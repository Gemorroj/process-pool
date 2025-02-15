<?php

declare(strict_types=1);

namespace ProcessPool\Tests;

use PHPUnit\Framework\TestCase;
use ProcessPool\Events\ProcessFinished;
use ProcessPool\Options;
use ProcessPool\ProcessPool;
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

    private function assertFinishProcessesIn(int $expectedTime, int $countProcesses, ?float $timeout = null): void
    {
        $processes = $this->makeSleepProcesses($countProcesses, $timeout);
        $countFinished = 0;
        $options = new Options(2);

        $pool = new ProcessPool($processes, $options);
        $pool->onProcessFinished(static function (ProcessFinished $event) use (&$countFinished): void {
            $process = $event->getProcess();
            $exception = $event->getException();
            ++$countFinished;
        });

        $start = \microtime(true);

        $pool->wait();

        $this->assertEquals($countProcesses, $countFinished);
        $this->assertEquals($expectedTime, \round(\microtime(true) - $start), 'assert duration');
    }

    public function testThrowExceptions(): void
    {
        $processes = $this->makeSleepProcesses(6, 5.0);
        $options = new Options(6, true);
        $pool = new ProcessPool($processes, $options);

        $this->expectException(ProcessTimedOutException::class);

        $pool->wait();
    }

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
}
