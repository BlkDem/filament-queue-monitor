<?php

namespace Kilo\FilamentQueueMonitor\Tests\Unit;

use Illuminate\Support\Facades\DB;
use Kilo\FilamentQueueMonitor\QueueMonitor\DTO\JobInfo;
use Kilo\FilamentQueueMonitor\QueueMonitor\DTO\QueueStats;
use Kilo\FilamentQueueMonitor\QueueMonitor\QueueMonitorManager;

describe('DatabaseQueueMonitorDriver', function () {
    beforeEach(function () {
        config()->set('filament-queue-monitor.driver', 'database');
        $this->driver = app(QueueMonitorManager::class)->driver();
    });

    function makePayload(string $jobClass, string $uuid = 'test-uuid'): string
    {
        return json_encode([
            'uuid' => $uuid,
            'job' => $jobClass,
            'data' => ['commandName' => $jobClass, 'command' => ''],
        ]);
    }

    it('uses the Laravel queue default when no monitor driver is configured', function () {
        config()->set('filament-queue-monitor.driver', null);
        config()->set('queue.default', 'redis');

        expect(app(QueueMonitorManager::class)->getDefaultDriver())->toBe('redis');
    });

    it('supports custom database queue connections', function () {
        config()->set('filament-queue-monitor.driver', null);
        config()->set('queue.default', 'database_jobs');
        config()->set('queue.connections.database_jobs', [
            'driver' => 'database',
            'table' => 'jobs',
            'connection' => 'testing',
        ]);

        expect(app(QueueMonitorManager::class)->getDefaultDriver())->toBe('database');
    });

    it('detects queues from the jobs table', function () {
        DB::table('jobs')->insert([
            [
                'queue' => 'default',
                'payload' => makePayload('TestJob'),
                'created_at' => now()->timestamp,
                'available_at' => now()->timestamp,
                'attempts' => 1,
            ],
        ]);

        DB::table('jobs')->insert([
            [
                'queue' => 'emails',
                'payload' => makePayload('SendEmail'),
                'created_at' => now()->timestamp,
                'available_at' => now()->timestamp,
                'attempts' => 1,
            ],
        ]);

        $queues = $this->driver->getQueues();

        expect($queues)->toHaveCount(2);
    });

    it('returns default queue when no jobs exist', function () {
        $queues = $this->driver->getQueues();

        expect($queues)->toHaveCount(1)
            ->and($queues[0]->name)->toBe('default');
    });

    it('counts pending jobs correctly', function () {
        DB::table('jobs')->insert([
            [
                'queue' => 'default',
                'payload' => makePayload('TestJob'),
                'created_at' => now()->timestamp,
                'available_at' => now()->timestamp,
                'attempts' => 1,
            ],
        ]);

        $stats = $this->driver->stats('default');

        expect($stats->pending)->toBe(1)
            ->and($stats->total)->toBe(1);
    });

    it('counts processing jobs correctly', function () {
        DB::table('jobs')->insert([
            [
                'queue' => 'default',
                'payload' => makePayload('TestJob'),
                'created_at' => now()->timestamp,
                'available_at' => now()->timestamp,
                'attempts' => 1,
                'reserved_at' => now()->timestamp,
            ],
        ]);

        $stats = $this->driver->stats('default');

        expect($stats->processing)->toBe(1)
            ->and($stats->pending)->toBe(0);
    });

    it('counts delayed jobs correctly', function () {
        DB::table('jobs')->insert([
            [
                'queue' => 'default',
                'payload' => makePayload('TestJob'),
                'created_at' => now()->timestamp,
                'available_at' => now()->addHour()->timestamp,
                'attempts' => 1,
            ],
        ]);

        $stats = $this->driver->stats('default');

        expect($stats->delayed)->toBe(1)
            ->and($stats->pending)->toBe(0);
    });

    it('returns zero stats for nonexistent queue', function () {
        $stats = $this->driver->stats('nonexistent');

        expect($stats->pending)->toBe(0)
            ->and($stats->processing)->toBe(0)
            ->and($stats->delayed)->toBe(0)
            ->and($stats->total)->toBe(0);
    });

    it('returns pending jobs with correct data', function () {
        DB::table('jobs')->insert([
            [
                'queue' => 'default',
                'payload' => makePayload('App\\Jobs\\TestJob', 'test-uuid-123'),
                'created_at' => now()->timestamp,
                'available_at' => now()->timestamp,
                'attempts' => 1,
            ],
        ]);

        $jobs = $this->driver->pendingJobs('default');
        $job = $jobs[0];

        expect($job)->toBeInstanceOf(JobInfo::class)
            ->and($job->queue)->toBe('default')
            ->and($job->job)->toBe('App\\Jobs\\TestJob')
            ->and($job->uuid)->toBe('test-uuid-123');
    });

    it('returns processing jobs with reserved_at set', function () {
        DB::table('jobs')->insert([
            [
                'queue' => 'emails',
                'payload' => makePayload('App\\Jobs\\ProcessJob'),
                'created_at' => now()->timestamp,
                'available_at' => now()->timestamp,
                'reserved_at' => now()->timestamp,
                'attempts' => 2,
            ],
        ]);

        $jobs = $this->driver->processingJobs('emails');
        $job = $jobs[0];

        expect($job)->toBeInstanceOf(JobInfo::class)
            ->and($job->queue)->toBe('emails')
            ->and($job->attempts)->toBe(2)
            ->and($job->reservedAt)->not->toBeNull();
    });

    it('handles empty payload safely', function () {
        DB::table('jobs')->insert([
            [
                'queue' => 'default',
                'payload' => '{}',
                'created_at' => now()->timestamp,
                'available_at' => now()->timestamp,
                'attempts' => 1,
            ],
        ]);

        $jobs = $this->driver->pendingJobs('default');

        expect($jobs)->toHaveCount(1)
            ->and($jobs[0]->payload)->toBe('{}');
    });

    it('returns empty array for empty queue', function () {
        $jobs = $this->driver->pendingJobs('nonexistent-queue');

        expect($jobs)->toBe([]);
    });

    it('stats is instance of QueueStats', function () {
        $stats = $this->driver->stats('default');

        expect($stats)->toBeInstanceOf(QueueStats::class);
    });
});
