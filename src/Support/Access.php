<?php

namespace BlkDem\FilamentQueueMonitor\Support;

final class Access
{
    public static function canAccess(): bool
    {
        $enabled = config('filament-queue-monitor.enabled', true);

        if (is_string($enabled)) {
            $enabled = ! in_array(strtolower($enabled), ['0', 'false', 'no', 'off'], true);
        }

        if (! $enabled) {
            return false;
        }

        if (self::hasNothingToMonitor()) {
            return false;
        }

        return self::passes(config('filament-queue-monitor.authorize'));
    }

    /**
     * Retrying and deleting failed jobs change what the application does, so
     * they can be held to a stricter rule than looking. authorize_actions takes
     * the same values as authorize; left unset it falls back to authorize, and
     * whoever is refused the pages is refused the actions too.
     */
    public static function canManage(): bool
    {
        if (! self::canAccess()) {
            return false;
        }

        $rule = config('filament-queue-monitor.authorize_actions');

        if ($rule === null || $rule === '') {
            return true;
        }

        return self::passes($rule);
    }

    protected static function passes(mixed $authorize): bool
    {
        if (is_bool($authorize)) {
            return $authorize;
        }

        if ($authorize instanceof \Closure) {
            return (bool) $authorize(app('auth')->user());
        }

        if (is_string($authorize) && $authorize !== '') {
            $normalized = strtolower($authorize);

            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }

            if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
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
        $connection = MonitoredConnection::name();

        return $connection === 'sync'
            || config("queue.connections.{$connection}.driver") === 'sync';
    }
}
