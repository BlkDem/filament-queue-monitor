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

    /**
     * The list methods take a limit because the pages and widgets only ever
     * show a window of them, and a backlog is exactly when reading the whole
     * queue into memory would take the monitor down.
     */
    public function pendingJobs(string $queue, ?int $limit = null): iterable;

    public function processingJobs(string $queue, ?int $limit = null): iterable;

    public function delayedJobs(string $queue, ?int $limit = null): iterable;

    /**
     * Newest first. A limit keeps a large failed_jobs table, payloads and
     * stack traces included, from being loaded whole.
     */
    public function failedJobs(?int $limit = null): iterable;

    public function failedJobsCount(?string $connection = null): int;

    public function stuckJobsCount(int $thresholdHours): int;

    public function findFailedJob(string $id): ?FailedJobInfo;

    public function retryFailedJob(string $id): void;

    public function deleteFailedJob(string $id): void;
}
