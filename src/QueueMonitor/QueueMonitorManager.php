<?php

namespace Kilo\FilamentQueueMonitor\QueueMonitor;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Manager;
use Kilo\FilamentQueueMonitor\QueueMonitor\Contracts\QueueMonitorDriver;
use Kilo\FilamentQueueMonitor\QueueMonitor\Drivers\DatabaseQueueMonitorDriver;
use Kilo\FilamentQueueMonitor\QueueMonitor\Drivers\RedisQueueMonitorDriver;

class QueueMonitorManager extends Manager
{
    public function __construct(Application $application)
    {
        parent::__construct($application);
    }

    public function getDefaultDriver(): string
    {
        $driver = config('filament-queue-monitor.driver');

        if (! $driver) {
            $driver = config('queue.default', 'database');
        }

        if ($driver === 'redis') {
            return 'redis';
        }

        if (in_array($driver, ['database', 'sync'])) {
            return 'database';
        }

        return $driver;
    }

    protected function createRedisDriver(): QueueMonitorDriver
    {
        return $this->container->make(RedisQueueMonitorDriver::class);
    }

    protected function createDatabaseDriver(): QueueMonitorDriver
    {
        return $this->container->make(DatabaseQueueMonitorDriver::class);
    }

    public function getDriverMap(): array
    {
        return [
            'redis' => RedisQueueMonitorDriver::class,
            'database' => DatabaseQueueMonitorDriver::class,
        ];
    }

    public function driver($driver = null): QueueMonitorDriver
    {
        return parent::driver($driver);
    }
}
