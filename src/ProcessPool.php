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
 * Process pool allow you to run a constant number
 * of parallel processes.
 */
class ProcessPool
{
    /** @var \Iterator<Process> */
    private \Iterator $queue;

    /** @var array<Process> */
    private array $running = [];
    private Options $options;

    private EventDispatcherInterface $eventDispatcher;

    /**
     * Accept any type of iterator, inclusive Generator.
     *
     * @param \Iterator<Process> $queue
     */
    public function __construct(\Iterator $queue, Options $options = null, EventDispatcher $eventDispatcher = null)
    {
        $this->eventDispatcher = $eventDispatcher ?? new EventDispatcher();
        $this->options = $options ?? new Options();
        $this->queue = $queue;
    }

    /**
     * Start and wait until all processes finishes.
     */
    public function wait(): void
    {
        $this->startNextProcesses();

        while (\count($this->running) > 0) {
            /** @var Process $process */
            foreach ($this->running as $key => $process) {
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

                if (!$isRunning) {
                    unset($this->running[$key]);
                    $this->startNextProcesses();

                    $event = new ProcessFinished($process);

                    if ($exception) {
                        $event->setException($exception);
                    }

                    $this->dispatchEvent($event);
                }
            }
            \usleep(1000);
        }
    }

    public function onProcessFinished(callable $callback): void
    {
        $eventName = $this->prepareEventName(ProcessEventName::PROCESS_FINISHED);

        $this->eventDispatcher->addListener($eventName, $callback);
    }

    /**
     * Start next processes until fill the concurrency limit.
     */
    private function startNextProcesses(): void
    {
        $concurrency = $this->options->concurrency;

        while (\count($this->running) < $concurrency && $this->queue->valid()) {
            $process = $this->queue->current();
            $process->start();

            $this->dispatchEvent(new ProcessStarted($process));

            $this->running[] = $process;

            $this->queue->next();
        }
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
