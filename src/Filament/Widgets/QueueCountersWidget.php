<?php

namespace BlkDem\FilamentQueueMonitor\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use BlkDem\FilamentQueueMonitor\QueueMonitor\QueueMonitorManager;
use BlkDem\FilamentQueueMonitor\QueueMonitor\Statistics\MetricsStorage;
use BlkDem\FilamentQueueMonitor\Support\Trans;
use Carbon\Carbon;

class QueueCountersWidget extends BaseWidget
{
    protected static bool $isLazy = false;

    protected int | string | array $columnSpan = 'full';

    protected $listeners = ['queueActivityPollingIntervalChanged' => 'setPollingInterval'];

    public ?string $pollingOverride = null;

    public function setPollingInterval(string $interval): void
    {
        $this->pollingOverride = $interval;
    }

    public function getColumns(): int
    {
        return 3;
    }

    protected function getPollingInterval(): ?string
    {
        if ($this->pollingOverride === 'off') {
            return null;
        }

        if (filled($this->pollingOverride)) {
            return $this->pollingOverride;
        }

        $interval = config('filament-queue-monitor.refresh_interval', 10);

        if ($interval <= 0) {
            return null;
        }

        return "{$interval}s";
    }

    protected function getStats(): array
    {
        $driver = app(QueueMonitorManager::class)->driver();
        $queues = $driver->getQueues();

        $totalDelayed = 0;
        $totalFailed = 0;
        foreach ($queues as $queueInfo) {
            $totalDelayed += $queueInfo->delayed;
            $totalFailed += $queueInfo->failed;
        }

        $metricsStorage = app(MetricsStorage::class);
        $stats = $metricsStorage->getAggregatedStats('hour');
        $totalProcessed = $stats['processed'];

        return [
            Stat::make(Trans::get('stats.delayed_jobs'), $totalDelayed)
                ->description(Trans::get('stats.description.awaiting_delayed'))
                ->descriptionIcon('heroicon-o-clock')
                ->color('warning')
                ->chart([$totalDelayed]),
            Stat::make(Trans::get('stats.failed_jobs'), $totalFailed)
                ->description(Trans::get('stats.description.has_failed'))
                ->descriptionIcon('heroicon-o-exclamation-triangle')
                ->color('danger')
                ->chart([$totalFailed]),
            Stat::make(Trans::get('stats.processed_last_hour'), $totalProcessed)
                ->description(Trans::get('stats.description.processed_last_hour'))
                ->descriptionIcon('heroicon-o-check-circle')
                ->color('success')
                ->chart([$totalProcessed]),
        ];
    }
}