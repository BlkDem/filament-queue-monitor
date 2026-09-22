<?php

namespace Kilo\FilamentQueueMonitor\Tests\Feature;

use Filament\Actions\PageActions;
use Filament\Forms\Components\TextInput;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PluginServiceProvider;
use Filament\Support\Colors\Color;
use Filament\Theme\Theme;
use Kilo\FilamentQueueMonitor\Filament\Pages\Queues\ListQueues;
use Kilo\FilamentQueueMonitor\Filament\Pages\Jobs\ListJobs;
use Kilo\FilamentQueueMonitor\Filament\Pages\FailedJobs\ListFailedJobs;
use Kilo\FilamentQueueMonitor\Filament\Pages\Queues\ViewQueue;
use Kilo\FilamentQueueMonitor\Filament\FilamentQueueMonitorPlugin;
use Kilo\FilamentQueueMonitor\FilamentQueueMonitorServiceProvider;
use Kilo\FilamentQueueMonitor\Tests\TestCase;

class FilamentTestCase extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return array_merge(parent::getPackageProviders($app), []);
    }

    protected function resolveApplicationConfiguration($app): void
    {
        parent::resolveApplicationConfiguration($app);

        $app['config']->set('database.default', 'testing');
    }

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('app.providers', array_merge(
            $app['config']->get('app.providers', []),
            [
                \Filament\FilamentServiceProvider::class,
            ]
        ));

        $app['config']->set('auth.defaults.guard', 'web');
        $app['config']->set('auth.providers.users.model', \Kilo\FilamentQueueMonitor\Tests\Fixtures\User::class);
    }
}
