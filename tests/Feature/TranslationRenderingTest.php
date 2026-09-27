<?php

use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Route;
use BlkDem\FilamentQueueMonitor\Filament\Pages\Dashboard;
use BlkDem\FilamentQueueMonitor\Filament\Pages\FailedJobs\ListFailedJobs;
use BlkDem\FilamentQueueMonitor\Filament\Pages\FailedJobs\ViewFailedJob;
use BlkDem\FilamentQueueMonitor\Filament\Pages\Jobs\ListJobs;
use BlkDem\FilamentQueueMonitor\Filament\Pages\Queues\ListQueues;
use BlkDem\FilamentQueueMonitor\Filament\Pages\Queues\ViewQueue;
use BlkDem\FilamentQueueMonitor\QueueMonitor\DTO\QueueInfo;
use BlkDem\FilamentQueueMonitor\QueueMonitor\Models\FailedJob as QueueMonitorFailedJob;

/**
 * PHP's glob does not treat ** as recursive, and these directories nest.
 */
function bladeFiles(string $dir): array
{
    if (! is_dir($dir)) {
        return [];
    }

    $files = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir)) as $file) {
        if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
            $files[] = (string) $file->getPathname();
        }
    }

    return $files;
}

beforeEach(function () {
    foreach ([
        \Livewire\LivewireServiceProvider::class,
        \Filament\Support\SupportServiceProvider::class,
    ] as $provider) {
        if (! app()->providerIsLoaded($provider)) {
            app()->register($provider);
        }
    }

    // Resolve <x-filament-panels::* /> without booting the whole panel provider,
    // which would reset the FilamentManager instance bound below.
    app('view')->addNamespace(
        'filament-panels',
        dirname((new ReflectionClass(\Filament\FilamentServiceProvider::class))->getFileName(), 2).'/resources/views',
    );

    app()->instance('filament', new \Filament\FilamentManager);
    app()->alias('filament', \Filament\FilamentManager::class);

    $pages = [
        Dashboard::class,
        ListQueues::class,
        ListJobs::class,
        ViewQueue::class,
        ListFailedJobs::class,
        ViewFailedJob::class,
    ];

    $routeParameters = [
        ViewQueue::class => ['queue' => 'emails'],
        ViewFailedJob::class => ['id' => '1'],
    ];

    $panel = Panel::make('admin')
        ->id('admin')
        ->path('admin')
        ->pages($pages);

    Filament::registerPanel($panel);
    Filament::setCurrentPanel($panel);

    foreach ($pages as $pageClass) {
        Route::get(
            $pageClass::getRoutePath($panel, $routeParameters[$pageClass] ?? []),
            fn () => 'ok',
        )->name($pageClass::getRouteName('admin'));
    }
});

afterEach(function () {
    App::setLocale('en');
});

it('renders the failed job detail view in russian', function () {
    config()->set('filament-queue-monitor.driver', 'database');
    App::setLocale('ru');

    $uuid = 'fqm-i18n-uuid';

    QueueMonitorFailedJob::query()->create([
        'uuid' => $uuid,
        'connection' => 'database',
        'queue' => 'default',
        'payload' => json_encode(['displayName' => 'App\\Jobs\\SendMail']),
        'exception' => 'RuntimeException: boom',
        'failed_at' => now(),
    ]);

    $job = QueueMonitorFailedJob::query()->where('uuid', $uuid)->first();

    $html = view('filament-queue-monitor::pages.view-failed-job', [
        'job' => $job,
        'jobName' => 'App\\Jobs\\SendMail',
        'payloadData' => ['displayName' => 'App\\Jobs\\SendMail'],
    ])->render();

    expect($html)->toContain('fi-header-heading')
        ->toContain('Информация о задании')
        ->toContain('Данные записи неудачного задания')
        ->toContain('Время ошибки')
        ->toContain('Ошибка')
        ->toContain('Назад к неудачным заданиям')
        ->and($html)->not->toContain('Job information');
});

it('renders every page through the shared shell', function () {
    // Strip blade comments: the shell documents the layout it mirrors.
    $markup = fn (string $path): string => (string) preg_replace(
        '/\{\{--.*?--\}\}/s',
        '',
        file_get_contents($path)
    );

    $shell = $markup(__DIR__ . '/../../src/resources/views/pages/partials/page-shell.blade.php');

    // These are filament 3 classes with real styles in the compiled theme:
    // fi-page-header-main-ctn carries the block padding, fi-page-main the gap
    // between header and content. Do not replace them with hand-rolled
    // utilities, the spacing stops matching the rest of the panel.
    expect($shell)
        ->toContain('fi-page')
        ->toContain('fi-page-header-main-ctn')
        ->toContain('fi-page-main')
        ->toContain('fi-page-content')
        ->toContain('fi-header')
        ->toContain('fi-header-heading')
        ->not->toContain('gap-y-8 py-8');

    // The frame belongs to the shell alone. Pages used to repeat filament's
    // page dom by hand, which is private markup: it can change between
    // releases and it was copied into four list views.
    $pages = glob(__DIR__ . '/../../src/resources/views/pages/*.blade.php') ?: [];

    expect($pages)->not->toBeEmpty();

    foreach ($pages as $page) {
        if (basename($page) === 'dashboard.blade.php') {
            continue;
        }

        expect($markup($page))
            ->toContain('x-page-shell')
            ->not->toContain('fi-page-main')
            ->not->toContain('fi-page-header-main-ctn')
            ->not->toContain('fi-page-content')
            ->not->toContain('gap-y-8 py-8');
    }
});

it('registers the page shell as a blade component', function () {
    // The views call <x-page-shell>, so the alias has to resolve.
    expect(view()->exists('filament-queue-monitor::pages.partials.page-shell'))->toBeTrue();
});

it('does not use filament class prefixes that do not exist', function () {
    // fi-so-stat-* was used on the queue details page, but filament 3 names
    // those classes fi-wi-stats-overview-stat-*. A class that is not in the
    // compiled theme renders as an unstyled div, silently.
    $known = [
        'fi-so-stat',
        'fi-so-stat-label',
        'fi-so-stat-value',
        'fi-so-stat-description',
    ];

    foreach (bladeFiles(__DIR__ . '/../../src/resources/views') as $view) {
        // Comments may name the old class to explain why it is gone.
        $contents = (string) preg_replace('/\{\{--.*?--\}\}/s', '', (string) file_get_contents($view));

        foreach ($known as $class) {
            expect($contents)->not->toContain($class);
        }
    }
});

it('only uses tailwind utilities that filament itself ships', function () {
    // A host app compiles its own tailwind from its own views, and the package
    // is not in those sources, so a utility that filament never uses is never
    // generated. Copying class strings out of filament's views is what keeps
    // them resolvable.
    $filament = '';

    foreach ([
        __DIR__ . '/../../vendor/filament/support/resources/views',
        __DIR__ . '/../../vendor/filament/widgets/resources/views',
        __DIR__ . '/../../vendor/filament/tables/resources/views',
    ] as $dir) {
        foreach (bladeFiles($dir) as $file) {
            $filament .= file_get_contents($file);
        }
    }

    expect($filament)->not->toBe('');

    $unknown = [];

    foreach (bladeFiles(__DIR__ . '/../../src/resources/views') as $view) {
        preg_match_all('/class="([^"]*)"/', (string) file_get_contents($view), $matches);

        foreach ($matches[1] as $attribute) {
            foreach (preg_split('/\s+/', trim($attribute)) ?: [] as $class) {
                if ($class === '' || str_starts_with($class, 'fqm-') || str_contains($class, '{{')) {
                    continue;
                }

                // Only check things shaped like tailwind utilities. Filament's
                // own component classes are covered by the other test.
                if (! preg_match('/^(dark:|sm:|lg:|xl:|md:|hover:|focus:|group-hover:)*[a-z][a-z0-9]*(-[a-z0-9]+)*(\/[0-9]+)?$/', $class)) {
                    continue;
                }

                if (str_starts_with($class, 'fi-') || str_starts_with($class, 'x-filament')) {
                    continue;
                }

                if (! str_contains($filament, $class)) {
                    $unknown[$class] = basename($view);
                }
            }
        }
    }

    expect($unknown)->toBe([]);
});

it('renders the queue details view in russian', function () {
    config()->set('filament-queue-monitor.driver', 'database');
    App::setLocale('ru');

    $queueInfo = new QueueInfo(
        name: 'emails',
        pending: 2,
        processing: 1,
        delayed: 0,
        failed: 0,
        lastActivityAt: now(),
    );

    $html = view('filament-queue-monitor::pages.view-queue', [
        'queueInfo' => $queueInfo,
        'queue' => 'emails',
        'refreshInterval' => 10,
    ])->render();

    expect($html)->toContain('Детали очереди: emails')
        ->toContain('Статистика выбранной очереди')
        ->toContain('Ожидают обработки')
        ->toContain('Сейчас обрабатываются')
        ->toContain('Посмотреть задания очереди')
        ->and($html)->not->toContain('Awaiting processing');
});

it('renders the widget toolbar controls in russian', function () {
    App::setLocale('ru');

    $polling = view('filament-queue-monitor::widgets.partials.polling-controls', [
        'defaultInterval' => 10,
    ])->render();

    expect($polling)->toContain('Обновление')
        ->toContain('По умолчанию (10 с)')
        ->toContain('5 секунд')
        ->toContain('Выключено');

    $offPolling = view('filament-queue-monitor::widgets.partials.polling-controls', [
        'defaultInterval' => 0,
    ])->render();

    expect($offPolling)->toContain('По умолчанию (выкл.)');

    $toolbar = view('filament-queue-monitor::widgets.partials.toolbar-controls', [
        'periods' => [
            'hour' => 'Последний час',
            'today' => 'Сегодня',
        ],
        'selectedPeriod' => 'today',
        'defaultInterval' => 30,
    ])->render();

    expect($toolbar)->toContain('Период')
        ->toContain('Последний час')
        ->toContain('Обновление');
});

it('keeps every blade view free of hardcoded english labels', function () {
    $forbidden = [
        'Queue Monitor', 'Queues', 'Jobs', 'Delayed Jobs', 'Failed Jobs',
        'Search queues', 'Search jobs', 'Search failed jobs',
        'Last Activity', 'Delayed For', 'Pushed At', 'Available At', 'Failed At',
        'Queue Details', 'Pending', 'Processing', 'Delayed', 'Failed', 'Total',
        'Attempts', 'Status', 'Payload', 'Exception', 'Connection',
        'No payload', 'Show error', 'Back to Failed Jobs', 'Job information',
        'Queue Activity', 'Job Breakdown', 'No active tasks', 'No job activity',
        'Last hour', 'Today', 'Last 24 hours', 'Last 7 days',
        'Default', 'seconds', 'Polling', 'Period',
    ];

    $offenders = [];

    $files = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator(__DIR__ . '/../../src/resources/views')
    );

    foreach ($files as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $contents = (string) file_get_contents($file->getPathname());

        foreach ($forbidden as $needle) {
            $patterns = [
                '="'.$needle.'"',
                '=\''.$needle.'\'',
                '>'.$needle.'<',
                '>'.$needle.' ',
            ];

            foreach ($patterns as $pattern) {
                if (str_contains($contents, $pattern)) {
                    $offenders[] = $file->getFilename().': '.$needle;
                }
            }
        }
    }

    expect($offenders)->toBe([]);
});
