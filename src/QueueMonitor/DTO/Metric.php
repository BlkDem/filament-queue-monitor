<?php

namespace BlkDem\FilamentQueueMonitor\QueueMonitor\DTO;

class Metric
{
    public function __construct(
        public readonly string $connection,
        public readonly string $queue,
        public readonly string $period, // e.g. '2024-01-01 10:00:00'
        public readonly int $processed = 0,
        public readonly int $failed = 0,
        public readonly ?float $avgRuntime = null,
        public readonly ?float $maxRuntime = null,
        public readonly string $job = 'unknown',
    ) {}
}
