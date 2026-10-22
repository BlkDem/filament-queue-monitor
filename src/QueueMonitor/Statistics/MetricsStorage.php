<?php

namespace BlkDem\FilamentQueueMonitor\QueueMonitor\Statistics;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MetricsStorage
{
    protected string $table;

    protected ?bool $cachedTableExists = null;

    protected ?float $tableCheckedAt = null;

    protected ?bool $cachedJobColumnExists = null;

    protected ?float $columnCheckedAt = null;

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

    /**
     * Inclusive lower bound of a reporting window, so a period means the same
     * thing to the counters, the breakdown and the completed jobs page.
     */
    public function periodStart(string $period = 'today'): string
    {
        $period = in_array($period, ['hour', '24h', '7d'], true) ? $period : 'today';

        return match ($period) {
            'hour' => now()->subHour()->format('Y-m-d H:i:s'),
            '24h' => now()->subHours(24)->format('Y-m-d H:i:s'),
            '7d' => now()->subDays(7)->format('Y-m-d H:i:s'),
            default => today()->toDateString(),
        };
    }

    public function tableExists(): bool
    {
        // These are singletons, so in a queue worker the answer lives as long
        // as the worker. A table that exists stays cached; a missing one is
        // asked again after a minute, so a migration run after the workers
        // started is picked up without restarting them.
        if ($this->cachedTableExists === false && $this->tableCheckedAt !== null
            && microtime(true) - $this->tableCheckedAt >= 60) {
            $this->cachedTableExists = null;
        }

        if ($this->cachedTableExists === null) {
            $this->cachedTableExists = Schema::hasTable($this->table);
            $this->tableCheckedAt = microtime(true);
        }

        return $this->cachedTableExists;
    }

    public function jobColumnExists(): bool
    {
        if (! $this->tableExists()) {
            return false;
        }

        // Same lifetime rule as the table: the job column arrives with a later
        // migration that also swaps the unique index the upsert relies on.
        if ($this->cachedJobColumnExists === false && $this->columnCheckedAt !== null
            && microtime(true) - $this->columnCheckedAt >= 60) {
            $this->cachedJobColumnExists = null;
        }

        if ($this->cachedJobColumnExists === null) {
            $this->cachedJobColumnExists = Schema::hasColumn($this->table, 'job');
            $this->columnCheckedAt = microtime(true);
        }

        return $this->cachedJobColumnExists;
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

    public function getAggregatedStats(string $period = 'today', ?string $connection = null): array
    {
        if (! $this->isPackageEnabled() || ! $this->isEnabled() || ! $this->tableExists()) {
            return [
                'processed' => 0,
                'failed' => 0,
            ];
        }

        $query = DB::table($this->table);

        if ($connection !== null && $connection !== '') {
            $query->where('connection', $connection);
        }

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

        if ($retentionDays === 0) {
            return 0;
        }

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
        $connection = DB::connection();
        $driver = $connection->getDriverName();

        // MySQL with use_upsert_alias inserts "as laravel_upsert_alias", which
        // makes every bare column name ambiguous; the update failed outright.
        $source = match (true) {
            in_array($driver, ['pgsql', 'sqlite'], true) => 'excluded.',
            $driver === 'sqlsrv' => 'laravel_source.',
            (bool) $connection->getConfig('use_upsert_alias') => 'laravel_upsert_alias.',
            default => 'VALUES(',
        };

        $value = fn (string $column): string => $source === 'VALUES('
            ? "VALUES({$column})"
            : "{$source}{$column}";

        // The existing row, named by its table so it never collides with the
        // incoming row's alias.
        $table = $connection->getQueryGrammar()->wrapTable($this->table);
        $current = fn (string $column): string => "{$table}.{$column}";

        $incomingProcessed = $value('processed');
        $incomingFailed = $value('failed');
        $incomingAvg = $value('avg_runtime');
        $incomingMax = $value('max_runtime');
        $currentAvg = $current('avg_runtime');
        $currentMax = $current('max_runtime');
        $existingCount = "({$current('processed')} + {$current('failed')})";
        $incomingCount = "({$incomingProcessed} + {$incomingFailed})";
        $knownIncomingCount = "CASE WHEN {$incomingAvg} IS NULL THEN 0 ELSE {$incomingCount} END";

        // processed and failed come last on purpose. MySQL and MariaDB apply
        // ON DUPLICATE KEY UPDATE assignments left to right, so a column
        // assigned earlier is already the new value when a later expression
        // reads it. The average reads the existing counts, so it has to be
        // assigned before they change: with the counts first, two runs of 10s
        // and 20s averaged to 13.3s instead of 15s. Postgres, SQLite and SQL
        // Server read the old row throughout, so the order is harmless there.
        return [
            'avg_runtime' => DB::raw(
                "CASE ".
                "WHEN {$incomingAvg} IS NULL THEN {$currentAvg} ".
                "WHEN {$currentAvg} IS NULL THEN {$incomingAvg} ".
                "WHEN ({$existingCount} + {$knownIncomingCount}) = 0 THEN {$currentAvg} ".
                "ELSE ({$currentAvg} * {$existingCount} + {$incomingAvg} * {$knownIncomingCount}) / ({$existingCount} + {$knownIncomingCount}) ".
                "END"
            ),
            'max_runtime' => DB::raw(
                "CASE ".
                "WHEN {$incomingMax} IS NULL THEN {$currentMax} ".
                "WHEN {$currentMax} IS NULL THEN {$incomingMax} ".
                "WHEN {$incomingMax} > {$currentMax} THEN {$incomingMax} ".
                "ELSE {$currentMax} END"
            ),
            'processed' => DB::raw("{$current('processed')} + {$incomingProcessed}"),
            'failed' => DB::raw("{$current('failed')} + {$incomingFailed}"),
            'updated_at' => now(),
        ];
    }
}
