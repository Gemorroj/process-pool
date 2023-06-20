<?php

declare(strict_types=1);

namespace ProcessPool;

readonly class Options
{
    public function __construct(
        public int $concurrency = 5,
        public bool $throwExceptions = false,
        public string $eventPrefix = 'process_pool',
    ) {
    }
}
