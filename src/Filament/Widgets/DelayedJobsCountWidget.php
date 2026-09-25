<?php

namespace BlkDem\FilamentQueueMonitor\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use BlkDem\FilamentQueueMonitor\QueueMonitor\QueueMonitorManager;

class DelayedJobsCountWidget extends BaseWidget
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
        $driver = app(QueueMonitorManager::class)->driver();
        $queues = $driver->getQueues();

        $totalDelayed = 0;
        foreach ($queues as $queueInfo) {
            $totalDelayed += $queueInfo->delayed;
        }

        return [
            Stat::make('Delayed Jobs', $totalDelayed)
                ->description('Jobs waiting to be processed')
                ->descriptionIcon('heroicon-o-clock')
                ->color('warning')
                ->chart($this->getChartData()),
        ];
    }

    protected function getChartData(): array
    {
        $driver = app(QueueMonitorManager::class)->driver();
        $queues = $driver->getQueues();

        $data = [];
        for ($i = 59; $i >= 0; $i--) {
            $data[] = 0;
        }

        return $data;
    }
}