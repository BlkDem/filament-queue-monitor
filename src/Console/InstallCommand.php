<?php

namespace BlkDem\FilamentQueueMonitor\Console;

use Illuminate\Console\Command;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'queue-monitor:install')]
class InstallCommand extends Command
{
    protected $signature = 'queue-monitor:install
                                    {--force : Overwrite existing files}';
    protected $description = 'Install the Filament Queue Monitor plugin';

    public function handle(): int
    {
        $this->info('Installing Filament Queue Monitor...');

        $configPath = config_path('filament-queue-monitor.php');

        if (! file_exists($configPath) || $this->option('force')) {
            $this->info('Publishing config...');
            $this->call('vendor:publish', [
                '--tag' => 'filament-queue-monitor-config',
                '--force' => $this->option('force'),
            ]);
        }

        $this->info('Publishing migrations...');
        $this->call('vendor:publish', [
            '--tag' => 'filament-queue-monitor-migrations',
            '--force' => $this->option('force'),
        ]);

        $this->info('Running migrations...');
        $status = $this->call('migrate', [
            '--path' => [dirname(__DIR__) . '/Database/Migrations'],
            '--realpath' => true,
            '--force' => true,
        ]);

        if ($status !== static::SUCCESS) {
            return $status;
        }

        $this->info('Register the plugin in your PanelServiceProvider:');
        $this->line('');
        $this->line('Add this to your Panel::configure() method:');
        $this->line('');
        $this->line("    ->plugins([");
        $this->line("        \\BlkDem\\FilamentQueueMonitor\\Filament\\FilamentQueueMonitorPlugin::make(),");
        $this->line("    ])");
        $this->line('');

        $this->info('Installation complete!');

        return static::SUCCESS;
    }
}
