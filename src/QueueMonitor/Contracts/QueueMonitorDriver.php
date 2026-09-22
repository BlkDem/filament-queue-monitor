<?php

namespace Kilo\FilamentQueueMonitor\QueueMonitor\Contracts;

use Kilo\FilamentQueueMonitor\QueueMonitor\DTO\QueueInfo;
use Kilo\FilamentQueueMonitor\QueueMonitor\DTO\QueueStats;
use Kilo\FilamentQueueMonitor\QueueMonitor\DTO\JobInfo;
use Kilo\FilamentQueueMonitor\QueueMonitor\DTO\FailedJobInfo;

interface QueueMonitorDriver
{
    public function getQueues(): array;

    public function stats(string $queue): QueueStats;

    public function info(string $queue): QueueInfo;

    public function pendingJobs(string $queue): iterable;

    public function processingJobs(string $queue): iterable;

    public function failedJobs(): iterable;

    public function findFailedJob(string $id): ?FailedJobInfo;

    public function retryFailedJob(string $id): void;

    public function deleteFailedJob(string $id): void;
}
