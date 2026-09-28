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

        $this->info('Publishing translations...');
        $this->call('vendor:publish', [
            '--tag' => 'filament-queue-monitor-lang',
            '--force' => $this->option('force'),
        ]);

        $this->info('Running migrations...');

        // No --path here: the service provider already registers the packaged
        // migrations, so a plain migrate covers them. Naming the path as well
        // meant two mechanisms for one set of files, which is what made it
        // unclear whether an edited published copy would take effect.
        $status = $this->call('migrate', [
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
