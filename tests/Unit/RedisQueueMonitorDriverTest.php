<?php

namespace BlkDem\FilamentQueueMonitor\Tests\Unit;

use Illuminate\Support\Facades\Queue;
use BlkDem\FilamentQueueMonitor\QueueMonitor\DTO\JobInfo;
use BlkDem\FilamentQueueMonitor\QueueMonitor\DTO\QueueStats;
use BlkDem\FilamentQueueMonitor\QueueMonitor\Drivers\RedisQueueMonitorDriver;
use BlkDem\FilamentQueueMonitor\Filament\Widgets\QueueActivityWidget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

describe('RedisQueueMonitorDriver', function () {
    beforeEach(function () {
        config()->set('filament-queue-monitor.driver', 'redis');
        config()->set('database.redis.default', [
            'driver' => 'redis',
            'host' => '127.0.0.1',
            'password' => null,
            'port' => 6379,
            'database' => 0,
            'prefix' => '',
        ]);
    });

    function flushRedis(): void
    {
        Redis::connection()->flushDB();
    }

    afterEach(function () {
        flushRedis();
    });

    it('detects queues from redis keys', function () {
        Redis::connection()->lpush('queues:default', json_encode([
            'uuid' => 'test-1',
            'job' => 'TestJob',
            'queue' => 'default',
            'attempts' => 1,
            'createdAt' => now()->timestamp,
            'data' => ['commandName' => 'TestJob'],
        ]));

        Redis::connection()->lpush('queues:emails', json_encode([
            'uuid' => 'test-2',
            'job' => 'SendEmailJob',
            'queue' => 'emails',
            'attempts' => 1,
            'createdAt' => now()->timestamp,
            'data' => ['commandName' => 'SendEmailJob'],
        ]));

        $driver = new RedisQueueMonitorDriver();

        $queues = $driver->getQueues();

        expect($queues)->toHaveCount(2);
    });

    it('detects queues from prefixed redis keys', function () {
        config()->set('database.redis.default.prefix', 'tenant:');
        \Illuminate\Support\Facades\Facade::clearResolvedInstance('redis');

        Redis::connection()->client()->set('queues:prefixed', json_encode([
            'uuid' => 'test-prefixed',
            'job' => 'TestJob',
            'queue' => 'prefixed',
            'attempts' => 1,
            'createdAt' => now()->timestamp,
            'data' => ['commandName' => 'TestJob'],
        ]));

        $driver = new RedisQueueMonitorDriver();

        expect(array_column(array_map(fn ($queue) => get_object_vars($queue), $driver->getQueues()), 'name'))
            ->toContain('prefixed');
    });

    it('handles malformed nested payloads', function () {
        Redis::connection()->rpush('queues:malformed', json_encode([
            'uuid' => 'malformed',
            'data' => 'not-an-array',
        ]));

        $driver = new RedisQueueMonitorDriver();

        expect($driver->pendingJobs('malformed')[0]->job)->toBe('Unknown');
    });

    it('returns empty queues list when redis is empty', function () {
        $driver = new RedisQueueMonitorDriver();

        $queues = $driver->getQueues();

        expect($queues)->toBeArray();
    });

    it('returns correct stats for redis queue', function () {
        Redis::connection()->lpush('queues:default', json_encode([
            'uuid' => 'test-1',
            'job' => 'TestJob',
            'queue' => 'default',
            'attempts' => 1,
            'createdAt' => now()->timestamp,
            'data' => ['commandName' => 'TestJob'],
        ]));

        $driver = new RedisQueueMonitorDriver();

        $stats = $driver->stats('default');

        expect($stats)->toBeInstanceOf(QueueStats::class)
            ->and($stats->pending)->toBe(1)
            ->and($stats->total)->toBe(1);
    });

    it('returns zero stats for nonexistent queue', function () {
        $driver = new RedisQueueMonitorDriver();

        $stats = $driver->stats('nonexistent');

        expect($stats->pending)->toBe(0)
            ->and($stats->processing)->toBe(0)
            ->and($stats->total)->toBe(0);
    });

    it('returns pending jobs with parsed data', function () {
        Redis::connection()->lpush('queues:default', json_encode([
            'uuid' => 'redis-uuid-1',
            'job' => 'App\Jobs\RedisTestJob',
            'queue' => 'default',
            'attempts' => 1,
            'createdAt' => now()->timestamp,
            'data' => ['commandName' => 'App\Jobs\RedisTestJob', 'command' => ''],
        ]));

        $driver = new RedisQueueMonitorDriver();

        $jobs = $driver->pendingJobs('default');

        expect($jobs)->toHaveCount(1)
            ->and($jobs[0])->toBeInstanceOf(JobInfo::class)
            ->and($jobs[0]->uuid)->toBe('redis-uuid-1')
            ->and($jobs[0]->job)->toBe('App\Jobs\RedisTestJob');
    });

    it('parses queue info correctly', function () {
        Redis::connection()->lpush('queues:default', json_encode([
            'uuid' => 'test-info',
            'job' => 'TestJob',
            'queue' => 'default',
            'attempts' => 1,
            'createdAt' => now()->timestamp,
            'data' => ['commandName' => 'TestJob'],
        ]));

        $driver = new RedisQueueMonitorDriver();

        $info = $driver->info('default');

        expect($info->name)->toBe('default')
            ->and($info->pending)->toBe(1);
    });

    it('counts stuck jobs in discovered queues without an explicit allowlist', function () {
        config()->set('filament-queue-monitor.redis.queues', []);

        // A sorted set is keyed by the payload, so each member must be unique.
        $make = fn (string $uuid) => json_encode([
            'uuid' => $uuid,
            'job' => 'TestJob',
            'queue' => 'discovered',
            'attempts' => 1,
            'createdAt' => now()->timestamp,
            'data' => ['commandName' => 'TestJob'],
        ]);

        Redis::connection()->zadd('queues:discovered:reserved', now()->subHours(26)->timestamp, $make('stale'));
        Redis::connection()->zadd('queues:discovered:reserved', now()->subHours(20)->timestamp, $make('stale-2'));
        Redis::connection()->zadd('queues:discovered:reserved', now()->subMinutes(5)->timestamp, $make('fresh'));

        $driver = new RedisQueueMonitorDriver();

        expect($driver->getQueues())->toHaveCount(1)
            ->and($driver->stuckJobsCount(12))->toBe(2)
            ->and($driver->stuckJobsCount(24))->toBe(1);
    });

    it('builds queue activity records from redis pending and reserved jobs', function () {
        config()->set('filament-queue-monitor.redis.queues', []);

        $payload = fn (string $uuid, string $queue) => json_encode([
            'uuid' => $uuid,
            'displayName' => 'App\\Jobs\\ActivityJob',
            'job' => 'Illuminate\\Queue\\CallQueuedHandler@call',
            'queue' => $queue,
            'attempts' => 2,
            'createdAt' => now()->timestamp,
            'data' => ['commandName' => 'App\\Jobs\\ActivityJob', 'command' => ''],
        ]);

        Redis::connection()->lpush('queues:redis-activity', $payload('pending-1', 'redis-activity'));
        Redis::connection()->lpush('queues:redis-activity', $payload('pending-2', 'redis-activity'));
        Redis::connection()->zadd(
            'queues:redis-activity:reserved',
            now()->subMinutes(2)->timestamp,
            $payload('reserved-1', 'redis-activity'),
        );

        $widget = new QueueActivityWidget();
        $widget->boot();

        $records = new \ReflectionMethod($widget, 'driverRecords');
        $records->setAccessible(true);
        $rows = $records->invoke($widget);

        expect($rows)->toHaveCount(3)
            ->and(collect($rows)->pluck('queue')->unique()->all())->toBe(['redis-activity'])
            ->and(collect($rows)->pluck('attempts')->unique()->all())->toBe([2]);

        $reserved = collect($rows)->firstWhere('id', 'reserved-1');

        expect($reserved['reserved_at'])->not->toBeNull()
            ->and(collect($rows)->firstWhere('id', 'pending-1')['reserved_at'])->toBeNull();

        $counts = new \ReflectionMethod($widget, 'groupCounts');
        $counts->setAccessible(true);

        expect($counts->invoke($widget, 'queue'))->toBe(['redis-activity' => 3])
            ->and($counts->invoke($widget, 'status'))->toBe(['pending' => 2, 'processing' => 1]);
    });

    it('counts failed jobs even when no queue is present in redis', function () {
        config()->set('queue.default', 'redis');
        config()->set('queue.connections.redis', ['driver' => 'redis', 'connection' => 'default']);

        $insert = fn (string $connection, string $queue) => DB::table('failed_jobs')->insert([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'connection' => $connection,
            'queue' => $queue,
            'payload' => json_encode([
                'uuid' => 'x',
                'displayName' => 'App\\Jobs\\FailingJob',
                'data' => ['commandName' => 'App\\Jobs\\FailingJob', 'command' => ''],
            ]),
            'exception' => 'boom',
            'failed_at' => now(),
        ]);

        $insert('redis', 'long-gone-queue');
        $insert('database', 'legacy-queue');

        $driver = new RedisQueueMonitorDriver();

        // The queue has no redis keys at all, so summing over getQueues() would
        // report nothing even though the failer still holds the job. The failure
        // left over from the previous database connection must not be counted.
        expect($driver->getQueues())->toBe([])
            ->and($driver->failedJobsCount())->toBe(1);
    });

    it('excludes failed jobs from a previous queue connection', function () {
        config()->set('queue.default', 'redis');

        foreach (['redis', 'database'] as $connection) {
            DB::table('failed_jobs')->insert([
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'connection' => $connection,
                'queue' => 'errors',
                'payload' => '{}',
                'exception' => 'boom',
                'failed_at' => now(),
            ]);
        }

        $driver = new RedisQueueMonitorDriver();

        expect($driver->failedJobsCount())->toBe(1)
            ->and($driver->failedJobsCount('database'))->toBe(1)
            ->and($driver->info('errors')->failed)->toBe(1);
    });

    it('counts stuck jobs in explicitly configured queues that are not in redis', function () {
        config()->set('filament-queue-monitor.redis.queues', 'idle-queue, processing');

        $payload = json_encode([
            'uuid' => 'stuck-3',
            'job' => 'TestJob',
            'queue' => 'processing',
            'attempts' => 1,
            'createdAt' => now()->timestamp,
            'data' => ['commandName' => 'TestJob'],
        ]);

        Redis::connection()->zadd('queues:processing:reserved', now()->subHours(30)->timestamp, $payload);

        $driver = new RedisQueueMonitorDriver();

        expect($driver->stuckJobsCount(12))->toBe(1);
    });
});
