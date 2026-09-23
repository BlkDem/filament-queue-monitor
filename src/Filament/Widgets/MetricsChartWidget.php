<?php

namespace Kilo\FilamentQueueMonitor\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;
use Kilo\FilamentQueueMonitor\QueueMonitor\Statistics\MetricsStorage;

class MetricsChartWidget extends ChartWidget
{
    protected static bool $isLazy = false;

    protected int | string | array $columnSpan = 'full';

    protected function getPollingInterval(): ?string
    {
        $interval = (int) config('filament-queue-monitor.refresh_interval', 10);

        if ($interval <= 0) {
            return null;
        }

        return "{$interval}s";
    }

    public function getHeading(): string | Htmlable | null
    {
        return 'Queue Activity';
    }

    public string $selectedPeriod = 'today';

    protected $listeners = ['refreshDashboard' => 'refreshDashboard'];

    public function refreshDashboard(string $period): void
    {
        $this->selectedPeriod = in_array($period, ['hour', 'today', '24h', '7d'], true)
            ? $period
            : 'today';
    }

    protected function getData(): array
    {
        $storage = app(MetricsStorage::class);

        if (! $storage->isEnabled()) {
            return [
                'labels' => [],
                'datasets' => [],
            ];
        }

        $period = in_array($this->selectedPeriod, ['hour', 'today', '24h', '7d'], true)
            ? $this->selectedPeriod
            : 'today';
        $metrics = $storage->getMetrics($period);

        $labels = [];
        $processed = [];
        $failed = [];

        foreach ($metrics as $metric) {
            $labels[] = $metric->period;
            $processed[] = $metric->processed;
            $failed[] = $metric->failed;
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'label' => 'Processed',
                    'data' => $processed,
                    'borderColor' => '#10B981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                ],
                [
                    'label' => 'Failed',
                    'data' => $failed,
                    'borderColor' => '#EF4444',
                    'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
