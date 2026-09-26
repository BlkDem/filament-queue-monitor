@php
    use BlkDem\FilamentQueueMonitor\Support\Trans;
@endphp
@include('filament-queue-monitor::pages.partials.page-shell', [
    'breadcrumbs' => [
        \BlkDem\FilamentQueueMonitor\Filament\Pages\Dashboard::getUrl() => Trans::get('navigation.label'),
        \BlkDem\FilamentQueueMonitor\Filament\Pages\Jobs\ListCompletedJobs::getUrl() => Trans::get('completed_jobs.title'),
    ],
    'heading' => $job,
    'subheading' => Trans::get('completed_jobs.detail_subtitle'),
    'backUrl' => \BlkDem\FilamentQueueMonitor\Filament\Pages\Jobs\ListCompletedJobs::getUrl(),
    'backLabel' => Trans::get('completed_jobs.back'),
    'content' => $this->getTable(),
])
