<?php

declare(strict_types=1);

namespace ProcessPool\Events;

use Symfony\Component\Process\Process;
use Symfony\Contracts\EventDispatcher\Event;

abstract class ProcessEvent extends Event
{
    private Process $process;

    public const PROCESS_FINISHED = 'process_finished';
    public const PROCESS_STARTED = 'process_started';

    public function __construct(Process $process)
    {
        $this->process = $process;
    }

    abstract public function getName(): string;

    public function getProcess(): Process
    {
        return $this->process;
    }
}
