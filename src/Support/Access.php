<?php

namespace BlkDem\FilamentQueueMonitor\Support;

final class Access
{
    public static function canAccess(): bool
    {
        if (! (bool) config('filament-queue-monitor.enabled', true)) {
            return false;
        }

        if (self::hasNothingToMonitor()) {
            return false;
        }

        $authorize = config('filament-queue-monitor.authorize');

        if (is_bool($authorize)) {
            return $authorize;
        }

        if ($authorize instanceof \Closure) {
            return (bool) $authorize(app('auth')->user());
        }

        if (is_string($authorize) && $authorize !== '') {
            if (in_array(strtolower($authorize), ['0', 'false', 'no', 'off'], true)) {
                return false;
            }

            return (bool) \Illuminate\Support\Facades\Gate::allows($authorize, [app('auth')->user()]);
        }

        return false;
    }

    /**
     * A sync connection runs each job immediately, so there is no jobs table
     * to read and no redis keys to scan. The pages would show one empty queue
     * forever, and the database driver would query a table that does not
     * exist, so there is nothing to show and no point offering it.
     */
    protected static function hasNothingToMonitor(): bool
    {
        $connection = config('filament-queue-monitor.driver');

        if (! is_string($connection) || $connection === '') {
            $connection = config('queue.default', 'database');
        }

        return $connection === 'sync'
            || config("queue.connections.{$connection}.driver") === 'sync';
    }
}
