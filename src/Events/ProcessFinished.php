<?php

declare(strict_types=1);

namespace ProcessPool\Events;

use Symfony\Component\Process\Process;

final class ProcessFinished extends ProcessEvent
{
    public function __construct(Process $process, private readonly ?\Throwable $exception = null)
    {
        parent::__construct($process);
    }

    public function getName(): ProcessEventName
    {
        return ProcessEventName::PROCESS_FINISHED;
    }

    public function getException(): ?\Throwable
    {
        return $this->exception;
    }
}
