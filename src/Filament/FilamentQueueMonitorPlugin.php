<?php

namespace Kilo\FilamentQueueMonitor\Filament;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Kilo\FilamentQueueMonitor\Filament\Pages\Dashboard;
use Kilo\FilamentQueueMonitor\Filament\Pages\FailedJobs\ListFailedJobs;
use Kilo\FilamentQueueMonitor\Filament\Pages\Jobs\ListJobs;
use Kilo\FilamentQueueMonitor\Filament\Pages\Queues\ListQueues;
use Kilo\FilamentQueueMonitor\Filament\Pages\Queues\ViewQueue;

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
            ListFailedJobs::class,
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
