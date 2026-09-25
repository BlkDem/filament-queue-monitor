<?php

namespace BlkDem\FilamentQueueMonitor\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use BlkDem\FilamentQueueMonitor\Filament\Pages\Dashboard;
use BlkDem\FilamentQueueMonitor\Filament\Pages\FailedJobs\ListFailedJobs;
use BlkDem\FilamentQueueMonitor\Filament\Pages\FailedJobs\ViewFailedJob;
use BlkDem\FilamentQueueMonitor\Filament\Pages\Jobs\ListDelayedJobs;
use BlkDem\FilamentQueueMonitor\Filament\Pages\Jobs\ListJobs;
use BlkDem\FilamentQueueMonitor\Filament\Pages\Queues\ListQueues;
use BlkDem\FilamentQueueMonitor\Filament\Pages\Queues\ViewQueue;
use BlkDem\FilamentQueueMonitor\Filament\Widgets\JobBreakdownWidget;
use BlkDem\FilamentQueueMonitor\Filament\Widgets\QueueActivityWidget;
use BlkDem\FilamentQueueMonitor\Filament\Widgets\QueueCountersWidget;
use BlkDem\FilamentQueueMonitor\Filament\Widgets\QueueStatsOverviewWidget;

class FilamentQueueMonitorPlugin implements Plugin
{
    public function getId(): string
    {
        return 'filamentQueueMonitor';
    }

    public function register(Panel $panel): void
    {
        $panel->pages([
            Dashboard::class,
            ListQueues::class,
            ViewQueue::class,
            ListJobs::class,
            ListDelayedJobs::class,
            ListFailedJobs::class,
            ViewFailedJob::class,
        ]);

        $panel->widgets([
            QueueStatsOverviewWidget::class,
            QueueCountersWidget::class,
            QueueActivityWidget::class,
            JobBreakdownWidget::class,
        ]);

        if ((bool) config('filament-queue-monitor.navigation.enabled', true)) {
            $group = config('filament-queue-monitor.navigation.group', 'Queue Monitor');
            $panel->navigationGroups([
                is_string($group) && $group !== '' ? $group : 'Queue Monitor',
            ]);
        }
    }

    public function boot(Panel $panel): void
    {
    }

    public static function make(): static
    {
        return app(static::class);
    }

    public static function get(): static
    {
        return app(static::class);
    }
}
