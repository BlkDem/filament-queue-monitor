<?php

namespace BlkDem\FilamentQueueMonitor\QueueMonitor\Statistics;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-run record of a job that completed.
 *
 * The queue backend deletes a job as soon as it has run, so without this table
 * a finished run is unrecoverable and only the per-minute aggregates in
 * MetricsStorage survive. Retention reuses the metrics retention setting.
 */
class CompletedJobsStorage
{
    protected string $table;

    protected ?bool $cachedTableExists = null;

    public function __construct()
    {
        $this->table = config('filament-queue-monitor.metrics.table_completed_jobs', 'queue_monitor_completed_jobs')
            ?: 'queue_monitor_completed_jobs';
    }

    public function isEnabled(): bool
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

    public function record(
        string $connection,
        string $queue,
        string $job,
        ?string $uuid = null,
        ?float $runtime = null,
        ?Carbon $finishedAt = null,
    ): void {
        if (! $this->isEnabled() || ! $this->tableExists()) {
            return;
        }

        $now = now();

        DB::table($this->table)->insert([
            'connection' => $connection,
            'queue' => $queue !== '' ? $queue : 'default',
            'job' => $job !== '' ? $job : 'unknown',
            'uuid' => $uuid,
            'runtime' => $runtime,
            'finished_at' => $finishedAt?->toDateTimeString() ?? $now->toDateTimeString(),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
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

        return DB::table($this->table)
            ->where('finished_at', '<', now()->subDays($retentionDays))
            ->delete();
    }
}
