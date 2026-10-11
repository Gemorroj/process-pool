<?php

declare(strict_types=1);

namespace ProcessPool\Tests;

use PHPUnit\Framework\TestCase;
use ProcessPool\Events\ProcessFinished;
use ProcessPool\Events\ProcessStarted;
use ProcessPool\Options;
use ProcessPool\ProcessPool;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\PhpProcess;

final class ProcessPoolTest extends TestCase
{
    public function testWorksWithGenerators(): void
    {
        $this->assertFinishProcessesIn(6, 5);
    }

    public function testHandleExceptions(): void
    {
        $this->assertFinishProcessesIn(2, 5, 1.0);
    }

    public function testRunYieldsEveryFinishedProcess(): void
    {
        $exitCodes = [];
        foreach (new ProcessPool($this->makeSleepProcesses(4), new Options(2))->run() as $event) {
            $exitCodes[] = $event->getProcess()->getExitCode();
        }

        $this->assertSame([0, 0, 0, 0], $exitCodes);
    }

    public function testRunYieldsProcessesInCompletionOrder(): void
    {
        $outputs = [];
        foreach (new ProcessPool($this->makeEchoProcesses([800_000, 100_000, 500_000]))->run() as $event) {
            $outputs[] = $event->getProcess()->getOutput();
        }

        $this->assertSame(['1', '2', '0'], $outputs);
    }

    public function testStoppingIterationStopsRemainingProcesses(): void
    {
        $slow = new PhpProcess('<?php sleep(10); echo "slow";');
        $processes = $this->makeProcessesFrom([new PhpProcess('<?php echo "fast";'), $slow]);

        $start = \microtime(true);
        foreach (new ProcessPool($processes, new Options(2))->run() as $_) {
            break;
        }

        $this->assertFalse($slow->isRunning(), 'the remaining process should be stopped');
        $this->assertLessThan(5, \microtime(true) - $start, 'pool should not wait for the stopped process');
    }

    public function testFinishedEventCarriesTimeoutException(): void
    {
        $ok = new PhpProcess('<?php echo "ok";');
        $slow = new PhpProcess('<?php sleep(5);');
        $slow->setTimeout(0.5);

        $exceptions = [];
        $successful = 0;
        foreach (new ProcessPool($this->makeProcessesFrom([$ok, $slow]), new Options(2))->run() as $event) {
            if (null === $event->getException()) {
                ++$successful;
            } else {
                $exceptions[] = $event->getException();
            }
        }

        $this->assertSame(1, $successful);
        $this->assertCount(1, $exceptions);
        $this->assertInstanceOf(ProcessTimedOutException::class, $exceptions[0]);
    }

    public function testOnProcessStartedIsCalledForEachProcess(): void
    {
        $pool = new ProcessPool($this->makeSleepProcesses(3), new Options(2));

        $started = 0;
        $pool->onProcessStarted(static function (ProcessStarted $event) use (&$started): void {
            ++$started;
        });

        $pool->wait();

        $this->assertSame(3, $started);
    }

    public function testConcurrencyLimitIsNotExceeded(): void
    {
        $concurrency = 2;
        $pool = new ProcessPool(
            $this->makeEchoProcesses([40_000, 40_000, 40_000, 40_000, 40_000, 40_000]),
            new Options($concurrency),
        );

        $active = 0;
        $maxActive = 0;
        $pool->onProcessStarted(static function () use (&$active, &$maxActive): void {
            $maxActive = \max($maxActive, ++$active);
        });
        $pool->onProcessFinished(static function () use (&$active): void {
            --$active;
        });

        $pool->wait();

        $this->assertSame($concurrency, $maxActive);
    }

    public function testCustomEventDispatcherReceivesEvents(): void
    {
        $dispatcher = new EventDispatcher();
        $finished = 0;
        $dispatcher->addListener('process_pool.process_finished', static function () use (&$finished): void {
            ++$finished;
        });

        new ProcessPool($this->makeSleepProcesses(2), new Options(), $dispatcher)->wait();

        $this->assertSame(2, $finished);
    }

    public function testPoolCanOnlyBeRunOnce(): void
    {
        $pool = new ProcessPool($this->makeSleepProcesses(1), new Options());

        $pool->wait();

        $this->expectException(\LogicException::class);

        $pool->wait();
    }

    public function testThrowExceptions(): void
    {
        $processes = $this->makeSleepProcesses(2, 0.5);
        $options = new Options(2, true);
        $pool = new ProcessPool($processes, $options);

        $this->expectException(ProcessTimedOutException::class);

        $pool->wait();
    }

    private function assertFinishProcessesIn(int $expectedTime, int $countProcesses, ?float $timeout = null): void
    {
        $processes = $this->makeSleepProcesses($countProcesses, $timeout);
        $countFinished = 0;
        $options = new Options(2);

        $pool = new ProcessPool($processes, $options);
        $pool->onProcessFinished(static function (ProcessFinished $event) use (&$countFinished): void {
            ++$countFinished;
        });

        $start = \microtime(true);

        $pool->wait();

        $this->assertSame($countProcesses, $countFinished);
        $this->assertLessThanOrEqual($expectedTime + 1, \microtime(true) - $start, 'pool should run processes concurrently');
    }

    /**
     * @return \Generator<int, PhpProcess>
     */
    private function makeSleepProcesses(int $count, ?float $timeout = null): \Generator
    {
        for ($i = 0; $i < $count; ++$i) {
            $process = new PhpProcess("<?php sleep($i); echo $i;");
            if (null !== $timeout) {
                $process->setTimeout($timeout);
            }

            yield $process;
        }
    }

    /**
     * Each process echoes its index after sleeping the given microseconds.
     *
     * @param list<int> $usleeps
     *
     * @return \Generator<int, PhpProcess>
     */
    private function makeEchoProcesses(array $usleeps): \Generator
    {
        foreach ($usleeps as $index => $usleep) {
            yield new PhpProcess("<?php usleep($usleep); echo $index;");
        }
    }

    /**
     * @param list<PhpProcess> $processes
     *
     * @return \Generator<int, PhpProcess>
     */
    private function makeProcessesFrom(array $processes): \Generator
    {
        yield from $processes;
    }
}
