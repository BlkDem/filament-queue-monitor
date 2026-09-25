<?php

namespace BlkDem\FilamentQueueMonitor\Support;

class Trans
{
    public const NAMESPACE = 'blkdem/filament-queue-monitor';

    public const GROUP = 'queue_monitor';

    public static function get(string $key, array $replace = []): string
    {
        return (string) __(static::key($key), $replace);
    }

    protected static function choice(string $key, int|array|\Countable $number, array $replace = []): string
    {
        return (string) trans_choice(static::key($key), $number, $replace);
    }

    public static function navigationGroup(): string
    {
        $group = config('filament-queue-monitor.navigation.group');

        return is_string($group) && trim($group) !== ''
            ? $group
            : static::get('navigation.group');
    }

    public static function status(mixed $status): string
    {
        $status = (string) $status;

        if ($status === '') {
            return static::get('status.unknown');
        }

        $key = static::key("status.{$status}");
        $line = (string) __($key);

        return $line === $key ? $status : $line;
    }

    public static function delayedFor(?int $minutes): string
    {
        if ($minutes === null) {
            return static::get('common.empty_value');
        }

        if ($minutes >= 60) {
            $hours = (int) ceil($minutes / 60);

            return static::choice('delayed.hours', $hours, [
                'hours' => $hours,
                'minutes' => $minutes,
            ]);
        }

        return static::choice('delayed.minutes', $minutes, [
            'minutes' => $minutes,
            'hours' => 0,
        ]);
    }

    protected static function key(string $key): string
    {
        return static::NAMESPACE.'::'.static::GROUP.'.'.$key;
    }
}
