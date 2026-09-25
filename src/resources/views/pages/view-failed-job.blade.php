@php
    use BlkDem\FilamentQueueMonitor\Support\Trans;
@endphp
<div class="fqm-page-wrap">
    <style>
        .fqm-page { font-family: ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; }
        .fqm-page-wrap { display: flex; flex-direction: column; gap: 22px; }
        .fqm-crumb { margin: 0 0 4px; font-size: 13px; color: #6b7280; }
        .fqm-header { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; }
        .fqm-title { margin: 0; font-size: 18px; font-weight: 600; color: #111827; line-height: 1.4; overflow-wrap: anywhere; }
        .fqm-back { flex: none; display: inline-flex; align-items: center; gap: 6px; font-size: 13px; font-weight: 500; color: #4f46e5; text-decoration: none; white-space: nowrap; }
        .fqm-back:hover { color: #4338ca; }
        .fqm-card { background: #ffffff; border: 1px solid #e5e7eb; border-radius: 10px; box-shadow: 0 1px 2px rgba(16, 24, 40, 0.04); overflow: hidden; }
        .fqm-card-header { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 20px; border-bottom: 1px solid #f3f4f6; }
        .fqm-card-title { margin: 0; font-size: 14px; font-weight: 600; color: #111827; }
        .fqm-card-subtitle { margin: 2px 0 0; font-size: 12px; color: #6b7280; }
        .fqm-card-body { padding: 18px 20px; }
        .fqm-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 16px 28px; margin: 0; }
        .fqm-grid dt { font-size: 12px; font-weight: 500; color: #6b7280; }
        .fqm-grid dd { margin: 3px 0 0; font-size: 13px; color: #111827; overflow-wrap: anywhere; }
        .fqm-codebox { margin: 0; padding: 16px; border-radius: 8px; overflow: auto; white-space: pre-wrap; overflow-wrap: break-word; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 12px; line-height: 1.7; }
        .fqm-codebox-plain { background: #0b1220; color: #e5e7eb; }
        .fqm-codebox-error { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c; }
        .fqm-note { margin: 0; font-size: 13px; color: #6b7280; }
        .fqm-details summary { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 20px; cursor: pointer; list-style: none; }
        .fqm-details summary::-webkit-details-marker { display: none; }
        .fqm-details summary .fqm-toggle { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 500; color: #4f46e5; }
        .fqm-chevron { transition: transform 0.15s ease; }
        .fqm-details[open] .fqm-chevron { transform: rotate(180deg); }
        .fqm-details-body { padding: 18px 20px; border-top: 1px solid #f3f4f6; }
        html.dark .fqm-crumb, html.dark .fqm-card-subtitle, html.dark .fqm-grid dt, html.dark .fqm-note { color: #9ca3af; }
        html.dark .fqm-title, html.dark .fqm-card-title, html.dark .fqm-grid dd { color: #f9fafb; }
        html.dark .fqm-card { background: #1f2937; border-color: #374151; box-shadow: none; }
        html.dark .fqm-card-header { border-bottom-color: #374151; }
        html.dark .fqm-details-body { border-top-color: #374151; }
        html.dark .fqm-back, html.dark .fqm-details summary .fqm-toggle { color: #818cf8; }
        html.dark .fqm-back:hover { color: #a5b4fc; }
        html.dark .fqm-codebox-error { background: #111827; border-color: #7f1d1d; color: #fca5a5; }
        html.dark .fqm-codebox-plain { background: #030712; color: #d1d5db; }
    </style>

    <div class="fqm-header">
        <div>
            <p class="fqm-crumb">{{ Trans::get('failed_job_detail.breadcrumb') }}</p>
            <h1 class="fqm-title">{{ $jobName }}</h1>
        </div>
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
