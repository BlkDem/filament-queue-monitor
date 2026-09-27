@php
    use BlkDem\FilamentQueueMonitor\Support\Trans;
@endphp
<x-page-shell
    :breadcrumbs="[
        \BlkDem\FilamentQueueMonitor\Filament\Pages\Jobs\ListCompletedJobs::getUrl() => Trans::get('completed_jobs.title'),
        \BlkDem\FilamentQueueMonitor\Filament\Pages\Jobs\ViewCompletedJob::getUrl(['job' => \BlkDem\FilamentQueueMonitor\Filament\Pages\Jobs\ViewCompletedJob::encodeJob($job)]) => Trans::get('completed_jobs.breadcrumb_class'),
        \BlkDem\FilamentQueueMonitor\Filament\Pages\Jobs\ListCompletedJobRuns::getUrl(['job' => \BlkDem\FilamentQueueMonitor\Filament\Pages\Jobs\ViewCompletedJob::encodeJob($job), 'minute' => \BlkDem\FilamentQueueMonitor\Filament\Pages\Jobs\ListCompletedJobRuns::encodeMinute($minute)]) => Trans::get('completed_jobs.breadcrumb_runs'),
    ]"
    :heading="$run?->uuid ?? Trans::get('completed_jobs.run_heading')"
    :subheading="filled($run?->job) ? $run->job : null"
    :backUrl="$this->backUrl()"
    :backLabel="Trans::get('completed_jobs.back_to_runs')"
>
    <x-slot name="content">
        @include('filament-queue-monitor::pages.partials.code-surface')

        <x-filament::section
            :collapsible="false"
            :heading="Trans::get('completed_jobs.run_details')"
            :description="Trans::get('completed_jobs.run_details_subtitle')"
        >
            <dl class="fqm-grid">
                <div>
                    <dt>{{ Trans::get('completed_jobs.uuid') }}</dt>
                    <dd>{{ $run?->uuid ?? Trans::get('common.empty_value') }}</dd>
                </div>
                <div>
                    <dt>{{ Trans::get('common.job') }}</dt>
                    <dd>{{ $run?->job ?? Trans::get('common.empty_value') }}</dd>
                </div>
                <div>
                    <dt>{{ Trans::get('common.queue') }}</dt>
                    <dd>{{ $run?->queue ?? Trans::get('common.empty_value') }}</dd>
                </div>
                <div>
                    <dt>{{ Trans::get('common.connection') }}</dt>
                    <dd>{{ $run?->connection ?? Trans::get('common.empty_value') }}</dd>
                </div>
                <div>
                    <dt>{{ Trans::get('completed_jobs.runtime') }}</dt>
                    <dd>{{ $run && $run->runtime !== null ? Trans::get('breakdown.seconds', ['seconds' => number_format((float) $run->runtime, 3)]) : Trans::get('common.empty_value') }}</dd>
                </div>
                <div>
                    <dt>{{ Trans::get('completed_jobs.finished_at') }}</dt>
                    <dd>{{ $run?->finished_at?->format('Y-m-d H:i:s') ?? Trans::get('common.empty_value') }}</dd>
                </div>
            </dl>
        </x-filament::section>

        <x-filament::section
            :collapsible="false"
            :heading="Trans::get('failed_job_detail.payload')"
            :description="Trans::get('completed_jobs.payload_subtitle')"
        >
            @if (filled($formattedPayload))
                <pre class="fqm-code">{{ $formattedPayload }}</pre>
            @else
                <p class="fqm-note">{{ Trans::get('failed_job_detail.no_payload') }}</p>
            @endif
        </x-filament::section>
    </x-slot>
</x-page-shell>
