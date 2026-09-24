<?php

namespace BlkDem\FilamentQueueMonitor\Tests\Unit;

use Illuminate\Support\Facades\DB;
use BlkDem\FilamentQueueMonitor\QueueMonitor\DTO\FailedJobInfo;
use BlkDem\FilamentQueueMonitor\QueueMonitor\QueueMonitorManager;

describe('Failed Jobs', function () {
    beforeEach(function () {
        config()->set('filament-queue-monitor.driver', 'database');
        $this->driver = app(QueueMonitorManager::class)->driver();
    });

    it('lists all failed jobs', function () {
        insertFailedJob('default', 'App\\Jobs\\TestJob');
        insertFailedJob('emails', 'App\\Jobs\\SendEmail');

        $jobs = $this->driver->failedJobs();

        expect($jobs)->toHaveCount(2)
            ->and($jobs[0])->toBeInstanceOf(FailedJobInfo::class);
    });

    it('finds a failed job by id', function () {
        $id = insertFailedJob('default', 'App\\Jobs\\TestJob');

        $job = $this->driver->findFailedJob($id);

        expect($job)->not->toBeNull()
            ->and($job->connection)->toBe('database')
            ->and($job->queue)->toBe('default');
    });

    it('returns null for nonexistent failed job', function () {
        $job = $this->driver->findFailedJob('nonexistent-id');

        expect($job)->toBeNull();
    });

    it('deletes a failed job', function () {
        $id = insertFailedJob('default', 'App\\Jobs\\TestJob');

        $this->driver->deleteFailedJob($id);

        $job = $this->driver->findFailedJob($id);
        expect($job)->toBeNull();
    });

    it('retries a failed job and removes it from failed jobs', function () {
        $id = insertFailedJob('default', 'App\\Jobs\\TestJob');

        $this->driver->retryFailedJob($id);

        $job = $this->driver->findFailedJob($id);
        expect($job)->toBeNull();
    });
})->tap(fn () => null);

function insertFailedJob(string $queue, string $jobClass): string
{
    $uuid = \Illuminate\Support\Str::uuid()->toString();
    $payload = json_encode([
        'uuid' => $uuid,
        'job' => $jobClass,
        'data' => ['commandName' => $jobClass, 'command' => ''],
    ]);
    $exception = 'Exception message in /path/to/file.php:42';

    DB::table('failed_jobs')->insert([
        'uuid' => $uuid,
        'connection' => 'database',
        'queue' => $queue,
        'payload' => $payload,
        'exception' => $exception,
        'failed_at' => now()->toDateTimeString(),
    ]);

    return $uuid;
}
