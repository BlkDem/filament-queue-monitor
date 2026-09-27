@php
    use BlkDem\FilamentQueueMonitor\Support\Trans;
@endphp
<x-page-shell
    :breadcrumbs="[
        \BlkDem\FilamentQueueMonitor\Filament\Pages\Dashboard::getUrl() => Trans::get('navigation.label'),
        \BlkDem\FilamentQueueMonitor\Filament\Pages\FailedJobs\ListFailedJobs::getUrl() => Trans::get('failed_jobs.title'),
    ]"
    :heading="$jobName"
    :backUrl="\BlkDem\FilamentQueueMonitor\Filament\Pages\FailedJobs\ListFailedJobs::getUrl()"
    :backLabel="Trans::get('failed_job_detail.back')"
>
    <x-slot name="content">
        @include('filament-queue-monitor::pages.partials.code-surface')

        <x-filament::section
            :collapsible="false"
            :heading="Trans::get('failed_job_detail.job_information')"
            :description="Trans::get('failed_job_detail.job_information_subtitle')"
        >
            <dl class="fqm-grid">
                <div>
                    <dt>{{ Trans::get('common.id') }}</dt>
                    <dd>{{ $job->id }}</dd>
                </div>
                <div>
                    <dt>{{ Trans::get('common.uuid') }}</dt>
                    <dd>{{ $job->uuid ?? Trans::get('common.empty_value') }}</dd>
                </div>
                <div>
                    <dt>{{ Trans::get('failed_job_detail.queue') }}</dt>
                    <dd>{{ $job->queue }}</dd>
                </div>
                <div>
                    <dt>{{ Trans::get('failed_job_detail.connection') }}</dt>
                    <dd>{{ $job->connection }}</dd>
                </div>
                <div>
                    <dt>{{ Trans::get('failed_jobs.failed_at') }}</dt>
                    <dd>{{ $job->failedAt?->format('Y-m-d H:i:s') ?? Trans::get('common.empty_value') }}</dd>
                </div>
            </dl>
        </x-filament::section>

        <x-filament::section
            :collapsible="false"
            :heading="Trans::get('failed_job_detail.payload')"
            :description="Trans::get('failed_job_detail.payload_subtitle')"
        >
            @if (filled($payloadData))
                <pre class="fqm-code">{{ json_encode($payloadData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            @else
                <p class="fqm-note">{{ Trans::get('failed_job_detail.no_payload') }}</p>
            @endif
        </x-filament::section>

        @if (filled($job->exception))
            <x-filament::section
                :collapsible="true"
                :collapsed="true"
                :heading="Trans::get('failed_job_detail.error')"
                :description="Trans::get('failed_job_detail.error_subtitle')"
            >
                <pre class="fqm-code fqm-code-error">{{ $job->exception }}</pre>
            </x-filament::section>
        @endif
    </x-slot>
</x-page-shell>
