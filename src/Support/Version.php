<?php

namespace Kilo\FilamentQueueMonitor\Support;

final class Version
{
    public static function isFilament4(): bool
    {
        return class_exists(\Filament\Schemas\Schema::class);
    }

    public static function getActionClass(): string
    {
        return self::isFilament4()
            ? \Filament\Actions\Action::class
            : \Filament\Tables\Actions\Action::class;
    }
}
