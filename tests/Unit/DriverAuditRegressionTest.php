<?php

use BlkDem\FilamentQueueMonitor\QueueMonitor\Drivers\DatabaseQueueMonitorDriver;
use BlkDem\FilamentQueueMonitor\QueueMonitor\Drivers\RedisQueueMonitorDriver;
use Illuminate\Support\Facades\DB;

it('reads database job timestamps as Unix seconds', function () {
    DB::table('jobs')->insert([
        'queue' => 'default',
        'payload' => '{}',
        'attempts' => 1,
        'created_at' => 1700000000,
        'available_at' => 1700000001,
        'reserved_at' => 1700000002,
    ]);

    $driver = new DatabaseQueueMonitorDriver();
    $job = $driver->processingJobs('default')[0];

    expect($job->createdAt->timestamp)->toBe(1700000000)
        ->and($job->reservedAt->timestamp)->toBe(1700000002)
        ->and($driver->info('default')->lastActivityAt->timestamp)->toBe(1700000000);
});

it('accepts Unix timestamps returned as strings by database drivers', function () {
    $driver = new class extends DatabaseQueueMonitorDriver
    {
        public function parseRows(iterable $rows): array
        {
            return $this->rowsToJobInfo($rows);
        }
    };
    $job = $driver->parseRows([(object) [
        'payload' => '{}',
        'created_at' => '1700000000',
        'available_at' => '1700000001',
        'reserved_at' => '1700000002',
    ]])[0];

    expect($job->createdAt->timestamp)->toBe(1700000000)
        ->and($job->availableAt->timestamp)->toBe(1700000001)
        ->and($job->reservedAt->timestamp)->toBe(1700000002);
});

it('falls back safely when the configured database queue array is empty', function () {
    config()->set('queue.connections.database.queue', []);

    expect((new DatabaseQueueMonitorDriver())->getQueues()[0]->name)->toBe('default');
});

it('scopes database failure counts to the connection actually monitored', function () {
    config()->set('queue.default', 'redis');
    config()->set('filament-queue-monitor.driver', 'database');
    DB::table('failed_jobs')->insert([
        'uuid' => 'database-failure',
        'connection' => 'database',
        'queue' => 'default',
        'payload' => '{}',
        'exception' => 'failure',
    ]);

    $driver = new DatabaseQueueMonitorDriver();

    expect($driver->failedJobsCount())->toBe(1)
        ->and($driver->stats('default')->failed)->toBe(1);
});

it('supports array records from failed job providers', function () {
    $record = [
        'id' => 'array-id',
        'uuid' => 'array-uuid',
        'connection' => 'database',
        'queue' => 'default',
        'payload' => '{}',
        'exception' => 'failure',
        'failed_at' => '2025-01-02 03:04:05',
    ];
    $failer = Mockery::mock();
    $failer->shouldReceive('all')->andReturn([$record]);
    $failer->shouldReceive('find')->with('array-id')->andReturn($record);
    app()->instance('queue.failer', $failer);
    $driver = new DatabaseQueueMonitorDriver();

    expect($driver->failedJobs()[0]->id)->toBe('array-id')
        ->and($driver->findFailedJob('array-id')->failedAt->format('Y-m-d H:i:s'))->toBe('2025-01-02 03:04:05');
});

it('invalidates failure caches after deleting a failed job', function (string $driverClass, string $connection) {
    config()->set('filament-queue-monitor.driver', $connection);
    DB::table('failed_jobs')->insert([
        'uuid' => 'cached-failure',
        'connection' => $connection,
        'queue' => 'default',
        'payload' => '{}',
        'exception' => 'failure',
    ]);
    $driver = new $driverClass();
    expect($driver->failedJobsCount())->toBe(1);
    $driver->deleteFailedJob('cached-failure');
    expect($driver->failedJobsCount())->toBe(0);
})->with([
    'database' => [DatabaseQueueMonitorDriver::class, 'database'],
    'redis' => [RedisQueueMonitorDriver::class, 'redis'],
]);

it('invalidates failure caches after retrying a failed job', function (string $driverClass, string $connection) {
    config()->set('filament-queue-monitor.driver', $connection);
    \Illuminate\Support\Facades\Redis::connection()->del('queues:default');
    DB::table('failed_jobs')->insert([
        'uuid' => 'retried-failure',
        'connection' => $connection,
        'queue' => 'default',
        'payload' => json_encode(['attempts' => 4, 'data' => ['command' => ['invalid']]]),
        'exception' => 'failure',
    ]);
    $driver = new $driverClass();
    expect($driver->failedJobsCount())->toBe(1);
    $driver->retryFailedJob('retried-failure');

    $pushed = $connection === 'redis'
        ? \Illuminate\Support\Facades\Redis::connection()->lindex('queues:default', 0)
        : DB::table('jobs')->value('payload');

    expect($driver->failedJobsCount())->toBe(0)
        ->and(json_decode($pushed, true)['attempts'])->toBe(0);

    \Illuminate\Support\Facades\Redis::connection()->del('queues:default');
})->with([
    'database' => [DatabaseQueueMonitorDriver::class, 'database'],
    'redis' => [RedisQueueMonitorDriver::class, 'redis'],
]);

it('leaves malformed command values intact while refreshing retry deadlines', function () {
    $driver = new class extends DatabaseQueueMonitorDriver
    {
        public function refresh(string $payload): string
        {
            return $this->refreshRetryUntil($payload);
        }
    };
    $payload = json_encode(['data' => ['command' => ['invalid']]]);

    expect($driver->refresh($payload))->toBe($payload);
});
