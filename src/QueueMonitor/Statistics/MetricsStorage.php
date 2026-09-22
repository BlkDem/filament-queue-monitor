<?php

namespace Kilo\FilamentQueueMonitor\QueueMonitor\Statistics;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Kilo\FilamentQueueMonitor\QueueMonitor\DTO\Metric;
use Illuminate\Support\Carbon;

class MetricsStorage
{
    protected string $table;

    public function __construct()
    {
        $this->table = config('filament-queue-monitor.metrics.table', 'queue_monitor_metrics');
    }

    public function isEnabled(): bool
    {
        return (bool) config('filament-queue-monitor.metrics.enabled', true);
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function tableExists(): bool
    {
        return Schema::hasTable($this->table);
    }

    public function record(string $connection, string $queue, ?string $period = null, int $processed = 0, int $failed = 0, ?float $avgRuntime = null, ?float $maxRuntime = null): void
    {
        if (! $this->isEnabled() || ! $this->tableExists()) {
            return;
        }

        $period ??= now()->format('Y-m-d H:i:s');

        $existing = DB::table($this->table)
            ->where('connection', $connection)
            ->where('queue', $queue)
            ->where('period', $period)
            ->first();

        if ($existing) {
            DB::table($this->table)
                ->where('id', $existing->id)
                ->update([
                    'processed' => $existing->processed + $processed,
                    'failed' => $existing->failed + $failed,
                    'avg_runtime' => $avgRuntime ?? $existing->avg_runtime,
                    'max_runtime' => $maxRuntime ?? $existing->max_runtime,
                    'updated_at' => now(),
                ]);
        } else {
            DB::table($this->table)->insert([
                'connection' => $connection,
                'queue' => $queue,
                'period' => $period,
                'processed' => $processed,
                'failed' => $failed,
                'avg_runtime' => $avgRuntime,
                'max_runtime' => $maxRuntime,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function getMetrics(string $period = 'today'): array
    {
        if (! $this->isEnabled() || ! $this->tableExists()) {
            return [];
        }

        $query = DB::table($this->table);

        switch ($period) {
            case 'hour':
                $query->where('period', '>=', now()->subHour()->format('Y-m-d H:i:s'));
                break;
            case 'today':
                $query->whereDate('period', today());
                break;
            case '24h':
                $query->where('period', '>=', now()->subHours(24)->format('Y-m-d H:i:s'));
                break;
            case '7d':
                $query->where('period', '>=', now()->subDays(7)->format('Y-m-d H:i:s'));
                break;
        }

        return $query->orderBy('period', 'desc')->get()->all();
    }

    public function getAggregatedStats(string $period = 'today'): array
    {
        if (! $this->isEnabled() || ! $this->tableExists()) {
            return [
                'processed' => 0,
                'failed' => 0,
            ];
        }

        $query = DB::table($this->table);

        $this->applyPeriodFilter($query, $period);

        $result = $query->selectRaw('SUM(processed) as processed, SUM(failed) as failed')->first();

        return [
            'processed' => (int) ($result->processed ?? 0),
            'failed' => (int) ($result->failed ?? 0),
        ];
    }

    public function prune(int $retentionDays = null): int
    {
        if (! $this->tableExists()) {
            return 0;
        }

        $retentionDays = $retentionDays ?? config('filament-queue-monitor.metrics.retention_days', 30);

        $cutoff = now()->subDays($retentionDays)->startOfDay()->format('Y-m-d H:i:s');

        return DB::table($this->table)
            ->where('period', '<', $cutoff)
            ->delete();
    }

    protected function applyPeriodFilter($query, string $period): void
    {
        switch ($period) {
            case 'hour':
                $query->where('period', '>=', now()->subHour()->format('Y-m-d H:i:s'));
                break;
            case 'today':
                $query->whereDate('period', today());
                break;
            case '24h':
                $query->where('period', '>=', now()->subHours(24)->format('Y-m-d H:i:s'));
                break;
            case '7d':
                $query->where('period', '>=', now()->subDays(7)->format('Y-m-d H:i:s'));
                break;
        }
    }
}
