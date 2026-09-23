<?php

namespace Kilo\FilamentQueueMonitor\QueueMonitor\DTO;

class QueueInfo
{
    public function __construct(
        public readonly string $name,
        public readonly int $pending = 0,
        public readonly int $processing = 0,
        public readonly int $delayed = 0,
        public readonly int $completed = 0,
        public readonly int $failed = 0,
        public readonly int $total = 0,
        public readonly ?\Illuminate\Support\Carbon $lastActivityAt = null,
    ) {}
}
