<?php

namespace Kilo\FilamentQueueMonitor\Support;

final class Access
{
    public static function canAccess(): bool
    {
        if (! (bool) config('filament-queue-monitor.enabled', true)) {
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
}
