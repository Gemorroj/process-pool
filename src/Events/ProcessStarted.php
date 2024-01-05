<?php

declare(strict_types=1);

namespace ProcessPool\Events;

final class ProcessStarted extends ProcessEvent
{
    public function getName(): ProcessEventName
    {
        return ProcessEventName::PROCESS_STARTED;
    }
}
