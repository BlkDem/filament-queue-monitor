<?php

namespace Kilo\FilamentQueueMonitor\Tests\Unit;

use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Jobs\Job;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Kilo\FilamentQueueMonitor\QueueMonitor\Listeners\RecordQueueMetrics;
use Kilo\FilamentQueueMonitor\QueueMonitor\Statistics\MetricsStorage;

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

    it('aggregates stats across time periods', function () {
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
    });
});
