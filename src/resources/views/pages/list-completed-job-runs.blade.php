@php
    use BlkDem\FilamentQueueMonitor\Support\Trans;
@endphp
<div class="fi-page">
    <section class="flex flex-col gap-y-8 py-8">
        <header class="fi-header flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <x-filament::breadcrumbs :breadcrumbs="[
                    \BlkDem\FilamentQueueMonitor\Filament\Pages\Jobs\ListCompletedJobs::getUrl() => Trans::get('completed_jobs.title'),
                    \BlkDem\FilamentQueueMonitor\Filament\Pages\Jobs\ViewCompletedJob::getUrl(['job' => \BlkDem\FilamentQueueMonitor\Filament\Pages\Jobs\ViewCompletedJob::encodeJob($job)]) => Trans::get('completed_jobs.breadcrumb_class'),
                ]" class="mb-2 hidden sm:block" />

                <h1 class="fi-header-heading text-2xl font-bold tracking-tight text-gray-950 dark:text-white sm:text-3xl">
                    {{ Trans::get('completed_jobs.runs_heading', ['job' => $job]) }}
                </h1>

                <p class="fi-header-subheading mt-2 max-w-2xl text-lg text-gray-600 dark:text-gray-400">
                    {{ Trans::get('completed_jobs.runs_subtitle', ['minute' => \Illuminate\Support\Carbon::parse($minute)->format('d.m.Y H:i')]) }}
                </p>
            </div>

            <div class="flex shrink-0 items-center gap-3 sm:mt-7">
                <x-filament::link
                    :href="\BlkDem\FilamentQueueMonitor\Filament\Pages\Jobs\ViewCompletedJob::getUrl(['job' => \BlkDem\FilamentQueueMonitor\Filament\Pages\Jobs\ViewCompletedJob::encodeJob($job)])"
                    icon="heroicon-o-arrow-left"
                >
                    {{ Trans::get('completed_jobs.back_to_class') }}
                </x-filament::link>
            </div>
        </header>

        <div class="fi-page-main">
            <div class="fi-page-content">
                {{ $this->getTable() }}
            </div>
        </div>
    </section>
</div>
