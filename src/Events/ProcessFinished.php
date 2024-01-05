<?php

declare(strict_types=1);

namespace ProcessPool\Events;

final class ProcessFinished extends ProcessEvent
{
    private ?\Exception $exception = null;

    public function getName(): ProcessEventName
    {
        return ProcessEventName::PROCESS_FINISHED;
    }

    public function setException(\Exception $exception): self
    {
        $this->exception = $exception;

        return $this;
    }

    public function getException(): ?\Exception
    {
        return $this->exception;
    }
}
