<?php

namespace Kilo\FilamentQueueMonitor\QueueMonitor\DTO;

class QueueStats
{
    public function __construct(
        public readonly int $pending = 0,
        public readonly int $processing = 0,
        public readonly int $delayed = 0,
        public readonly int $completed = 0,
        public readonly int $failed = 0,
        public readonly int $total = 0,
    ) {}
}
