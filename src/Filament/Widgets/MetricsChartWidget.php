<?php

namespace Kilo\FilamentQueueMonitor\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Kilo\FilamentQueueMonitor\QueueMonitor\Statistics\MetricsStorage;

class MetricsChartWidget extends ChartWidget
{
    protected static bool $isLazy = false;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $pollingInterval = null;

    protected function getPollingInterval(): ?string
    {
        $interval = (int) config('filament-queue-monitor.refresh_interval', 10);

        if ($interval <= 0) {
            return null;
        }

        return "{$interval}s";
    }

    protected function getHeading(): ?string
    {
        return 'Queue Activity';
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

        $metrics = $storage->getMetrics('7d');

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
