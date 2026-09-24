<?php

namespace BlkDem\FilamentQueueMonitor\Tests;

use BlkDem\FilamentQueueMonitor\FilamentQueueMonitorServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app): array
    {
        return [
            FilamentQueueMonitorServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        $app['config']->set('queue.default', 'database');
        $app['config']->set('queue.connections.database', [
            'driver' => 'database',
            'table' => 'jobs',
            'queue' => 'default',
            'retry_after' => 90,
            'connection' => null,
        ]);

        $app['config']->set('queue.connections.redis', [
            'driver' => 'redis',
            'connection' => 'default',
            'queue' => 'default',
            'retry_after' => 90,
            'block_for' => null,
        ]);

        $app['config']->set('queue.failed.driver', 'database-uuids');
        $app['config']->set('queue.failed.database', 'testing');
        $app['config']->set('queue.failed.table', 'failed_jobs');

        $app['config']->set('database.redis.default', [
            'driver' => 'redis',
            'host' => '127.0.0.1',
            'password' => null,
            'port' => 6379,
            'database' => 0,
            'prefix' => '',
        ]);

        $app['config']->set('filament-queue-monitor.enabled', true);
        $app['config']->set('filament-queue-monitor.driver', 'database');
        $app['config']->set('filament-queue-monitor.refresh_interval', 10);
        $app['config']->set('filament-queue-monitor.metrics.enabled', true);
        $app['config']->set('filament-queue-monitor.metrics.retention_days', 30);

        $app['config']->set('app.key', 'AckfSEC3v7pB5Jh3_2kX2y3eZ7v8xVb5n8e3Y5s7tA');
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__ . '/../src/Database/Migrations');

        $this->artisan('migrate')->run();

        $this->createJobsTable();
        $this->createFailedJobsTable();
    }

    protected function createJobsTable(): void
    {
        $this->app['db']->connection('testing')->getSchemaBuilder()->create('jobs', function ($table) {
            $table->bigIncrements('id');
            $table->string('queue')->index();
            $table->text('payload');
            $table->unsignedTinyInteger('attempts')->default(1);
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at')->nullable();
        });
    }

    protected function createFailedJobsTable(): void
    {
        $this->app['db']->connection('testing')->getSchemaBuilder()->create('failed_jobs', function ($table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->string('connection');
            $table->string('queue');
            $table->text('payload')->nullable();
            $table->text('exception')->nullable();
            $table->timestamp('failed_at')->useCurrent();
        });
    }
}
