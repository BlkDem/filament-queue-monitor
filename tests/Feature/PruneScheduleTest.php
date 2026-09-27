<?php

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;

it('schedules the prune command daily', function () {
    // A full payload per completed run means the table only grows until
    // something prunes it, and nothing did.
    $schedule = app(Schedule::class);

    $commands = collect($schedule->events())
        ->filter(fn ($event): bool => str_contains($event->command ?? '', 'queue-monitor:prune'));

    expect($commands)->toHaveCount(1)
        ->and($commands->first()->expression)->toBe('0 0 * * *');
});

it('leaves the prune command unscheduled when auto prune is off', function () {
    // Someone who schedules the command themselves would otherwise get it
    // twice a day.
    config()->set('filament-queue-monitor.metrics.auto_prune', false);

    $prunes = collect(app(Schedule::class)->events())
        ->filter(fn ($event): bool => str_contains($event->command ?? '', 'queue-monitor:prune'));

    expect($prunes)->toBeEmpty();
});

it('does not register the schedule while handling a request', function () {
    // callAfterResolving means the callback only runs when the scheduler is
    // actually built. Registering it on every request would be wasteful and
    // would show up in queue-monitor:prune's output.
    expect(app()->resolved(Schedule::class))->toBeFalse();
});

it('still runs the prune command on demand', function () {
    config()->set('filament-queue-monitor.metrics.retention_days', 30);

    $this->artisan('queue-monitor:prune')
        ->assertSuccessful();
});
