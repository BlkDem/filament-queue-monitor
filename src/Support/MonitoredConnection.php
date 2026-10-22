<?php

namespace BlkDem\FilamentQueueMonitor\Support;

/**
 * The queue connection the monitor reports on.
 *
 * QUEUE_MONITOR_DRIVER accepts either a driver type ("database", "redis") or
 * the name of a queue connection. Access and the manager already honoured a
 * connection name while the drivers, pages and widgets kept reading
 * queue.default, so they could disagree about which queue they were showing.
 * Everything that needs the connection asks here instead.
 */
final class MonitoredConnection
{
    public static function name(): string
    {
        $default = config('queue.default', 'database');
        $default = is_string($default) && $default !== '' ? $default : 'database';

        $configured = config('filament-queue-monitor.driver');

        if (! is_string($configured) || $configured === '') {
            return $default;
        }

        // A driver type: the connection actually in use when it is of that
        // type, so a renamed default connection keeps its history.
        if (in_array($configured, ['database', 'redis'], true)
            && self::driverOf($default) === $configured) {
            return $default;
        }

        if (is_array(config("queue.connections.{$configured}"))) {
            return $configured;
        }

        return $default;
    }

    /**
     * The monitored connection when it is backed by the given driver, or the
     * first connection that is, falling back to a connection named after the
     * driver.
     */
    public static function nameFor(string $driver): string
    {
        $name = self::name();

        if (self::driverOf($name) === $driver) {
            return $name;
        }

        $default = config('queue.default');

        if (is_string($default) && self::driverOf($default) === $driver) {
            return $default;
        }

        return $driver;
    }

    public static function driverOf(string $connection): ?string
    {
        $driver = config("queue.connections.{$connection}.driver");

        return is_string($driver) ? $driver : null;
    }
}
