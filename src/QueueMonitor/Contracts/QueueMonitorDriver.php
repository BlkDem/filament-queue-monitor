<?php

namespace BlkDem\FilamentQueueMonitor\QueueMonitor\Contracts;

use BlkDem\FilamentQueueMonitor\QueueMonitor\DTO\QueueInfo;
use BlkDem\FilamentQueueMonitor\QueueMonitor\DTO\QueueStats;
use BlkDem\FilamentQueueMonitor\QueueMonitor\DTO\JobInfo;
use BlkDem\FilamentQueueMonitor\QueueMonitor\DTO\FailedJobInfo;

interface QueueMonitorDriver
{
    public function getQueues(): array;

    public function stats(string $queue): QueueStats;

    public function info(string $queue): QueueInfo;

    public function pendingJobs(string $queue): iterable;

    public function processingJobs(string $queue): iterable;

    public function delayedJobs(string $queue): iterable;

    public function failedJobs(): iterable;

    public function findFailedJob(string $id): ?FailedJobInfo;

    public function retryFailedJob(string $id): void;

    public function deleteFailedJob(string $id): void;
}
