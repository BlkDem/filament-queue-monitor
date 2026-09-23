<?php

namespace Kilo\FilamentQueueMonitor\QueueMonitor\Statistics;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MetricsStorage
{
    protected string $table;

    protected ?bool $cachedTableExists = null;

    protected ?bool $cachedJobColumnExists = null;

    public function __construct()
    {
        $this->table = config('filament-queue-monitor.metrics.table', 'queue_monitor_metrics')
            ?: 'queue_monitor_metrics';
    }

    public function isEnabled(): bool
    {
        $enabled = config('filament-queue-monitor.metrics.enabled', true);

        if (is_string($enabled)) {
            return ! in_array(strtolower($enabled), ['0', 'false', 'no', 'off'], true);
        }

        return (bool) $enabled;
    }

    protected function isPackageEnabled(): bool
    {
        $enabled = config('filament-queue-monitor.enabled', true);

        if (is_string($enabled)) {
            return ! in_array(strtolower($enabled), ['0', 'false', 'no', 'off'], true);
        }

        return (bool) $enabled;
    }

    public function getTable(): string
    {
        return $this->table;
    }

    public function tableExists(): bool
    {
        return $this->cachedTableExists ??= Schema::hasTable($this->table);
    }

    public function jobColumnExists(): bool
    {
        if (! $this->tableExists()) {
            return false;
        }

        return $this->cachedJobColumnExists ??= Schema::hasColumn($this->table, 'job');
    }

    public function record(
        string $connection,
        string $queue,
        ?string $period = null,
        int $processed = 0,
        int $failed = 0,
        ?float $avgRuntime = null,
        ?float $maxRuntime = null,
        ?string $job = null,
    ): void {
        if (! $this->isPackageEnabled() || ! $this->isEnabled() || ! $this->tableExists()) {
            return;
        }

        $period ??= now()->format('Y-m-d H:i:s');
        $processed = max(0, $processed);
        $failed = max(0, $failed);
        $now = now();

        $data = [
            'connection' => $connection,
            'queue' => $queue,
            'period' => $period,
            'processed' => $processed,
            'failed' => $failed,
            'avg_runtime' => $avgRuntime,
            'max_runtime' => $maxRuntime,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $unique = ['connection', 'queue', 'period'];

        if ($this->jobColumnExists()) {
            $data['job'] = $job ?: 'unknown';
            $unique[] = 'job';
        }

        DB::table($this->table)->upsert($data, $unique, $this->upsertUpdates());
    }

    public function getMetrics(string $period = 'today'): array
    {
        if (! $this->isPackageEnabled() || ! $this->isEnabled() || ! $this->tableExists()) {
            return [];
        }

        $query = DB::table($this->table);

        $this->applyPeriodFilter($query, $this->normalizePeriod($period));

        return $query->orderBy('period', 'desc')->get()->all();
    }

    public function getAggregatedStats(string $period = 'today'): array
    {
        if (! $this->isPackageEnabled() || ! $this->isEnabled() || ! $this->tableExists()) {
            return [
                'processed' => 0,
                'failed' => 0,
            ];
        }

        $query = DB::table($this->table);

        $this->applyPeriodFilter($query, $this->normalizePeriod($period));

        $result = $query->selectRaw('SUM(processed) as processed, SUM(failed) as failed')->first();

        return [
            'processed' => (int) ($result->processed ?? 0),
            'failed' => (int) ($result->failed ?? 0),
        ];
    }

    public function getJobBreakdown(string $period = 'today'): array
    {
        if (! $this->isPackageEnabled() || ! $this->isEnabled() || ! $this->tableExists()) {
            return [];
        }

        $query = DB::table($this->table);

        $this->applyPeriodFilter($query, $this->normalizePeriod($period));

        $jobSelect = $this->jobColumnExists()
            ? 'COALESCE(NULLIF(job, \'\'), \'unknown\') as job'
            : '\'unknown\' as job';

        $groupBy = $this->jobColumnExists()
            ? ['connection', 'queue', 'job']
            : ['connection', 'queue'];

        return $query
            ->selectRaw("connection, queue, {$jobSelect}, SUM(processed) as processed, SUM(failed) as failed, AVG(avg_runtime) as avg_runtime, MAX(max_runtime) as max_runtime")
            ->groupBy($groupBy)
            ->orderByRaw('SUM(processed) DESC, SUM(failed) DESC')
            ->get()
            ->all();
    }

    public function prune(?int $retentionDays = null): int
    {
        if (! $this->tableExists()) {
            return 0;
        }

        $retentionDays = max(0, $retentionDays ?? config('filament-queue-monitor.metrics.retention_days', 30));
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

    protected function normalizePeriod(string $period): string
    {
        return in_array($period, ['hour', 'today', '24h', '7d'], true)
            ? $period
            : 'today';
    }

    protected function upsertUpdates(): array
    {
        $driver = DB::connection()->getDriverName();
        $source = match ($driver) {
            'pgsql', 'sqlite' => 'excluded.',
            'sqlsrv' => 'laravel_source.',
            default => 'VALUES(',
        };

        $value = fn (string $column): string => $source === 'VALUES('
            ? "VALUES({$column})"
            : "{$source}{$column}";

        $incomingProcessed = $value('processed');
        $incomingFailed = $value('failed');
        $incomingAvg = $value('avg_runtime');
        $incomingMax = $value('max_runtime');
        $existingCount = '(processed + failed)';
        $incomingCount = "({$incomingProcessed} + {$incomingFailed})";
        $knownIncomingCount = "CASE WHEN {$incomingAvg} IS NULL THEN 0 ELSE {$incomingCount} END";

        return [
            'processed' => DB::raw("processed + {$incomingProcessed}"),
            'failed' => DB::raw("failed + {$incomingFailed}"),
            'avg_runtime' => DB::raw(
                "CASE ".
                "WHEN {$incomingAvg} IS NULL THEN avg_runtime ".
                "WHEN avg_runtime IS NULL THEN {$incomingAvg} ".
                "WHEN ({$existingCount} + {$knownIncomingCount}) = 0 THEN avg_runtime ".
                "ELSE (avg_runtime * {$existingCount} + {$incomingAvg} * {$knownIncomingCount}) / ({$existingCount} + {$knownIncomingCount}) ".
                "END"
            ),
            'max_runtime' => DB::raw(
                "CASE ".
                "WHEN {$incomingMax} IS NULL THEN max_runtime ".
                "WHEN max_runtime IS NULL THEN {$incomingMax} ".
                "WHEN {$incomingMax} > max_runtime THEN {$incomingMax} ".
                "ELSE max_runtime END"
            ),
            'updated_at' => now(),
        ];
    }
}
