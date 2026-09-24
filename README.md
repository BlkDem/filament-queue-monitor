# Filament Queue Monitor

A lightweight queue monitoring plugin for Filament. It supports database and Redis queue drivers and is compatible with Laravel 10, 11, and 12 and Filament 3 and 4.

## Requirements

- PHP 8.2 or newer
- Laravel 10, 11, or 12
- Filament 3.3 or 4
- A database connection for database queues and metrics
- The `ext-redis` extension only when Redis queue monitoring is enabled

## Installation

```bash
composer require kilo/filament-queue-monitor
php artisan queue-monitor:install
```

The install command publishes the configuration and migrations, then runs only this package's migration. Register the plugin in a Filament panel:

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
    'enabled' => true,

    'driver' => env('QUEUE_MONITOR_DRIVER'),

    'refresh_interval' => 10,

    'metrics' => [
        'enabled' => env('QUEUE_MONITOR_METRICS_ENABLED', true),
        'table' => env('QUEUE_MONITOR_METRICS_TABLE', 'queue_monitor_metrics'),
        'retention_days' => env('QUEUE_MONITOR_METRICS_RETENTION_DAYS', 30),
        'refresh_interval' => env('QUEUE_MONITOR_METRICS_REFRESH_INTERVAL', 30),
    ],

    'navigation' => [
        'enabled' => env('QUEUE_MONITOR_NAV_ENABLED', true),
        'group' => env('QUEUE_MONITOR_NAV_GROUP', 'Queue Monitor'),
        'sort' => env('QUEUE_MONITOR_NAV_SORT', 0),
    ],

    'authorize' => env('QUEUE_MONITOR_AUTHORIZE', false),

    'failed_jobs' => [
        'table' => env('QUEUE_MONITOR_FAILED_JOBS_TABLE', 'failed_jobs'),
        'database' => env('QUEUE_MONITOR_FAILED_JOBS_DATABASE', null),
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
| `QUEUE_MONITOR_DRIVER` | — | Queue driver to monitor: `database` or `redis` |
| `QUEUE_MONITOR_AUTHORIZE` | `false` | Access control. Set to `true` to allow all authenticated users. Use a Closure or Gate ability for custom logic. |
| `QUEUE_MONITOR_NAV_ENABLED` | `true` | Show/hide navigation group |
| `QUEUE_MONITOR_NAV_GROUP` | `Queue Monitor` | Navigation group label |
| `QUEUE_MONITOR_NAV_SORT` | `0` | Navigation sort order |
| `QUEUE_MONITOR_REFRESH_INTERVAL` | `10` | Dashboard auto-refresh interval in seconds (0 = disabled) |
| `QUEUE_MONITOR_METRICS_ENABLED` | `true` | Enable metrics collection |
| `QUEUE_MONITOR_METRICS_TABLE` | `queue_monitor_metrics` | Database table for metrics |
| `QUEUE_MONITOR_METRICS_RETENTION_DAYS` | `30` | Days to retain metrics |
| `QUEUE_MONITOR_METRICS_REFRESH_INTERVAL` | `30` | Metrics chart refresh interval in seconds |
| `QUEUE_MONITOR_FAILED_JOBS_TABLE` | `failed_jobs` | Failed jobs table name |
| `QUEUE_MONITOR_FAILED_JOBS_DATABASE` | `null` | Database connection for failed jobs (null = default) |
| `QUEUE_MONITOR_REDIS_CONNECTION` | `null` | Redis connection name for Redis driver |
| `QUEUE_MONITOR_REDIS_QUEUES` | `[]` | Comma-separated allowlist of Redis queue names (empty = auto-discover) |

### Access Control

Access is denied by default (`QUEUE_MONITOR_AUTHORIZE=false`). Options:

- Set `QUEUE_MONITOR_AUTHORIZE=true` to allow all authenticated users
- Or in `AppServiceProvider`, set a Closure for custom logic:
  ```php
  config(['filament-queue-monitor.authorize' => fn () => auth()->user()->can('view-queue-monitor')]);
  ```
- Or define a Gate: `Gate::define('view-queue-monitor', fn ($user) => $user->isAdmin());`

The metrics table is configurable, but the shipped migration creates `queue_monitor_metrics`. Create an equivalent table when changing `QUEUE_MONITOR_METRICS_TABLE`.

## Queue Drivers

### Database Driver (Laravel database queues)

```env
QUEUE_MONITOR_DRIVER=database
```

### Redis Driver (Laravel Redis queues)

```env
QUEUE_MONITOR_DRIVER=redis
QUEUE_MONITOR_REDIS_CONNECTION=default
```

`QUEUE_MONITOR_REDIS_QUEUES` can contain a comma-separated allowlist of queue names. When empty, queue names are discovered with Redis `SCAN`.

## Features

### Dashboard
Real-time overview with:
- **Stats Overview**: Pending, Processing, Delayed, Failed counts per queue
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

Columns: Job Class, Queue, Status (Pending/Processing badge), Attempts, Pushed At, Available At, Delayed (Yes/No badge).

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
# Install (publish config, migrations, run migrations)
php artisan queue-monitor:install

# Prune old metrics (older than retention_days)
php artisan queue-monitor:prune
```

## Maintenance

The `queue-monitor:prune` command deletes metrics older than the configured `retention_days`. Schedule it in your kernel:

```php
$schedule->command('queue-monitor:prune')->daily();
```

## Metrics Collection

Metrics are automatically collected via Laravel queue events:
- `JobProcessing` — increments processing count
- `JobProcessed` — increments processed count, records duration
- `JobFailed` — increments failed count
- `JobExceptionOccurred` — records exception

## Testing

```bash
# Run package tests
vendor/bin/pest

# Run e2e tests (requires running Filament app)
cd /path/to/e2e-app && php artisan test
```

## License

MIT License