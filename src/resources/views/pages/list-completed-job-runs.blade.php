@php
    use BlkDem\FilamentQueueMonitor\Support\Trans;
@endphp
@include('filament-queue-monitor::pages.partials.page-shell', [
    'breadcrumbs' => [
        \BlkDem\FilamentQueueMonitor\Filament\Pages\Jobs\ListCompletedJobs::getUrl() => Trans::get('completed_jobs.title'),
        \BlkDem\FilamentQueueMonitor\Filament\Pages\Jobs\ViewCompletedJob::getUrl(['job' => \BlkDem\FilamentQueueMonitor\Filament\Pages\Jobs\ViewCompletedJob::encodeJob($job)]) => Trans::get('completed_jobs.breadcrumb_class'),
    ],
    'heading' => Trans::get('completed_jobs.runs_heading', ['job' => $job]),
    'subheading' => Trans::get('completed_jobs.runs_subtitle', ['minute' => \Illuminate\Support\Carbon::parse($minute)->format('d.m.Y H:i')]),
    'backUrl' => \BlkDem\FilamentQueueMonitor\Filament\Pages\Jobs\ViewCompletedJob::getUrl(['job' => \BlkDem\FilamentQueueMonitor\Filament\Pages\Jobs\ViewCompletedJob::encodeJob($job)]),
    'backLabel' => Trans::get('completed_jobs.back_to_class'),
    'content' => $this->getTable(),
])
