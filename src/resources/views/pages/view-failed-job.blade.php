@php
    use BlkDem\FilamentQueueMonitor\Support\Trans;
@endphp
<div class="fi-page">
    <div class="fi-page-header-main-ctn">
        <header class="fi-header">
            <div>
                <x-filament::breadcrumbs :breadcrumbs="[
                    \BlkDem\FilamentQueueMonitor\Filament\Pages\Dashboard::getUrl() => Trans::get('navigation.label'),
                    \BlkDem\FilamentQueueMonitor\Filament\Pages\FailedJobs\ListFailedJobs::getUrl() => Trans::get('failed_jobs.title'),
                ]" />

                <h1 class="fi-header-heading">{{ $jobName }}</h1>
            </div>
        </header>
    </div>

    <div class="fi-page-main">
        <div class="fi-page-content">
            @include('filament-queue-monitor::pages.partials.details-cards')

            <div class="fqm-page-wrap">
                <div class="fqm-header">
                    <a class="fqm-back" href="{{ \BlkDem\FilamentQueueMonitor\Filament\Pages\FailedJobs\ListFailedJobs::getUrl() }}">&larr; {{ Trans::get('failed_job_detail.back') }}</a>
                </div>

                <section class="fqm-card">
                    <header class="fqm-card-header">
                        <div>
                            <h2 class="fqm-card-title">{{ Trans::get('failed_job_detail.job_information') }}</h2>
                            <p class="fqm-card-subtitle">{{ Trans::get('failed_job_detail.job_information_subtitle') }}</p>
                        </div>
                    </header>
                    <dl class="fqm-grid fqm-card-body">
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
                </section>

                <section class="fqm-card">
                    <header class="fqm-card-header">
                        <div>
                            <h2 class="fqm-card-title">{{ Trans::get('failed_job_detail.payload') }}</h2>
                            <p class="fqm-card-subtitle">{{ Trans::get('failed_job_detail.payload_subtitle') }}</p>
                        </div>
                    </header>
                    <div class="fqm-card-body">
                        @if (filled($payloadData))
                            <pre class="fqm-codebox fqm-codebox-plain">{{ json_encode($payloadData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                        @else
                            <p class="fqm-note">{{ Trans::get('failed_job_detail.no_payload') }}</p>
                        @endif
                    </div>
                </section>

                @if (filled($job->exception))
                    <section class="fqm-card fqm-details">
                        <details>
                            <summary>
                                <span>
                                    <span class="fqm-card-title">{{ Trans::get('failed_job_detail.error') }}</span>
                                    <span class="fqm-card-subtitle">{{ Trans::get('failed_job_detail.error_subtitle') }}</span>
                                </span>
                                <span class="fqm-toggle">{{ Trans::get('failed_job_detail.show_error') }} <span class="fqm-chevron">&#9660;</span></span>
                            </summary>
                            <div class="fqm-details-body">
                                <pre class="fqm-codebox fqm-codebox-error">{{ $job->exception }}</pre>
                            </div>
                        </details>
                    </section>
                @endif
            </div>
        </div>
    </div>
</div>
