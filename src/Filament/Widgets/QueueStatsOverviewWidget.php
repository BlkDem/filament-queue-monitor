<?php

namespace BlkDem\FilamentQueueMonitor\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use BlkDem\FilamentQueueMonitor\QueueMonitor\QueueMonitorManager;
use BlkDem\FilamentQueueMonitor\QueueMonitor\Models\QueueJob;
use BlkDem\FilamentQueueMonitor\Support\Trans;

class QueueStatsOverviewWidget extends BaseWidget
{
    protected static bool $isLazy = false;

    protected int | string | array $columnSpan = 'full';

    public ?string $pollingOverride = null;

    protected $listeners = ['queueActivityPollingIntervalChanged' => 'setPollingInterval'];

    public function setPollingInterval(string $interval): void
    {
        $this->pollingOverride = $interval;
    }

    public function getColumns(): int
    {
        return 4;
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
        $manager = app(QueueMonitorManager::class);
        $driver = $manager->driver();

        $driverQueues = $driver->getQueues();

        $allStats = [
            'queues' => 0,
            'pending' => 0,
            'processing' => 0,
            'failed' => 0,
        ];

        foreach ($driverQueues as $queueInfo) {
            $allStats['queues']++;
            $allStats['pending'] += $queueInfo->pending;
            $allStats['processing'] += $queueInfo->processing;
            $allStats['failed'] += $queueInfo->failed;
        }

        // Get total configured queues from Laravel queue config
        $defaultConnection = config('queue.default', 'database');
        $queueConfig = config("queue.connections.{$defaultConnection}.queue", 'default');
        $totalConfiguredQueues = is_array($queueConfig) ? count($queueConfig) : 1;

        // A redis queue connection declares a single queue name, which says
        // nothing about how many are in use. Fall back to the allowlist when
        // it is set, otherwise report what the driver actually found.
        if (config('filament-queue-monitor.driver') === 'redis') {
            $allowlist = config('filament-queue-monitor.redis.queues', []);

            if (is_string($allowlist)) {
                $allowlist = array_filter(array_map('trim', explode(',', $allowlist)));
            }

            $totalConfiguredQueues = count($allowlist) > 0
                ? count($allowlist)
                : $allStats['queues'];
        }

        // Active queues come from the driver being monitored, so the stat
        // agrees with the per-queue tables below it. The database driver keeps
        // using the same source as QueueActivityWidget.
        $activeQueuesCount = config('filament-queue-monitor.driver') === 'redis'
            ? $allStats['queues']
            : QueueJob::query()
                ->where(function ($query) {
                    $query->whereNull('reserved_at')
                        ->where('available_at', '<=', now()->timestamp)
                        ->orWhereNotNull('reserved_at');
                })
                ->distinct('queue')
                ->count('queue');

        $thresholdHours = config('filament-queue-monitor.stuck_jobs.threshold_hours', 12);
        $stuckCount = $driver->stuckJobsCount($thresholdHours);

        return [
            Stat::make(Trans::get('stats.queues'), $activeQueuesCount)
                ->description(Trans::get('stats.description.total_queues', ['count' => $totalConfiguredQueues]))
                ->icon('heroicon-o-queue-list')
                ->color('gray'),
            Stat::make(Trans::get('stats.pending'), $allStats['pending'])
                ->description(Trans::get('stats.description.processing_count', ['count' => $allStats['processing']]))
                ->icon('heroicon-o-clock')
                ->color($allStats['pending'] > 0 ? 'warning' : 'success'),
            Stat::make(Trans::get('stats.processing'), $allStats['processing'])
                ->icon('heroicon-o-arrow-path')
                ->color($allStats['processing'] > 0 ? 'info' : 'success'),
            Stat::make(Trans::get('stats.stuck_jobs'), $stuckCount)
                ->description(Trans::get('stats.description.stuck_jobs', ['hours' => $thresholdHours]))
                ->icon('heroicon-o-exclamation-triangle')
                ->color($stuckCount > 0 ? 'danger' : 'success'),
        ];
    }
}