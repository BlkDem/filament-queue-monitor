@php
    use BlkDem\FilamentQueueMonitor\Support\Trans;
@endphp
@include('filament-queue-monitor::pages.partials.page-shell', [
    'breadcrumbs' => [
        \BlkDem\FilamentQueueMonitor\Filament\Pages\Dashboard::getUrl() => Trans::get('navigation.label'),
    ],
    'heading' => Trans::get('completed_jobs.title'),
    'subheading' => Trans::get('completed_jobs.subtitle'),
    'content' => $this->getTable(),
])
