<?php

namespace BlkDem\FilamentQueueMonitor\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use BlkDem\FilamentQueueMonitor\QueueMonitor\Statistics\MetricsStorage;

class JobsProcessedLastHourWidget extends BaseWidget
{
    protected static bool $isLazy = false;

    protected int | string | array $columnSpan = 1;

    protected $listeners = ['queueActivityPollingIntervalChanged' => 'setPollingInterval'];

    public ?string $pollingOverride = null;

    public function setPollingInterval(string $interval): void
    {
        $this->pollingOverride = $interval;
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
        $metricsStorage = app(MetricsStorage::class);
        $stats = $metricsStorage->getAggregatedStats('hour');

        $totalProcessed = $stats['processed'];

        return [
            Stat::make('Processed (Last Hour)', $totalProcessed)
                ->description('Jobs completed in the last hour')
                ->descriptionIcon('heroicon-o-check-circle')
                ->color('success')
                ->chart($this->getChartData()),
        ];
    }

    protected function getChartData(): array
    {
        $metricsStorage = app(MetricsStorage::class);
        $stats = $metricsStorage->getAggregatedStats('hour');

        return [$stats['processed']];
    }
}