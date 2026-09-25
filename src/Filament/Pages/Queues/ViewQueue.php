<?php

namespace BlkDem\FilamentQueueMonitor\Filament\Pages\Queues;

use Filament\Pages\Page;
use Filament\Widgets\StatsOverviewWidget\Stat;
use BlkDem\FilamentQueueMonitor\QueueMonitor\DTO\QueueInfo;
use BlkDem\FilamentQueueMonitor\QueueMonitor\QueueMonitorManager;
use BlkDem\FilamentQueueMonitor\Support\Access;
use BlkDem\FilamentQueueMonitor\Support\Trans;

class ViewQueue extends Page
{
    public function getView(): string
    {
        return 'filament-queue-monitor::pages.view-queue';
    }

    public static function getNavigationGroup(): ?string
    {
        return Trans::navigationGroup();
    }

    public static function getNavigationLabel(): string
    {
        return Trans::get('navigation.queue_details');
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
            Stat::make(Trans::get('stats.pending'), $info->pending)
                ->description(Trans::get('stats.description.awaiting_processing'))
                ->icon('heroicon-o-clock')
                ->color($info->pending > 0 ? 'warning' : 'success'),
            Stat::make(Trans::get('stats.processing'), $info->processing)
                ->description(Trans::get('stats.description.currently_working'))
                ->icon('heroicon-o-arrow-path')
                ->color($info->processing > 0 ? 'info' : 'success'),
            Stat::make(Trans::get('stats.delayed'), $info->delayed)
                ->description(Trans::get('stats.description.scheduled_later'))
                ->icon('heroicon-o-calendar')
                ->color($info->delayed > 0 ? 'warning' : 'success'),
            Stat::make(Trans::get('stats.failed'), $info->failed)
                ->description(Trans::get('stats.description.failed_count'))
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
