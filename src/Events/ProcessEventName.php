<?php

declare(strict_types=1);

namespace ProcessPool\Events;

enum ProcessEventName: string
{
    case PROCESS_FINISHED = 'process_finished';
    case PROCESS_STARTED = 'process_started';
}
