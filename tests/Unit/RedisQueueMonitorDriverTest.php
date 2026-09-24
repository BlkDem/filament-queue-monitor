<?php

namespace BlkDem\FilamentQueueMonitor\Tests\Unit;

use Illuminate\Support\Facades\Queue;
use BlkDem\FilamentQueueMonitor\QueueMonitor\DTO\JobInfo;
use BlkDem\FilamentQueueMonitor\QueueMonitor\DTO\QueueStats;
use BlkDem\FilamentQueueMonitor\QueueMonitor\Drivers\RedisQueueMonitorDriver;
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
});
