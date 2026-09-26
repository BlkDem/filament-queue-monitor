<?php

namespace BlkDem\FilamentQueueMonitor\Tests\Unit;

use BlkDem\FilamentQueueMonitor\QueueMonitor\Models\QueueJob;

describe('QueueJob connection resolution', function () {
    it('uses the jobs table of the database queue connection', function () {
        config()->set('filament-queue-monitor.driver', 'database');
        config()->set('queue.default', 'database');
        config()->set('queue.connections.database', [
            'driver' => 'database',
            'table' => 'jobs',
            'connection' => 'testing',
        ]);

        $model = new QueueJob();

        expect($model->getConnectionName())->toBe('testing')
            ->and($model->getTable())->toBe('jobs');
    });

    it('falls back to the default connection when the queue connection declares none', function () {
        config()->set('filament-queue-monitor.driver', 'database');
        config()->set('queue.default', 'database');
        config()->set('queue.connections.database', [
            'driver' => 'database',
            'table' => 'jobs',
        ]);

        expect((new QueueJob())->getConnectionName())->toBe('testing');
    });

    it('never resolves a redis queue connection name to a database connection', function () {
        config()->set('filament-queue-monitor.driver', 'redis');
        config()->set('queue.default', 'redis');
        config()->set('queue.connections.redis', [
            'driver' => 'redis',
            'connection' => 'default',
            'queue' => 'default',
        ]);

        $model = new QueueJob();

        // A redis queue connection names a *redis* connection, so it must never
        // be handed to Eloquent as a database connection.
        expect($model->getConnectionName())->not->toBe('default')
            ->and(array_key_exists($model->getConnectionName(), config('database.connections')))->toBeTrue()
            ->and($model->getTable())->toBe('jobs');
    });

    it('never resolves an unconfigured connection when the queue itself runs on redis', function () {
        config()->set('filament-queue-monitor.driver', 'database');
        config()->set('queue.default', 'redis');
        config()->set('queue.connections.redis', [
            'driver' => 'redis',
            'connection' => 'default',
            'queue' => 'default',
        ]);

        $model = new QueueJob();

        expect($model->getConnectionName())->not->toBe('default')
            ->and(array_key_exists($model->getConnectionName(), config('database.connections')))->toBeTrue();
    });

    it('treats the sync connection as database backed', function () {
        config()->set('filament-queue-monitor.driver', 'database');
        config()->set('queue.default', 'sync');
        config()->set('queue.connections.database', [
            'driver' => 'database',
            'table' => 'jobs',
            'connection' => 'testing',
        ]);

        expect((new QueueJob())->getConnectionName())->toBe('testing');
    });

    it('resolves a valid connection for every queue connection shape', function (string $queueDefault, array $queueConnection) {
        config()->set('queue.default', $queueDefault);
        config()->set("queue.connections.{$queueDefault}", $queueConnection);

        foreach (['database', 'redis'] as $driver) {
            config()->set('filament-queue-monitor.driver', $driver);

            $connection = (new QueueJob())->getConnectionName();

            expect(array_key_exists($connection, config('database.connections')))->toBeTrue(
                "driver={$driver} queue.default={$queueDefault} resolved to [{$connection}]",
            );
        }
    })->with([
        'redis connection naming a redis connection' => ['redis', ['driver' => 'redis', 'connection' => 'default', 'queue' => 'default']],
        'database connection without a connection key' => ['database', ['driver' => 'database', 'table' => 'jobs']],
        'database connection with an explicit connection' => ['database', ['driver' => 'database', 'table' => 'jobs', 'connection' => 'testing']],
        'sync connection' => ['sync', ['driver' => 'sync']],
    ]);
});
