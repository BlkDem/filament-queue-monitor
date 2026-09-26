<?php

namespace BlkDem\FilamentQueueMonitor;

use Illuminate\Support\ServiceProvider;
use BlkDem\FilamentQueueMonitor\QueueMonitor\Drivers\DatabaseQueueMonitorDriver;
use BlkDem\FilamentQueueMonitor\QueueMonitor\Drivers\RedisQueueMonitorDriver;
use BlkDem\FilamentQueueMonitor\QueueMonitor\Listeners\RecordQueueMetrics;
use BlkDem\FilamentQueueMonitor\QueueMonitor\QueueMonitorManager;
use BlkDem\FilamentQueueMonitor\QueueMonitor\Statistics\CompletedJobsStorage;
use BlkDem\FilamentQueueMonitor\QueueMonitor\Statistics\MetricsStorage;
use BlkDem\FilamentQueueMonitor\Support\Trans;

class FilamentQueueMonitorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/resources/config/filament-queue-monitor.php',
            'filament-queue-monitor'
        );

        $this->registerManager();
        $this->registerMetricsStorage();
        $this->registerEventListeners();

        $this->commands([
            Console\InstallCommand::class,
            Console\PruneCommand::class,
        ]);
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(
            __DIR__ . '/resources/lang',
            Trans::NAMESPACE
        );

        $this->loadViewsFrom(
            __DIR__ . '/resources/views',
            'filament-queue-monitor'
        );

        if (config('filament-queue-monitor.enabled', true)) {
            $this->loadMigrationsFrom(__DIR__ . '/Database/Migrations');
        }

        $this->publishes([
            __DIR__ . '/Database/Migrations' => database_path('migrations'),
        ], 'filament-queue-monitor-migrations');

        $this->publishes([
            __DIR__ . '/resources/config/filament-queue-monitor.php' => config_path('filament-queue-monitor.php'),
        ], 'filament-queue-monitor-config');

        $this->publishes([
            __DIR__ . '/resources/views' => resource_path('views/vendor/filament-queue-monitor'),
        ], 'filament-queue-monitor-views');

        $this->publishes([
            __DIR__ . '/resources/lang' => lang_path('vendor/'.Trans::NAMESPACE),
        ], 'filament-queue-monitor-lang');
    }

    protected function registerManager(): void
    {
        $this->app->singleton(QueueMonitorManager::class, function ($app) {
            return new QueueMonitorManager($app);
        });

        $this->app->bind(RedisQueueMonitorDriver::class, function () {
            return new RedisQueueMonitorDriver();
        });

        $this->app->bind(DatabaseQueueMonitorDriver::class, function () {
            return new DatabaseQueueMonitorDriver();
        });
    }

    protected function registerMetricsStorage(): void
    {
        $this->app->singleton(MetricsStorage::class, function () {
            return new MetricsStorage();
        });

        $this->app->singleton(CompletedJobsStorage::class, function () {
            return new CompletedJobsStorage();
        });
    }

    protected function registerEventListeners(): void
    {
        $events = $this->app->make('events');

        $events->listen(
            \Illuminate\Queue\Events\JobProcessing::class,
            [RecordQueueMetrics::class, 'handleProcessing']
        );

        $events->listen(
            \Illuminate\Queue\Events\JobProcessed::class,
            [RecordQueueMetrics::class, 'handleProcessed']
        );

        $events->listen(
            \Illuminate\Queue\Events\JobFailed::class,
            [RecordQueueMetrics::class, 'handleFailed']
        );

        $events->listen(
            \Illuminate\Queue\Events\JobExceptionOccurred::class,
            [RecordQueueMetrics::class, 'handleExceptionOccurred']
        );
    }
}
