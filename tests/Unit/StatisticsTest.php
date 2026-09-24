<?php

namespace BlkDem\FilamentQueueMonitor\Tests\Unit;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Jobs\Job;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use BlkDem\FilamentQueueMonitor\QueueMonitor\Listeners\RecordQueueMetrics;
use BlkDem\FilamentQueueMonitor\QueueMonitor\Statistics\MetricsStorage;

describe('Statistics', function () {
    beforeEach(function () {
        Cache::flush();
    });

    it('records metrics on JobProcessed event', function () {
        $storage = app(MetricsStorage::class);
        $listener = new RecordQueueMetrics($storage);

        $job = \Mockery::mock(Job::class);
        $job->shouldReceive('getQueue')->andReturn('default');
        $job->shouldReceive('payload')->andReturn(json_encode(['data' => []]));
        $job->shouldReceive('getRawBody')->andReturn(json_encode(['data' => []]));

        $processingEvent = new JobProcessing('database', $job);
        $listener->handleProcessing($processingEvent);

        $processedEvent = new JobProcessed('database', $job);
        $listener->handleProcessed($processedEvent);

        $metrics = $storage->getAggregatedStats('today');

        expect($metrics['processed'])->toBe(1);
    });

    it('records metrics on JobFailed event', function () {
        $storage = app(MetricsStorage::class);
        $listener = new RecordQueueMetrics($storage);

        $job = \Mockery::mock(Job::class);
        $job->shouldReceive('getQueue')->andReturn('default');
        $job->shouldReceive('payload')->andReturn(json_encode(['data' => []]));

        $exception = new \Exception('Test exception');
        $event = new JobFailed('database', $job, $exception, 'uuid-123');
        $listener->handleFailed($event);

        $metrics = $storage->getAggregatedStats('today');

        expect($metrics['failed'])->toBe(1);
    });

    it('aggregates multiple jobs in same minute', function () {
        $storage = app(MetricsStorage::class);
        $listener = new RecordQueueMetrics($storage);

        $job = \Mockery::mock(Job::class);
        $job->shouldReceive('getQueue')->andReturn('default');
        $job->shouldReceive('payload')->andReturn(json_encode(['data' => []]));
        $job->shouldReceive('getRawBody')->andReturn(json_encode(['data' => []]));

        $event = new JobProcessing('database', $job);
        $listener->handleProcessing($event);
        $eventProcessed = new JobProcessed('database', $job);
        $listener->handleProcessed($eventProcessed);

        $event2 = new JobProcessing('database', $job);
        $listener->handleProcessing($event2);
        $eventProcessed2 = new JobProcessed('database', $job);
        $listener->handleProcessed($eventProcessed2);

        $metrics = $storage->getAggregatedStats('today');

        expect($metrics['processed'])->toBe(2);
    });

    it('calculates runtime duration', function () {
        $storage = app(MetricsStorage::class);
        $listener = new RecordQueueMetrics($storage);

        $job = \Mockery::mock(Job::class);
        $job->shouldReceive('getQueue')->andReturn('default');
        $job->shouldReceive('payload')->andReturn(json_encode(['data' => []]));
        $job->shouldReceive('getRawBody')->andReturn(json_encode(['data' => []]));

        $processingEvent = new JobProcessing('database', $job);
        $listener->handleProcessing($processingEvent);

        usleep(10000);

        $processedEvent = new JobProcessed('database', $job);
        $listener->handleProcessed($processedEvent);

        $metrics = $storage->getMetrics('today');

        expect($metrics)->not->toBeEmpty();
        $metric = $metrics[0];
        expect($metric->avg_runtime)->toBeGreaterThan(0)
            ->and($metric->processed)->toBe(1);
    });

    it('prunes old metrics', function () {
        $storage = app(MetricsStorage::class);
        $oldPeriod = now()->subDays(40)->startOfMinute()->format('Y-m-d H:i:s');
        $storage->record('database', 'default', $oldPeriod, 10, 2);
        $recentPeriod = now()->startOfMinute()->format('Y-m-d H:i:s');
        $storage->record('database', 'default', $recentPeriod, 5, 1);

        $deleted = $storage->prune(30);

        expect($deleted)->toBe(1);

        $remaining = DB::table('queue_monitor_metrics')->count();
        expect($remaining)->toBe(1);
    });

    it('stores metrics with correct fields', function () {
        $storage = app(MetricsStorage::class);
        $storage->record('redis', 'emails', now()->format('Y-m-d H:i:s'), 5, 1, 0.5, 2.0);

        $metric = DB::table('queue_monitor_metrics')
            ->where('connection', 'redis')
            ->where('queue', 'emails')
            ->first();

        expect($metric->connection)->toBe('redis')
            ->and($metric->queue)->toBe('emails')
            ->and($metric->processed)->toBe(5)
            ->and($metric->failed)->toBe(1)
            ->and($metric->avg_runtime)->toBe(0.5);
    });

    it('records the job name on JobProcessed', function () {
        $storage = app(MetricsStorage::class);
        $listener = new RecordQueueMetrics($storage);

        $job = \Mockery::mock(Job::class);
        $job->shouldReceive('getQueue')->andReturn('default');
        $job->shouldReceive('resolveName')->andReturn('App\\Jobs\\ProcessOrder');
        $job->shouldReceive('payload')->andReturn(json_encode(['displayName' => 'App\\Jobs\\ProcessOrder']));

        $listener->handleProcessing(new JobProcessing('database', $job));
        $listener->handleProcessed(new JobProcessed('database', $job));

        $metric = DB::table('queue_monitor_metrics')->where('queue', 'default')->first();

        expect($metric->job)->toBe('App\\Jobs\\ProcessOrder');
    });

    it('falls back to unknown job name', function () {
        $storage = app(MetricsStorage::class);
        $listener = new RecordQueueMetrics($storage);

        $job = \Mockery::mock(Job::class);
        $job->shouldReceive('getQueue')->andReturn('default');
        $job->shouldReceive('payload')->andReturn(json_encode(['data' => []]));

        $listener->handleProcessing(new JobProcessing('database', $job));
        $listener->handleProcessed(new JobProcessed('database', $job));

        $metric = DB::table('queue_monitor_metrics')->where('queue', 'default')->first();

        expect($metric->job)->toBe('unknown');
    });

    it('defaults the job name to unknown on direct record', function () {
        $storage = app(MetricsStorage::class);
        $storage->record('database', 'default', now()->format('Y-m-d H:i:s'), 1, 0);

        $metric = DB::table('queue_monitor_metrics')->where('queue', 'default')->first();

        expect($metric->job)->toBe('unknown');
    });

    it('provides a job breakdown grouped by job, queue and connection', function () {
        $storage = app(MetricsStorage::class);
        $period = now()->startOfMinute()->format('Y-m-d H:i:s');

        $storage->record('database', 'emails', $period, 10, 2, 0.5, 1.0, 'App\\Jobs\\SendEmail');
        $storage->record('database', 'emails', $period, 5, 0, 0.6, 2.0, 'App\\Jobs\\SendEmail');
        $storage->record('database', 'default', $period, 1, 1, 2.0, 3.0, 'App\\Jobs\\ProcessOrder');
        $storage->record('redis', 'emails', $period, 3, 1, 1.0, 1.5, 'App\\Jobs\\SendEmail');

        $breakdown = $storage->getJobBreakdown('today');

        expect($breakdown)->toHaveCount(3);

        $dbEmails = collect($breakdown)->first(
            fn ($row) => $row->connection === 'database' && $row->queue === 'emails' && $row->job === 'App\\Jobs\\SendEmail'
        );

        expect((int) $dbEmails->processed)->toBe(15)
            ->and((int) $dbEmails->failed)->toBe(2)
            ->and((float) $dbEmails->avg_runtime)->toBeGreaterThan(0)
            ->and((float) $dbEmails->max_runtime)->toBe(2.0);

        $dbDefault = collect($breakdown)->first(
            fn ($row) => $row->connection === 'database' && $row->queue === 'default' && $row->job === 'App\\Jobs\\ProcessOrder'
        );

        expect((int) $dbDefault->processed)->toBe(1)
            ->and((int) $dbDefault->failed)->toBe(1);
    });

    it('aggregates runtime statistics across updates', function () {
        $storage = app(MetricsStorage::class);
        $period = now()->startOfMinute()->format('Y-m-d H:i:s');

        $storage->record('database', 'default', $period, 2, 0, 10.0, 12.0);
        $storage->record('database', 'default', $period, 1, 1, 20.0, 8.0);

        $metric = DB::table('queue_monitor_metrics')
            ->where('connection', 'database')
            ->where('queue', 'default')
            ->where('period', $period)
            ->first();

        expect($metric->processed)->toBe(3)
            ->and($metric->failed)->toBe(1)
            ->and($metric->avg_runtime)->toBe(15.0)
            ->and($metric->max_runtime)->toBe(12.0);
    });

    it('aggregates stats across time periods', function () {
        Carbon::setTestNow('2026-01-01 12:00:00');

        try {
            $storage = app(MetricsStorage::class);

            $oldPeriod = now()->subHours(2)->startOfMinute()->format('Y-m-d H:i:s');
            $recentPeriod = now()->startOfMinute()->format('Y-m-d H:i:s');
            $storage->record('database', 'default', $oldPeriod, 10, 1);
            $storage->record('database', 'default', $recentPeriod, 5, 0);

            $stats = $storage->getAggregatedStats('hour');
            expect($stats['processed'])->toBe(5)
                ->and($stats['failed'])->toBe(0);

            $stats = $storage->getAggregatedStats('today');
            expect($stats['processed'])->toBe(15)
                ->and($stats['failed'])->toBe(1);
        } finally {
            Carbon::setTestNow();
        }
    });
});
