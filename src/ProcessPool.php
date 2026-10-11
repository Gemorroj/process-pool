<?php

declare(strict_types=1);

namespace ProcessPool;

use ProcessPool\Events\ProcessEvent;
use ProcessPool\Events\ProcessEventName;
use ProcessPool\Events\ProcessFinished;
use ProcessPool\Events\ProcessStarted;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Process\Exception\RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Process pool allows you to run a constant number
 * of parallel processes.
 */
final class ProcessPool
{
    /** @var array<Process> */
    private array $running = [];

    private bool $started = false;

    /**
     * Accepts any type of iterator, including Generator.
     *
     * @param \Iterator<Process> $queue
     */
    public function __construct(private readonly \Iterator $queue, private readonly Options $options = new Options(), private readonly EventDispatcherInterface $eventDispatcher = new EventDispatcher())
    {
    }

    /**
     * Start and wait until all processes finish.
     */
    public function wait(): void
    {
        \iterator_count($this->run());
    }

    /**
     * Start processes and yield a ProcessFinished event as soon as each
     * process finishes, so results can be consumed while other processes
     * are still running.
     *
     * The generator holds at most {@see Options::$concurrency} processes in
     * memory. Stopping iteration early stops the still running processes.
     *
     * A pool is single-use: calling {@see run()} (or {@see wait()}) more than
     * once on the same instance throws a {@see \LogicException}.
     *
     * @throws \LogicException when the pool has already been run
     *
     * @return \Generator<int, ProcessFinished>
     */
    public function run(): \Generator
    {
        if ($this->started) {
            throw new \LogicException('ProcessPool can only be run once.');
        }

        $this->started = true;

        try {
            $this->startNextProcesses();

            while ([] !== $this->running) {
                foreach ($this->running as $process) {
                    $exception = null;
                    try {
                        $process->checkTimeout();
                        $isRunning = $process->isRunning();
                    } catch (RuntimeException $e) {
                        $isRunning = false;
                        $exception = $e;

                        if ($this->options->throwExceptions) {
                            throw $e;
                        }
                    }

                    if ($isRunning) {
                        continue;
                    }

                    $this->forget($process);

                    $event = new ProcessFinished($process, $exception);
                    $this->dispatchEvent($event);

                    $this->startNextProcesses();

                    yield $event;
                }
                \usleep(1000);
            }
        } finally {
            $this->stopRunningProcesses();
        }
    }

    public function onProcessStarted(callable $callback): void
    {
        $eventName = $this->prepareEventName(ProcessEventName::PROCESS_STARTED);

        $this->eventDispatcher->addListener($eventName, $callback);
    }

    public function onProcessFinished(callable $callback): void
    {
        $eventName = $this->prepareEventName(ProcessEventName::PROCESS_FINISHED);

        $this->eventDispatcher->addListener($eventName, $callback);
    }

    /**
     * Start the next processes until the concurrency limit is reached.
     */
    private function startNextProcesses(): void
    {
        $concurrency = $this->options->concurrency;

        while (\count($this->running) < $concurrency && $this->queue->valid()) {
            $process = $this->queue->current();
            $this->queue->next();

            $this->running[] = $process;

            $process->start();

            $this->dispatchEvent(new ProcessStarted($process));
        }
    }

    /**
     * Remove a finished process from the running set, keeping it hole-free.
     */
    private function forget(Process $process): void
    {
        $this->running = \array_values(\array_filter(
            $this->running,
            static fn (Process $running): bool => $running !== $process,
        ));
    }

    /**
     * Stop and forget every still running process.
     *
     * Used to avoid leaving orphaned processes behind when {@see run()}
     * exits early (e.g. {@see Options::$throwExceptions} is enabled).
     */
    private function stopRunningProcesses(): void
    {
        foreach ($this->running as $process) {
            $process->stop();
        }

        $this->running = [];
    }

    private function dispatchEvent(ProcessEvent $event): void
    {
        $this->eventDispatcher->dispatch($event, $this->prepareEventName($event->getName()));
    }

    private function prepareEventName(ProcessEventName $processEventName): string
    {
        $eventPrefix = $this->options->eventPrefix;
        $eventName = $processEventName->value;

        return "$eventPrefix.$eventName";
    }
}
