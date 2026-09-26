@php
    use BlkDem\FilamentQueueMonitor\Support\Trans;
@endphp
<div class="fi-page">
    <div class="fi-page-header-main-ctn">
        <header class="fi-header">
            <div>
                <x-filament::breadcrumbs :breadcrumbs="[
                    \BlkDem\FilamentQueueMonitor\Filament\Pages\Jobs\ListCompletedJobs::getUrl() => Trans::get('completed_jobs.title'),
                    \BlkDem\FilamentQueueMonitor\Filament\Pages\Jobs\ViewCompletedJob::getUrl(['job' => \BlkDem\FilamentQueueMonitor\Filament\Pages\Jobs\ViewCompletedJob::encodeJob($job)]) => Trans::get('completed_jobs.breadcrumb_class'),
                    \BlkDem\FilamentQueueMonitor\Filament\Pages\Jobs\ListCompletedJobRuns::getUrl(['job' => \BlkDem\FilamentQueueMonitor\Filament\Pages\Jobs\ViewCompletedJob::encodeJob($job), 'minute' => \BlkDem\FilamentQueueMonitor\Filament\Pages\Jobs\ListCompletedJobRuns::encodeMinute($minute)]) => Trans::get('completed_jobs.breadcrumb_runs'),
                ]" />

                <h1 class="fi-header-heading">
                    {{ $run?->uuid ?? Trans::get('completed_jobs.run_heading') }}
                </h1>

                @if (filled($run?->job))
                    <p class="fi-header-subheading">{{ $run->job }}</p>
                @endif
            </div>

            <div class="fi-header-actions-ctn">
                <x-filament::link :href="$this->backUrl()" icon="heroicon-o-arrow-left">
                    {{ Trans::get('completed_jobs.back_to_runs') }}
                </x-filament::link>
            </div>
        </header>
    </div>

    <div class="fi-page-main">
        <div class="fi-page-content">
            @include('filament-queue-monitor::pages.partials.details-cards')

            <div class="fqm-page-wrap">
                <section class="fqm-card">
                    <header class="fqm-card-header">
                        <div>
                            <h2 class="fqm-card-title">{{ Trans::get('completed_jobs.run_details') }}</h2>
                            <p class="fqm-card-subtitle">{{ Trans::get('completed_jobs.run_details_subtitle') }}</p>
                        </div>
                    </header>
                    <dl class="fqm-grid fqm-card-body">
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
                </section>

                <section class="fqm-card">
                    <header class="fqm-card-header">
                        <div>
                            <h2 class="fqm-card-title">{{ Trans::get('failed_job_detail.payload') }}</h2>
                            <p class="fqm-card-subtitle">{{ Trans::get('completed_jobs.payload_subtitle') }}</p>
                        </div>
                    </header>
                    <div class="fqm-card-body">
                        @if (filled($formattedPayload))
                            <pre class="fqm-codebox fqm-codebox-plain">{{ $formattedPayload }}</pre>
                        @else
                            <p class="fqm-note">{{ Trans::get('failed_job_detail.no_payload') }}</p>
                        @endif
                    </div>
                </section>
            </div>
        </div>
    </div>
</div>
