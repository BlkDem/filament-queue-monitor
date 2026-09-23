<?php

namespace Kilo\FilamentQueueMonitor\Filament\Pages\Queues;

use Filament\Pages\Page;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Kilo\FilamentQueueMonitor\QueueMonitor\DTO\QueueInfo;
use Kilo\FilamentQueueMonitor\QueueMonitor\QueueMonitorManager;
use Kilo\FilamentQueueMonitor\Support\Access;

class ViewQueue extends Page
{
    public function getView(): string
    {
        return 'filament-queue-monitor::pages.view-queue';
    }

    public static function getNavigationGroup(): ?string
    {
        $group = config('filament-queue-monitor.navigation.group', 'Queue Monitor');

        return is_string($group) && $group !== '' ? $group : 'Queue Monitor';
    }

    public static function getNavigationLabel(): string
    {
        return 'Queue Details';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    protected static ?string $slug = 'queue-monitor/queues/{queue}';

    public string $queue;

    protected ?QueueInfo $cachedInfo = null;

    public function mount(string $queue): void
    {
        $this->queue = $queue;
    }

    public function getStats(): array
    {
        $info = $this->getInfo();

        return [
            Stat::make('Pending', $info->pending)
                ->description('Awaiting processing')
                ->icon('heroicon-o-clock')
                ->color($info->pending > 0 ? 'warning' : 'success'),
            Stat::make('Processing', $info->processing)
                ->description('Currently being worked on')
                ->icon('heroicon-o-arrow-path')
                ->color($info->processing > 0 ? 'info' : 'success'),
            Stat::make('Delayed', $info->delayed)
                ->description('Scheduled for later')
                ->icon('heroicon-o-calendar')
                ->color($info->delayed > 0 ? 'warning' : 'success'),
            Stat::make('Failed', $info->failed)
                ->description('Failed jobs')
                ->icon('heroicon-o-exclamation-triangle')
                ->color($info->failed > 0 ? 'danger' : 'success'),
        ];
    }

    public function getInfo(): QueueInfo
    {
        if ($this->cachedInfo !== null) {
            return $this->cachedInfo;
        }

        $driver = app(QueueMonitorManager::class)->driver();

        return $this->cachedInfo = $driver->info($this->queue);
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
        return Access::canAccess();
    }
}
