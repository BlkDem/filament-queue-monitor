<div class="fqm-page-wrap">
    <style>
        .fqm-page-wrap { display: flex; flex-direction: column; gap: 24px; }
        .fqm-header { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
        .fqm-title { margin: 0; font-size: 22px; font-weight: 600; color: #111827; }
        html.dark .fqm-title { color: #f9fafb; }
        .fqm-stats-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 16px; }
        .fqm-stat-card { background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; padding: 16px; box-shadow: 0 1px 2px rgba(16,24,40,0.04); }
        html.dark .fqm-stat-card { background: #1f2937; border-color: #374151; box-shadow: none; }
        .fqm-stat-label { font-size: 13px; color: #6b7280; }
        html.dark .fqm-stat-label { color: #9ca3af; }
        .fqm-stat-value { font-size: 24px; font-weight: 700; }
        .fqm-stat-value.warning { color: #d97706; }
        .fqm-stat-value.info { color: #0891b2; }
        .fqm-stat-value.danger { color: #dc2626; }
        .fqm-meta { font-size: 13px; color: #6b7280; }
        html.dark .fqm-meta { color: #9ca3af; }
        .fqm-link { color: #4f46e5; font-weight: 500; text-decoration: none; }
        .fqm-link:hover { color: #4338ca; }
    </style>

    <header class="fqm-header">
        <h1 class="fqm-title">Queue Details: {{ $queueInfo->name ?? $queue }}</h1>
    </header>

    <div class="fqm-stats-grid">
        <div class="fqm-stat-card">
            <div class="fqm-stat-label">Pending</div>
            <div class="fqm-stat-value warning">{{ $queueInfo->pending ?? 0 }}</div>
        </div>
        <div class="fqm-stat-card">
            <div class="fqm-stat-label">Processing</div>
            <div class="fqm-stat-value info">{{ $queueInfo->processing ?? 0 }}</div>
        </div>
        <div class="fqm-stat-card">
            <div class="fqm-stat-label">Delayed</div>
            <div class="fqm-stat-value warning">{{ $queueInfo->delayed ?? 0 }}</div>
        </div>
        <div class="fqm-stat-card">
            <div class="fqm-stat-label">Failed</div>
            <div class="fqm-stat-value danger">{{ $queueInfo->failed ?? 0 }}</div>
        </div>
    </div>

    <p class="fqm-meta">Last Activity: {{ isset($queueInfo->lastActivityAt) ? $queueInfo->lastActivityAt->format('Y-m-d H:i:s') : 'N/A' }}</p>

    <a class="fqm-link" href="{{ \BlkDem\FilamentQueueMonitor\Filament\Pages\Jobs\ListJobs::getUrl(['queue' => $queue]) }}">View Queued Jobs &rarr;</a>
</div>