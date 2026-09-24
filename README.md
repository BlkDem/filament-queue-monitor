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
'driver' => env('QUEUE_MONITOR_DRIVER'),

'authorize' => env('QUEUE_MONITOR_AUTHORIZE', false),

'metrics' => [
    'enabled' => env('QUEUE_MONITOR_METRICS_ENABLED', true),
    'table' => env('QUEUE_MONITOR_METRICS_TABLE', 'queue_monitor_metrics'),
    'retention_days' => env('QUEUE_MONITOR_METRICS_RETENTION_DAYS', 30),
],

'redis' => [
    'connection' => env('QUEUE_MONITOR_REDIS_CONNECTION', null),
    'queues' => env('QUEUE_MONITOR_REDIS_QUEUES', []),
],
```

Access is denied by default. Set `QUEUE_MONITOR_AUTHORIZE=true` for an explicitly open panel, or set the runtime configuration to a Closure or Laravel Gate ability for application-specific authorization.

The metrics table is configurable, but the shipped migration creates `queue_monitor_metrics`. Create an equivalent table when changing `QUEUE_MONITOR_METRICS_TABLE`.

## Queue Drivers

Use the database driver for Laravel database queues:

```env
QUEUE_MONITOR_DRIVER=database
```

Use the Redis driver for Laravel Redis queues:

```env
QUEUE_MONITOR_DRIVER=redis
QUEUE_MONITOR_REDIS_CONNECTION=default
```

`QUEUE_MONITOR_REDIS_QUEUES` can contain a comma-separated allowlist of queue names. When it is empty, queue names are discovered with Redis `SCAN`.

## Maintenance

Prune metrics older than the configured retention period:

```bash
php artisan queue-monitor:prune
```

The dashboard period selector filters the metrics chart by hour, today, 24 hours, or 7 days. Failed-job retry and delete actions use Laravel's configured failed-job provider.
