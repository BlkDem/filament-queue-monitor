<?php

namespace BlkDem\FilamentQueueMonitor\Tests\Feature;

use BlkDem\FilamentQueueMonitor\Filament\FilamentQueueMonitorPlugin;
use BlkDem\FilamentQueueMonitor\Filament\Widgets\JobBreakdownWidget;
use BlkDem\FilamentQueueMonitor\Filament\Widgets\QueueActivityWidget;
use BlkDem\FilamentQueueMonitor\Filament\Widgets\QueueCountersWidget;
use BlkDem\FilamentQueueMonitor\Filament\Widgets\QueueStatsOverviewWidget;
use BlkDem\FilamentQueueMonitor\Tests\TestCase;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/**
 * Renders the widgets through Livewire with the real Filament providers. The
 * other tests call widget methods directly, so a Blade template that used a
 * class missing from one Filament major passed CI while the panel dashboard
 * returned a 500 on Filament 3.
 */
class WidgetRenderingTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        $filament = array_filter([
            \Livewire\LivewireServiceProvider::class,
            \Filament\Support\SupportServiceProvider::class,
            \Filament\Actions\ActionsServiceProvider::class,
            \Filament\Forms\FormsServiceProvider::class,
            \Filament\Infolists\InfolistsServiceProvider::class,
            \Filament\Notifications\NotificationsServiceProvider::class,
            'Filament\Schemas\SchemasServiceProvider',
            \Filament\Tables\TablesServiceProvider::class,
            \Filament\Widgets\WidgetsServiceProvider::class,
            \Filament\FilamentServiceProvider::class,
        ], 'class_exists');

        return [...$filament, ...parent::getPackageProviders($app), WidgetRenderingPanelProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        // The shared key is not a valid AES key, and rendering a panel
        // encrypts the Livewire snapshot.
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('k', 32)));
    }

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('filament-queue-monitor.authorize', true);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => json_encode(['uuid' => 'render-1', 'displayName' => 'App\Jobs\Render', 'data' => []]),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => now()->timestamp - 5,
            'created_at' => now()->timestamp - 5,
        ]);
    }

    /**
     * @dataProvider widgets
     */
    public function test_widget_renders(string $widget): void
    {
        Livewire::test($widget)->assertOk();
    }

    public static function widgets(): array
    {
        return [
            'stats overview' => [QueueStatsOverviewWidget::class],
            'counters' => [QueueCountersWidget::class],
            'activity' => [QueueActivityWidget::class],
            'job breakdown' => [JobBreakdownWidget::class],
        ];
    }

    public function test_activity_widget_shows_the_queued_job(): void
    {
        Livewire::test(QueueActivityWidget::class)->assertSee('Render');
    }
}

/**
 * Registered as a provider so the panel exists before Filament registers its
 * routes, which the widgets link to.
 */
class WidgetRenderingPanelProvider extends \Filament\PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->path('admin')
            ->default()
            ->plugin(FilamentQueueMonitorPlugin::make());
    }
}
