<?php

declare(strict_types=1);

namespace ProcessPool\Events;

use Symfony\Component\Process\Process;
use Symfony\Contracts\EventDispatcher\Event;

abstract class ProcessEvent extends Event
{
    public function __construct(private readonly Process $process)
    {
    }

    abstract public function getName(): ProcessEventName;

    public function getProcess(): Process
    {
        return $this->process;
    }
}
