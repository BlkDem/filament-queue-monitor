<?php

use Filament\Tables\Table;
use Illuminate\Support\Facades\App;
use BlkDem\FilamentQueueMonitor\Filament\Pages\Dashboard;
use BlkDem\FilamentQueueMonitor\Filament\Pages\FailedJobs\ListFailedJobs;
use BlkDem\FilamentQueueMonitor\Filament\Pages\Jobs\ListDelayedJobs;
use BlkDem\FilamentQueueMonitor\Filament\Pages\Jobs\ListJobs;
use BlkDem\FilamentQueueMonitor\Filament\Pages\Queues\ListQueues;
use BlkDem\FilamentQueueMonitor\Filament\Widgets\JobBreakdownWidget;
use BlkDem\FilamentQueueMonitor\Filament\Widgets\QueueActivityWidget;
use BlkDem\FilamentQueueMonitor\Support\Trans;

afterEach(function () {
    App::setLocale('en');
});

it('registers the package translations under the composer namespace', function () {
    expect(Trans::NAMESPACE)->toBe('blkdem/filament-queue-monitor')
        ->and(Trans::get('navigation.label'))->toBe('Queue Monitor');
});

it('resolves the dashboard title and subheading from the translations', function () {
    App::setLocale('en');

    $english = new Dashboard();

    expect($english->getTitle())->toBe('Queue Monitor')
        ->and($english->getSubheading())->toBe('Live view of your queues and recent job activity.')
        ->and($english->getTitle())->not->toContain('::');

    App::setLocale('ru');

    $russian = new Dashboard();

    expect($russian->getTitle())->toBe('Монитор очередей')
        ->and($russian->getSubheading())->toBe('Просмотр очередей и активности заданий в реальном времени.')
        ->and($russian->getTitle())->not->toContain('::');
});

it('translates navigation labels into russian', function () {
    App::setLocale('ru');

    expect(Dashboard::getNavigationLabel())->toBe('Монитор очередей')
        ->and(ListQueues::getNavigationLabel())->toBe('Очереди')
        ->and(ListJobs::getNavigationLabel())->toBe('Задания')
        ->and(ListDelayedJobs::getNavigationLabel())->toBe('Отложенные задания')
        ->and(ListFailedJobs::getNavigationLabel())->toBe('Неудачные задания');
});

it('falls back to the english translation for unsupported locales', function () {
    App::setLocale('de');

    expect(Trans::get('navigation.label'))->toBe('Queue Monitor');
});

it('keeps the english and russian translation files structurally identical', function () {
    $flatten = function (array $lines, string $prefix = '') use (&$flatten): array {
        $flat = [];

        foreach ($lines as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (is_array($value)) {
                $flat = array_merge($flat, $flatten($value, $path));

                continue;
            }

            $flat[] = $path;
        }

        return $flat;
    };

    $base = __DIR__ . '/../../src/resources/lang';

    $english = $flatten(require $base . '/en/queue_monitor.php');
    $russian = $flatten(require $base . '/ru/queue_monitor.php');

    sort($english);
    sort($russian);

    expect($english)->toHaveCount(140)
        ->and($russian)->toBe($english);
});

it('resolves the navigation group from the translation unless config overrides it', function () {
    App::setLocale('ru');

    config()->set('filament-queue-monitor.navigation.group', null);

    expect(Trans::navigationGroup())->toBe('Монитор очередей')
        ->and(Dashboard::getNavigationGroup())->toBe('Монитор очередей');

    config()->set('filament-queue-monitor.navigation.group', 'Custom Group');

    expect(Trans::navigationGroup())->toBe('Custom Group')
        ->and(Dashboard::getNavigationGroup())->toBe('Custom Group');

    config()->set('filament-queue-monitor.navigation.group', '   ');

    expect(Trans::navigationGroup())->toBe('Монитор очередей');
});

it('translates queue table column labels and search placeholders', function () {
    config()->set('filament-queue-monitor.driver', 'database');
    App::setLocale('ru');

    $page = new ListQueues();
    $table = $page->table(Table::make($page));

    $labels = array_map(fn ($column) => $column->getLabel(), $table->getColumns());

    expect($labels)->toContain(
        'Очередь',
        'В ожидании',
        'В обработке',
        'Отложено',
        'Неудачные',
        'Всего',
        'Последняя активность',
    )->and($table->getSearchPlaceholder())->toBe('Поиск очередей...');
});

it('translates job table filters and status options', function () {
    config()->set('filament-queue-monitor.driver', 'database');
    App::setLocale('ru');

    $page = new ListDelayedJobs();
    $table = $page->table(Table::make($page));

    expect($table->getSearchPlaceholder())->toBe('Поиск заданий...')
        ->and($table->getFilter('status')->getOptions())->toBe([
            'pending' => 'В ожидании',
            'processing' => 'В обработке',
        ])
        ->and($table->getFilter('createdAt')->getLabel())->toBe('Добавлено')
        ->and($table->getFilter('availableAt')->getLabel())->toBe('Доступно');
});

it('translates the failed jobs filters, actions and modals', function () {
    App::setLocale('ru');

    $page = new ListFailedJobs();
    $table = $page->table(Table::make($page));

    expect($table->getSearchPlaceholder())->toBe('Поиск неудачных заданий...')
        ->and($table->getFilter('failedAt')->getLabel())->toBe('Время ошибки');

    $actions = [];

    foreach ($table->getActions() as $action) {
        $actions[$action->getName()] = $action;
    }

    expect($actions)->toHaveKeys(['retry', 'delete'])
        ->and($actions['retry']->getLabel())->toBe('Повторить')
        ->and($actions['delete']->getLabel())->toBe('Удалить')
        ->and($actions['retry']->getModalHeading())->toBe('Повторить неудачное задание')
        ->and($actions['delete']->getModalDescription())->toBe('Вы уверены, что хотите удалить это неудачное задание? Это действие необратимо.');
});

it('translates the activity and breakdown widget headings', function () {
    config()->set('filament-queue-monitor.driver', 'database');
    App::setLocale('ru');

    $activity = new QueueActivityWidget();
    $activityTable = $activity->table(Table::make($activity));

    expect($activityTable->getHeading())->toBe('Активность очередей');

    $breakdown = new JobBreakdownWidget();
    $breakdown->boot();
    $breakdownTable = $breakdown->table(Table::make($breakdown));

    expect($breakdownTable->getHeading())->toBe('Разбивка по заданиям')
        ->and($breakdown->periods)->toBe([
            'hour' => 'Последний час',
            'today' => 'Сегодня',
            '24h' => 'Последние 24 часа',
            '7d' => 'Последние 7 дней',
        ]);
});

it('localises status values', function () {
    App::setLocale('ru');

    expect(Trans::status('pending'))->toBe('В ожидании')
        ->and(Trans::status('processing'))->toBe('В обработке')
        ->and(Trans::status(''))->toBe('Неизвестно')
        ->and(Trans::status('custom_state'))->toBe('custom_state');

    App::setLocale('en');

    expect(Trans::status('pending'))->toBe('Pending')
        ->and(Trans::status(''))->toBe('Unknown');
});

it('localises the delayed-for display', function () {
    expect(Trans::delayedFor(null))->toBe('—')
        ->and(Trans::delayedFor(0))->toBe('0 mins')
        ->and(Trans::delayedFor(1))->toBe('1 min')
        ->and(Trans::delayedFor(5))->toBe('5 mins')
        ->and(Trans::delayedFor(59))->toBe('59 mins')
        ->and(Trans::delayedFor(60))->toBe('1 h')
        ->and(Trans::delayedFor(125))->toBe('3 h');
});

it('interpolates placeholders in translated descriptions', function () {
    App::setLocale('ru');

    expect(Trans::get('stats.description.total_queues', ['count' => 4]))->toBe('Всего очередей: 4')
        ->and(Trans::get('stats.description.stuck_jobs', ['hours' => 12]))->toBe('Зависли дольше 12 ч (reserved_at)')
        ->and(Trans::get('queue_details.title', ['name' => 'emails']))->toBe('Детали очереди: emails')
        ->and(Trans::get('actions.retried_body', ['id' => 7]))->toBe('Неудачное задание #7 отправлено на повтор.');
});
