<?php

namespace BlkDem\FilamentQueueMonitor\Console;

use Illuminate\Console\Command;
use BlkDem\FilamentQueueMonitor\QueueMonitor\Statistics\CompletedJobsStorage;
use BlkDem\FilamentQueueMonitor\QueueMonitor\Statistics\MetricsStorage;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'queue-monitor:prune')]
class PruneCommand extends Command
{
    protected $signature = 'queue-monitor:prune';
    protected $description = 'Delete old queue monitor metrics and completed job records based on retention config';

    public function handle(MetricsStorage $storage, CompletedJobsStorage $completed): int
    {
        $retentionDays = config('filament-queue-monitor.metrics.retention_days', 30);

        $metrics = $storage->prune($retentionDays);
        $runs = $completed->prune($retentionDays);

        $this->info("Deleted {$metrics} old metric records (older than {$retentionDays} days).");
        $this->info("Deleted {$runs} old completed job records (older than {$retentionDays} days).");

        return static::SUCCESS;
    }
}
