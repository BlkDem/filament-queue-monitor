<?php

use BlkDem\FilamentQueueMonitor\Filament\Pages\FailedJobs\ListFailedJobs;
use BlkDem\FilamentQueueMonitor\Filament\Pages\Jobs\ListJobs;
use BlkDem\FilamentQueueMonitor\Filament\Widgets\JobBreakdownWidget;
use BlkDem\FilamentQueueMonitor\Filament\Widgets\QueueActivityWidget;
use BlkDem\FilamentQueueMonitor\Filament\Widgets\QueueCountersWidget;
use BlkDem\FilamentQueueMonitor\Filament\Widgets\QueueStatsOverviewWidget;
use BlkDem\FilamentQueueMonitor\QueueMonitor\Drivers\DatabaseQueueMonitorDriver;
use BlkDem\FilamentQueueMonitor\QueueMonitor\Drivers\RedisQueueMonitorDriver;
use BlkDem\FilamentQueueMonitor\QueueMonitor\Listeners\RecordQueueMetrics;
use BlkDem\FilamentQueueMonitor\QueueMonitor\Statistics\CompletedJobsStorage;
use BlkDem\FilamentQueueMonitor\QueueMonitor\Statistics\MetricsStorage;
use BlkDem\FilamentQueueMonitor\Support\Access;
use BlkDem\FilamentQueueMonitor\Support\MonitoredConnection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

class AuditFixesSucceedingJob implements \Illuminate\Contracts\Queue\ShouldQueue
{
    use \Illuminate\Bus\Queueable;
    use \Illuminate\Queue\InteractsWithQueue;

    public static int $runs = 0;

    public static bool $failedCalled = false;

    public function handle(): void
    {
        self::$runs++;
    }

    public function failed(): void
    {
        self::$failedCalled = true;
    }
}

class AuditFixesRetryableJob
{
    public $nested;

    public function retryUntil(): int
    {
        return 1999999999;
    }
}

class AuditFixesWakeupProbe
{
    public static bool $wokeUp = false;

    public function __wakeup(): void
    {
        self::$wokeUp = true;
    }
}

function auditQueueJob(string $uuid): \Illuminate\Queue\Jobs\Job
{
    return new class($uuid) extends \Illuminate\Queue\Jobs\Job
    {
        public function __construct(protected string $id)
        {
        }

        public function uuid(): ?string
        {
            return $this->id;
        }

        public function getJobId(): string
        {
            return $this->id;
        }

        public function getRawBody(): string
        {
            return '{}';
        }

        public function resolveName(): string
        {
            return 'App\Jobs\SendMail';
        }

        public function getQueue(): string
        {
            return 'default';
        }
    };
}

function auditJobRow(string $queue, int $i, ?int $reservedAt = null): array
{
    return [
        'queue' => $queue,
        'payload' => json_encode(['uuid' => "job-{$i}", 'displayName' => 'App\Jobs\Bulk', 'data' => []]),
        'attempts' => 0,
        'reserved_at' => $reservedAt,
        'available_at' => now()->timestamp - 10,
        'created_at' => now()->timestamp - 10,
    ];
}

describe('monitoring never changes the outcome of a job', function () {
    it('does not report a succeeded job as erroring when its metrics cannot be written', function () {
        AuditFixesSucceedingJob::$runs = 0;

        app()->instance(MetricsStorage::class, new class extends MetricsStorage
        {
            public function record(string $connection, string $queue, ?string $period = null, int $processed = 0, int $failed = 0, ?float $avgRuntime = null, ?float $maxRuntime = null, ?string $job = null): void
            {
                throw new RuntimeException('Deadlock found when trying to get lock');
            }
        });

        $errored = 0;
        $laterListenerRan = false;
        app('events')->listen(\Illuminate\Queue\Events\JobExceptionOccurred::class, function () use (&$errored) {
            $errored++;
        });
        app('events')->listen(\Illuminate\Queue\Events\JobProcessed::class, function () use (&$laterListenerRan) {
            $laterListenerRan = true;
        });

        Queue::connection('database')->push(new AuditFixesSucceedingJob);

        Artisan::call('queue:work', ['connection' => 'database', '--once' => true, '--tries' => 1]);

        expect(AuditFixesSucceedingJob::$runs)->toBe(1)
            ->and($errored)->toBe(0)
            ->and($laterListenerRan)->toBeTrue()
            ->and(DB::table('jobs')->count())->toBe(0);
    });

    it('runs the job when the schema check fails before it starts', function () {
        AuditFixesSucceedingJob::$runs = 0;

        app()->instance(MetricsStorage::class, new class extends MetricsStorage
        {
            public function tableExists(): bool
            {
                throw new RuntimeException('SQLSTATE[HY000] [2002] Connection refused');
            }
        });

        Queue::connection('database')->push(new AuditFixesSucceedingJob);

        Artisan::call('queue:work', ['connection' => 'database', '--once' => true, '--tries' => 3]);

        expect(AuditFixesSucceedingJob::$runs)->toBe(1)
            ->and(DB::table('jobs')->count())->toBe(0)
            ->and(DB::table('failed_jobs')->count())->toBe(0);
    });

    it('still writes the run when the aggregate write fails', function () {
        $storage = new class extends MetricsStorage
        {
            public function record(string $connection, string $queue, ?string $period = null, int $processed = 0, int $failed = 0, ?float $avgRuntime = null, ?float $maxRuntime = null, ?string $job = null): void
            {
                throw new RuntimeException('metrics table locked');
            }
        };

        $job = auditQueueJob('run-after-failure');

        (new RecordQueueMetrics($storage, new CompletedJobsStorage))
            ->handleProcessed(new \Illuminate\Queue\Events\JobProcessed('database', $job));

        expect(DB::table('queue_monitor_completed_jobs')->where('uuid', 'run-after-failure')->exists())->toBeTrue();
    });

    it('does not record jobs that ran on the sync connection', function () {
        $job = auditQueueJob('sync-run');

        (new RecordQueueMetrics(new MetricsStorage, new CompletedJobsStorage))
            ->handleProcessed(new \Illuminate\Queue\Events\JobProcessed('sync', $job));

        expect(DB::table('queue_monitor_metrics')->count())->toBe(0)
            ->and(DB::table('queue_monitor_completed_jobs')->count())->toBe(0);
    });

    it('picks up a metrics table created after the worker started', function () {
        $storage = new class extends MetricsStorage
        {
            public function point(string $table): void
            {
                $this->table = $table;
            }

            public function age(float $seconds): void
            {
                $this->tableCheckedAt -= $seconds;
            }
        };

        $storage->point('not_migrated_yet');
        expect($storage->tableExists())->toBeFalse();

        // Migrated meanwhile: still cached as missing within the minute...
        $storage->point('queue_monitor_metrics');
        expect($storage->tableExists())->toBeFalse();

        // ...and found once it has passed, without restarting the worker.
        $storage->age(61);
        expect($storage->tableExists())->toBeTrue();
    });
});

describe('runtime averages', function () {
    it('assigns the average before the counts it is weighted by', function () {
        $storage = new class extends MetricsStorage
        {
            public function updates(): array
            {
                return $this->upsertUpdates();
            }
        };

        $order = array_keys($storage->updates());

        // MySQL evaluates ON DUPLICATE KEY UPDATE left to right, see upsertUpdates().
        expect(array_search('avg_runtime', $order, true))->toBeLessThan(array_search('processed', $order, true))
            ->and(array_search('avg_runtime', $order, true))->toBeLessThan(array_search('failed', $order, true));
    });

    it('averages runs correctly', function () {
        $storage = new MetricsStorage;

        foreach ([10.0, 20.0, 30.0] as $runtime) {
            $storage->record('database', 'default', '2026-10-22 10:00:00', processed: 1, avgRuntime: $runtime, maxRuntime: $runtime, job: 'App\Jobs\Avg');
        }

        $row = DB::table('queue_monitor_metrics')->where('job', 'App\Jobs\Avg')->first();

        expect((int) $row->processed)->toBe(3)
            ->and(round((float) $row->avg_runtime, 6))->toBe(20.0)
            ->and((float) $row->max_runtime)->toBe(30.0);
    });
});

describe('access', function () {
    it('hides every widget from users who may not see the monitor', function (string $widget) {
        config()->set('filament-queue-monitor.authorize', false);
        expect($widget::canView())->toBeFalse();

        config()->set('filament-queue-monitor.authorize', true);
        expect($widget::canView())->toBeTrue();
    })->with([
        QueueStatsOverviewWidget::class,
        QueueCountersWidget::class,
        QueueActivityWidget::class,
        JobBreakdownWidget::class,
    ]);

    it('holds retry and delete to authorize_actions', function () {
        config()->set('filament-queue-monitor.authorize', true);

        config()->set('filament-queue-monitor.authorize_actions', null);
        expect(Access::canManage())->toBeTrue();

        config()->set('filament-queue-monitor.authorize_actions', false);
        expect(Access::canAccess())->toBeTrue()
            ->and(Access::canManage())->toBeFalse();

        \Illuminate\Support\Facades\Gate::define('manage-queue-monitor', fn ($user = null) => true);
        config()->set('filament-queue-monitor.authorize_actions', 'manage-queue-monitor');
        expect(Access::canManage())->toBeTrue();

        config()->set('filament-queue-monitor.authorize', false);
        expect(Access::canManage())->toBeFalse();
    });
});

describe('bounded lists', function () {
    it('limits the database driver lists', function () {
        DB::table('jobs')->insert(array_map(fn (int $i): array => auditJobRow('bulk', $i), range(1, 30)));
        DB::table('jobs')->insert(array_map(fn (int $i): array => auditJobRow('bulk', 100 + $i, now()->timestamp), range(1, 30)));

        $driver = new DatabaseQueueMonitorDriver;

        expect($driver->pendingJobs('bulk', 5))->toHaveCount(5)
            ->and($driver->processingJobs('bulk', 7))->toHaveCount(7)
            ->and($driver->pendingJobs('bulk'))->toHaveCount(30);
    });

    it('limits the redis driver lists', function () {
        config()->set('filament-queue-monitor.driver', 'redis');
        Redis::connection()->del('queues:bulk');

        foreach (range(1, 20) as $i) {
            Redis::connection()->rpush('queues:bulk', json_encode(['uuid' => "r-{$i}", 'displayName' => 'App\Jobs\Bulk']));
        }

        $driver = new RedisQueueMonitorDriver;

        expect($driver->pendingJobs('bulk', 3))->toHaveCount(3)
            ->and($driver->pendingJobs('bulk', 3)[0]->uuid)->toBe('r-1')
            ->and($driver->pendingJobs('bulk'))->toHaveCount(20);

        Redis::connection()->del('queues:bulk');
    });

    it('limits failed jobs in SQL, newest first', function () {
        foreach (range(1, 12) as $i) {
            DB::table('failed_jobs')->insert([
                'uuid' => "failed-{$i}",
                'connection' => 'database',
                'queue' => 'default',
                'payload' => '{}',
                'exception' => 'boom',
            ]);
        }

        $driver = new DatabaseQueueMonitorDriver;
        $jobs = $driver->failedJobs(4);

        expect($jobs)->toHaveCount(4)
            ->and($jobs[0]->uuid)->toBe('failed-12')
            ->and($jobs[0]->id)->toBe('failed-12')
            ->and($driver->findFailedJob($jobs[0]->id)?->id)->toBe('failed-12');
    });

    it('caps a list page and says so', function () {
        config()->set('filament-queue-monitor.list_limit', 10);
        config()->set('filament-queue-monitor.authorize', true);
        DB::table('jobs')->insert(array_map(fn (int $i): array => auditJobRow('default', $i), range(1, 25)));

        $page = new ListJobs;
        $page->bootedInteractsWithTable();

        expect($page->getAllTableRecordsCount())->toBe(10)
            ->and($page->truncationNotice())->toContain('10');

        DB::table('jobs')->delete();
        DB::table('jobs')->insert(array_map(fn (int $i): array => auditJobRow('default', $i), range(1, 10)));

        $exact = new ListJobs;
        $exact->bootedInteractsWithTable();

        expect($exact->getAllTableRecordsCount())->toBe(10)
            ->and($exact->truncationNotice())->toBeNull();
    });

    it('caps the failed jobs page', function () {
        config()->set('filament-queue-monitor.list_limit', 3);
        config()->set('filament-queue-monitor.authorize', true);

        foreach (range(1, 5) as $i) {
            DB::table('failed_jobs')->insert([
                'uuid' => "page-failed-{$i}",
                'connection' => 'database',
                'queue' => 'default',
                'payload' => '{}',
                'exception' => 'boom',
            ]);
        }

        $page = new ListFailedJobs;
        $page->bootedInteractsWithTable();

        expect($page->getAllTableRecordsCount())->toBe(3)
            ->and($page->truncationNotice())->not->toBeNull();
    });
});

describe('the monitored connection', function () {
    it('honours a queue connection name in QUEUE_MONITOR_DRIVER', function () {
        config()->set('queue.default', 'redis');
        config()->set('queue.connections.reports', [
            'driver' => 'database',
            'table' => 'report_jobs',
            'connection' => 'testing',
            'queue' => 'reports',
        ]);
        config()->set('filament-queue-monitor.driver', 'reports');

        $driver = new class extends DatabaseQueueMonitorDriver
        {
            public function describe(): array
            {
                return [$this->queueConnection, $this->table];
            }
        };

        expect(MonitoredConnection::name())->toBe('reports')
            ->and($driver->describe())->toBe(['reports', 'report_jobs']);
    });

    it('follows queue.default when the driver type matches', function () {
        config()->set('queue.default', 'database-high');
        config()->set('queue.connections.database-high', ['driver' => 'database', 'table' => 'jobs']);
        config()->set('filament-queue-monitor.driver', 'database');

        expect(MonitoredConnection::name())->toBe('database-high');
    });

    it('reads redis from the queue connection rather than "default"', function () {
        config()->set('queue.default', 'redis');
        config()->set('queue.connections.redis.connection', 'queue');
        config()->set('filament-queue-monitor.driver', 'redis');
        config()->set('filament-queue-monitor.redis.connection', null);

        $driver = new class extends RedisQueueMonitorDriver
        {
            public function redisConnectionName(): ?string
            {
                return $this->connection;
            }
        };

        expect($driver->redisConnectionName())->toBe('queue');
    });
});

describe('redis reservations', function () {
    beforeEach(function () {
        config()->set('filament-queue-monitor.driver', 'redis');
        config()->set('queue.default', 'redis');
        config()->set('queue.connections.redis.retry_after', 90);
        Redis::connection()->del('queues:audit', 'queues:audit:reserved');
    });

    afterEach(function () {
        Redis::connection()->del('queues:audit', 'queues:audit:reserved');
    });

    it('reports when a job was reserved, not when its reservation expires', function () {
        $reservedAt = now()->subMinutes(5)->timestamp;

        // Laravel scores the reserved set with reservation time + retry_after.
        Redis::connection()->zadd('queues:audit:reserved', $reservedAt + 90, json_encode(['uuid' => 'reserved-1']));

        $job = (new RedisQueueMonitorDriver)->processingJobs('audit')[0];

        expect($job->reservedAt->timestamp)->toBe($reservedAt);
    });

    it('counts a job as stuck from when it was reserved', function () {
        config()->set('filament-queue-monitor.redis.queues', ['audit']);
        $reservedAt = now()->subHours(13)->timestamp;

        Redis::connection()->zadd('queues:audit:reserved', $reservedAt + 90, json_encode(['uuid' => 'stuck-1']));
        Redis::connection()->zadd('queues:audit:reserved', now()->subHours(11)->timestamp + 90, json_encode(['uuid' => 'fresh-1']));

        expect((new RedisQueueMonitorDriver)->stuckJobsCount(12))->toBe(1);
    });
});

describe('retrying failed jobs', function () {
    it('only builds the command class when refreshing retryUntil', function () {
        AuditFixesWakeupProbe::$wokeUp = false;

        $command = new AuditFixesRetryableJob;
        $command->nested = new AuditFixesWakeupProbe;

        $payload = json_encode([
            'attempts' => 3,
            'retryUntil' => 1,
            'data' => [
                'commandName' => AuditFixesRetryableJob::class,
                'command' => serialize($command),
            ],
        ]);

        $driver = new class extends DatabaseQueueMonitorDriver
        {
            public function refresh(string $payload): string
            {
                return $this->refreshRetryUntil($payload);
            }
        };

        $refreshed = json_decode($driver->refresh($payload), true);

        expect($refreshed['retryUntil'])->toBe(1999999999)
            ->and(AuditFixesWakeupProbe::$wokeUp)->toBeFalse();
    });

    it('leaves the deadline alone when the command class is unknown', function () {
        $payload = json_encode([
            'retryUntil' => 1,
            'data' => [
                'commandName' => 'App\Jobs\DoesNotExist',
                'command' => 'O:20:"App\Jobs\DoesNotExist":0:{}',
            ],
        ]);

        $driver = new class extends DatabaseQueueMonitorDriver
        {
            public function refresh(string $payload): string
            {
                return $this->refreshRetryUntil($payload);
            }
        };

        expect(json_decode($driver->refresh($payload), true)['retryUntil'])->toBe(1);
    });
});

describe('defaults', function () {
    it('does not store payloads unless asked to', function () {
        $defaults = require __DIR__.'/../../src/resources/config/filament-queue-monitor.php';

        expect($defaults['metrics']['store_payload'])->toBeFalse()
            ->and($defaults['list_limit'])->toBe(1000)
            ->and($defaults['authorize_actions'])->toBeNull();
    });
});
