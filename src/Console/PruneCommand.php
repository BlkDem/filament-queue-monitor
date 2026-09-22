<?php

namespace Kilo\FilamentQueueMonitor\Console;

use Illuminate\Console\Command;
use Kilo\FilamentQueueMonitor\QueueMonitor\Statistics\MetricsStorage;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'queue-monitor:prune')]
class PruneCommand extends Command
{
    protected $signature = 'queue-monitor:prune';
    protected $description = 'Delete old queue monitor metrics based on retention config';

    public function handle(MetricsStorage $storage): int
    {
        $retentionDays = config('filament-queue-monitor.metrics.retention_days', 30);
        $count = $storage->prune($retentionDays);

        $this->info("Deleted {$count} old metric records (older than {$retentionDays} days).");

        return static::SUCCESS;
    }
}
