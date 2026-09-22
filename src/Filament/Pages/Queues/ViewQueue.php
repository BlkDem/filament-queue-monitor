<?php

namespace Kilo\FilamentQueueMonitor\Filament\Pages\Queues;

use Filament\Pages\Page;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Filament\Widgets\StatsOverviewWidget;
use Kilo\FilamentQueueMonitor\QueueMonitor\QueueMonitorManager;

class ViewQueue extends Page
{
    protected static string $view = 'filament-queue-monitor::pages.view-queue';

    protected static ?string $navigationLabel = 'Queue Details';

    protected static ?string $navigationGroup = 'Queue Monitor';

    protected static ?string $slug = 'queue-monitor/queues/{queue}';

    protected static bool $shouldRegisterNavigation = false;

    public string $queue;

    public function mount(string $queue): void
    {
        $this->queue = $queue;
    }

    public function getStats(): array
    {
        $driver = app(QueueMonitorManager::class)->driver();
        $info = $driver->info($this->queue);
        $stats = $driver->stats($this->queue);

        $connection = app('config')->get('queue.default', 'database');

        return [
            Stat::make('Pending', $stats->pending)
                ->description('Awaiting processing')
                ->icon('heroicon-o-clock')
                ->color($stats->pending > 0 ? 'warning' : 'success'),
            Stat::make('Processing', $stats->processing)
                ->description('Currently being worked on')
                ->icon('heroicon-o-arrow-path')
                ->color($stats->processing > 0 ? 'info' : 'success'),
            Stat::make('Delayed', $stats->delayed)
                ->description('Scheduled for later')
                ->icon('heroicon-o-calendar')
                ->color($stats->delayed > 0 ? 'warning' : 'success'),
            Stat::make('Failed', $stats->failed)
                ->description('Failed jobs')
                ->icon('heroicon-o-exclamation-triangle')
                ->color($stats->failed > 0 ? 'danger' : 'success'),
        ];
    }

    public function getInfo()
    {
        $driver = app(QueueMonitorManager::class)->driver();

        return $driver->info($this->queue);
    }

    public function getViewData(): array
    {
        return array_merge(parent::getViewData(), [
            'queueInfo' => $this->getInfo(),
            'refreshInterval' => config('filament-queue-monitor.refresh_interval', 10),
        ]);
    }

    public static function canAccess(): bool
    {
        if (! config('filament-queue-monitor.enabled', true)) {
            return false;
        }

        $authorize = config('filament-queue-monitor.authorize');

        if ($authorize instanceof \Closure) {
            return (bool) $authorize(app('auth')->user());
        }

        return true;
    }
}
