# Filament Queue Monitor

A lightweight queue monitoring plugin for Filament. It supports database and Redis queue drivers and is compatible with Laravel 10.43+, 11, and 12 and Filament 3 and 4.

## Requirements

- PHP 8.2 or newer
- Laravel 10.43+, 11, or 12
- Filament 3.3 or 4
- A database connection for database queues and metrics
- The `ext-redis` extension only when Redis queue monitoring is enabled

### Laravel 10

Laravel 10 no longer receives security fixes, and Composer 2.9+ refuses to
install any 10.x release because of published advisories. If you must stay on
it, the application has to opt out of that check (for example
`composer update --no-security-blocking`, or `policy.advisories` in its
`composer.json`). Prefer upgrading the framework.

## Installation

```bash
composer require blkdem/filament-queue-monitor
php artisan queue-monitor:install
```

The install command publishes the configuration, migrations and translations, then runs the migration. The service provider also registers the packaged migrations, so a plain `php artisan migrate` covers them and you never have to publish anything to get a working install. A published copy is a snapshot for review: edits to it are not what runs, because the registered path is the one `migrate` uses. Register the plugin in a Filament panel:

```php
use BlkDem\FilamentQueueMonitor\Filament\FilamentQueueMonitorPlugin;

$panel
    ->plugins([
        FilamentQueueMonitorPlugin::make(),
    ]);
```

## Configuration

The published configuration is available at `config/filament-queue-monitor.php`.

```php
return [
    'enabled' => env('QUEUE_MONITOR_ENABLED', true),

    'driver' => env('QUEUE_MONITOR_DRIVER'),

    'refresh_interval' => env('QUEUE_MONITOR_REFRESH_INTERVAL', 10),

    'list_limit' => env('QUEUE_MONITOR_LIST_LIMIT', 1000),

    'metrics' => [
        'enabled' => env('QUEUE_MONITOR_METRICS_ENABLED', true),
        'table' => env('QUEUE_MONITOR_METRICS_TABLE', 'queue_monitor_metrics'),
        'table_completed_jobs' => env('QUEUE_MONITOR_COMPLETED_JOBS_TABLE', 'queue_monitor_completed_jobs'),
        'retention_days' => env('QUEUE_MONITOR_METRICS_RETENTION_DAYS', 30),
        'store_payload' => env('QUEUE_MONITOR_STORE_PAYLOAD', false),
        'refresh_interval' => env('QUEUE_MONITOR_METRICS_REFRESH_INTERVAL', 30),
        'auto_prune' => env('QUEUE_MONITOR_AUTO_PRUNE', true),
    ],

    'navigation' => [
        'enabled' => env('QUEUE_MONITOR_NAV_ENABLED', true),
        'group' => env('QUEUE_MONITOR_NAV_GROUP'),
        'sort' => env('QUEUE_MONITOR_NAV_SORT', 0),
    ],

    'authorize' => env('QUEUE_MONITOR_AUTHORIZE', false),

    'authorize_actions' => env('QUEUE_MONITOR_AUTHORIZE_ACTIONS'),

    'failed_jobs' => [
        'table' => env('QUEUE_MONITOR_FAILED_JOBS_TABLE', 'failed_jobs'),
        'database' => env('QUEUE_MONITOR_FAILED_JOBS_DATABASE', null),
    ],

    'stuck_jobs' => [
        'threshold_hours' => env('QUEUE_MONITOR_STUCK_JOBS_THRESHOLD_HOURS', 12),
    ],

    'redis' => [
        'connection' => env('QUEUE_MONITOR_REDIS_CONNECTION', null),
        'queues' => env('QUEUE_MONITOR_REDIS_QUEUES', []),
    ],
];
```

### Environment Variables

| Variable | Default | Description |
|---|---|---|
| `QUEUE_MONITOR_ENABLED` | `true` | Master switch. When false every page and widget is hidden. |
| `QUEUE_MONITOR_DRIVER` | — | What to monitor: `database`, `redis`, or the name of a queue connection (e.g. `reports`). Empty follows `queue.default`. A driver type follows `queue.default` when that connection has the same driver. |
| `QUEUE_MONITOR_AUTHORIZE` | `false` | Who may see the pages and widgets: `true`, `false`, or a Gate ability name. |
| `QUEUE_MONITOR_AUTHORIZE_ACTIONS` | — | Who may retry and delete failed jobs: `true`, `false`, or a Gate ability. Empty means everyone allowed by `QUEUE_MONITOR_AUTHORIZE`. |
| `QUEUE_MONITOR_LIST_LIMIT` | `1000` | Most jobs a list page loads, per queue and in total. Lists are searched and sorted in memory, so this bounds what a backlog costs. A notice shows when a list is cut. |
| `QUEUE_MONITOR_NAV_ENABLED` | `true` | Show/hide navigation group |
| `QUEUE_MONITOR_NAV_GROUP` | *(translated)* | Navigation group label. Falls back to the translated `navigation.group` line when unset. |
| `QUEUE_MONITOR_NAV_SORT` | `0` | Navigation sort order |
| `QUEUE_MONITOR_REFRESH_INTERVAL` | `10` | Dashboard auto-refresh interval in seconds (0 = disabled) |
| `QUEUE_MONITOR_METRICS_ENABLED` | `true` | Enable metrics collection |
| `QUEUE_MONITOR_METRICS_TABLE` | `queue_monitor_metrics` | Database table for metrics. The packaged migrations read this, so a custom name is created correctly. |
| `QUEUE_MONITOR_COMPLETED_JOBS_TABLE` | `queue_monitor_completed_jobs` | Database table for per-run completed job records. Also read by the migrations. |
| `QUEUE_MONITOR_METRICS_RETENTION_DAYS` | `30` | Days to retain metrics and completed job records |
| `QUEUE_MONITOR_AUTO_PRUNE` | `true` | Run `queue-monitor:prune` daily. Set to `false` if you schedule it yourself. |
| `QUEUE_MONITOR_STORE_PAYLOAD` | `false` | Also keep the full job payload on each completed run. A payload holds the serialized command, so anything a job carries (tokens, personal data) is copied into the table and shown in the panel. Each one is roughly 5 KB. Run records are kept either way. |
| `QUEUE_MONITOR_METRICS_REFRESH_INTERVAL` | `30` | Metrics chart refresh interval in seconds |
| `QUEUE_MONITOR_FAILED_JOBS_TABLE` | `failed_jobs` | Unused. Failed jobs are read through Laravel's failer, see [Which storage backs each number](#which-storage-backs-each-number) |
| `QUEUE_MONITOR_FAILED_JOBS_DATABASE` | `null` | Unused, same as above |
| `QUEUE_MONITOR_STUCK_JOBS_THRESHOLD_HOURS` | `12` | Hours after which a reserved job is considered stuck |
| `QUEUE_MONITOR_REDIS_CONNECTION` | `null` | Redis connection to read. Empty uses the `connection` of the monitored queue connection, as the queue itself does. |
| `QUEUE_MONITOR_REDIS_QUEUES` | `[]` | Comma-separated allowlist of Redis queue names (empty = auto-discover) |

### Access Control

Access is denied by default (`QUEUE_MONITOR_AUTHORIZE=false`). Options:

- Set `QUEUE_MONITOR_AUTHORIZE=true` to allow every user of the panel
- Or name a Gate ability (recommended):
  ```php
  // AppServiceProvider::boot()
  Gate::define('view-queue-monitor', fn ($user) => $user->isAdmin());
  ```
  ```env
  QUEUE_MONITOR_AUTHORIZE=view-queue-monitor
  ```
- A Closure also works when set at runtime, e.g. `config(['filament-queue-monitor.authorize' => fn ($user) => ...])` in a service provider. Do not put a Closure in the config file: `php artisan config:cache` cannot serialize it.

Retrying and deleting failed jobs can be held to a stricter rule with `QUEUE_MONITOR_AUTHORIZE_ACTIONS`, which takes the same values. Without it, anyone who can see the pages can also act on failed jobs.

The widgets follow the same rule, including when they appear on Filament's own dashboard.

The packaged migrations use the configured metrics and completed-jobs table names. Configure them before running migrations; changing a name afterwards does not rename an existing table.

## Queue Drivers

### Database Driver (Laravel database queues)

```env
QUEUE_MONITOR_DRIVER=database
```

### Redis Driver (Laravel Redis queues)

```env
QUEUE_MONITOR_DRIVER=redis
```

The Redis connection is taken from the queue connection (`queue.connections.redis.connection`); set `QUEUE_MONITOR_REDIS_CONNECTION` only to override it. Reservation times are derived from the queue's `retry_after`, so keep it in the queue connection config.

`QUEUE_MONITOR_REDIS_QUEUES` can contain a comma-separated allowlist of queue names. When empty, queue names are discovered with Redis `SCAN`.

## Features

### Dashboard
Real-time overview with:
- **Stats Overview**: Queue counts (Total, Pending, Processing, Stuck Jobs)
- **Queue Counters**: Delayed Jobs, Failed Jobs, Processed (Last Hour)
- **Queue Activity**: Live table of active jobs grouped by queue with status badges
- **Job Breakdown**: Metrics chart (processed, failed, throughput) with period selector (hour, today, 24h, 7d)
- **Auto-refresh**: Configurable interval (default 10s)

### Queues (`/queue-monitor/queues`)
List all queues with stats (Pending, Processing, Delayed, Failed, Total) and Last Activity. Click a queue for details.

### Queue Details (`/queue-monitor/queues/{queue}`)
- Statistics table (Pending, Processing, Delayed, Failed) with descriptions
- Last Activity timestamp
- Link to view queued jobs

### Jobs (`/queue-monitor/jobs`)
Live table of pending/processing jobs with filters:
- **Job** — select by job class (short name)
- **Queue** — select by queue name
- **Status** — Pending / Processing
- **Delayed** — Yes / No
- **Pushed At** — date/time range filter (24h picker)
- **Available At** — date/time range filter (24h picker)

Columns: Job Class, Queue, Status (Pending/Processing badge), Attempts, Pushed At, Available At.

### Delayed Jobs (`/queue-monitor/delayed-jobs`)
List of delayed jobs (available_at > now) with filters:
- **Job** — select by job class
- **Queue** — select by queue
- **Status** — Pending / Processing
- **Pushed At** — date/time range filter (24h picker)
- **Available At** — date/time range filter (24h picker)

Columns: Job Class, Queue, Status, Attempts, Pushed At, Available At, Delayed For (shows minutes when < 1 hour, hours when ≥ 1 hour).

### Failed Jobs (`/queue-monitor/failed-jobs`)
List of failed jobs with filters:
- **Job** — select by job class
- **Queue** — select by queue
- **Status** — Pending / Processing
- **Delayed** — Yes / No
- **Failed At** — date/time range filter (24h picker)

Columns: Job (clickable → detail page), Queue (badge), Payload, Exception, Failed At.

### Failed Job Detail (`/queue-monitor/failed-jobs/{id}`)
- Job info: ID, UUID, Queue, Connection, Failed At
- Payload: formatted JSON
- Exception: collapsible full error message

## Commands

```bash
# Install (publish config, migrations, translations, run migrations)
php artisan queue-monitor:install

# Prune old metrics (older than retention_days)
php artisan queue-monitor:prune
```

## Maintenance

The `queue-monitor:prune` command deletes metrics and completed job records older than the configured `retention_days`. The completed jobs table keeps a full job payload per run, so it only grows until something prunes it.

The package schedules this command daily for you. If you would rather schedule it yourself, turn the automatic one off first, or it runs twice:

```dotenv
QUEUE_MONITOR_AUTO_PRUNE=false
```

```php
$schedule->command('queue-monitor:prune')->daily();
```

## Metrics Collection

Metrics are automatically collected via Laravel queue events:
- `JobProcessing` — increments processing count
- `JobProcessed` — increments processed count, records duration
- `JobFailed` — increments failed count
- `JobExceptionOccurred` — records exception

### Which storage backs each number

The `driver` setting only decides where **live queue state** is read from. Some
numbers cannot come from there, because the queue backend keeps no record of
them once a job is gone.

| Dashboard number | Read from |
|---|---|
| Queues, Pending, Processing, Delayed, Stuck | the driver — Redis keys or the `jobs` table |
| Failed Jobs | your app's queue failer, `config('queue.failed')` |
| Processed (last hour), Job Breakdown | `queue_monitor_metrics` |

Concretely, on the Redis driver the package reads:

- `queue_monitor_metrics` — aggregate metrics owned by the package
- `queue_monitor_completed_jobs` — individual completed runs owned by the package
- the failer's storage — `failed_jobs` with the default `database-uuids` driver

It does **not** read the `jobs` table on the redis driver, and it never creates
`failed_jobs`: that table belongs to Laravel and is configured by your app. The
`filament-queue-monitor.failed_jobs.*` config keys are unused, since failed jobs
are always read through `queue.failer`.

Failed jobs are never stored in the queue backend, and completed jobs are
deleted from it, so neither can be counted there. Point `QUEUE_FAILED_DRIVER`
at `file` or `null` if you would rather not keep a `failed_jobs` table at all.

## Translations

Every user-facing string in the package is translated. English (`en`) and Russian (`ru`) ship with the package; any other locale falls back to English.

The UI follows your application's `app.locale`, so setting the panel/app locale is all that is required:

```php
// config/app.php
'locale' => 'ru',
```

Filament panels can also be localised independently:

```php
$panel->locale('ru');
```

### Publishing

```bash
php artisan vendor:publish --tag=filament-queue-monitor-lang
```

Published files land in `lang/vendor/blkdem/filament-queue-monitor/{locale}/queue_monitor.php`. Edit them to adjust wording, or add a new locale by copying the `en` file:

```bash
mkdir -p lang/vendor/blkdem/filament-queue-monitor/fr
cp lang/vendor/blkdem/filament-queue-monitor/en/queue_monitor.php \
   lang/vendor/blkdem/filament-queue-monitor/fr/queue_monitor.php
```

Only the keys you need have to be present — any missing key falls back to English.

### Adding strings in your own code

Use the `Trans` helper so the namespace stays consistent:

```php
use BlkDem\FilamentQueueMonitor\Support\Trans;

Trans::get('stats.description.total_queues', ['count' => 4]); // "Total queues: 4"
Trans::status('pending');                                    // "Pending"
Trans::delayedFor(125);                                      // "3 h"
```

The raw translation namespace is also available directly:

```php
__('blkdem/filament-queue-monitor::queue_monitor.navigation.label');
```

### Overriding the navigation group

`QUEUE_MONITOR_NAV_GROUP` overrides the navigation group label. When it is unset the translated `navigation.group` line is used.

## Development and releases

```bash
composer install
composer test
composer validate --strict --no-check-lock
composer audit
```

See [CHANGELOG.md](CHANGELOG.md) for changes, [RELEASING.md](RELEASING.md) for
versioning and release steps, and [AUDIT.md](AUDIT.md) for the latest audit and
known operational limitations. Package versions are derived from Git tags.

## License

MIT License