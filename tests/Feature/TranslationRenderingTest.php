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

    expect($html)->toContain('Монитор очередей / Неудачные задания')
        ->toContain('Информация о задании')
        ->toContain('Данные записи неудачного задания')
        ->toContain('Время ошибки')
        ->toContain('Ошибка')
        ->toContain('Показать ошибку')
        ->toContain('Назад к неудачным заданиям')
        ->and($html)->not->toContain('Job information');
});

it('renders every completed jobs page through the same shell', function () {
    // Strip blade comments: the shell documents the removed v2 classes by name.
    $markup = fn (string $path): string => (string) preg_replace(
        '/\{\{--.*?--\}\}/s',
        '',
        file_get_contents($path)
    );

    $shell = __DIR__ . '/../../src/resources/views/pages/partials/page-shell.blade.php';

    expect($markup($shell))
        ->toContain('flex flex-col gap-y-8 py-8')
        ->toContain('fi-header flex flex-col gap-4')
        ->not->toContain('fi-page-main')
        ->not->toContain('fi-page-content')
        ->not->toContain('fi-page-header-main-ctn');

    // Each page delegates to the shell instead of repeating its own markup.
    foreach ([
        'list-completed-jobs',
        'view-completed-job',
        'list-completed-job-runs',
    ] as $page) {
        $contents = $markup(__DIR__ . "/../../src/resources/views/pages/{$page}.blade.php");

        expect($contents)->toContain('pages.partials.page-shell')
            ->not->toContain('fi-page-main')
            ->not->toContain('fi-page-content')
            ->not->toContain('gap-y-8 py-8');
    }
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
