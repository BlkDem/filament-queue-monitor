<div class="fi-page">
    <div class="fi-page-header-main-ctn">
        <header class="fi-header">
            <div>
                <x-filament::breadcrumbs :breadcrumbs="[
                    \BlkDem\FilamentQueueMonitor\Filament\Pages\Dashboard::getUrl() => 'Queue Monitor',
                    \BlkDem\FilamentQueueMonitor\Filament\Pages\Queues\ListQueues::getUrl() => 'Queues',
                ]" />

                <h1 class="fi-header-heading">Queue Details: {{ $queueInfo->name ?? $queue }}</h1>
                <p class="fi-header-subheading">Statistics for the selected queue</p>
            </div>
        </header>
    </div>

    <div class="fi-page-main">
        <div class="fi-page-content fqm-content">
            <style>
                .fqm-content { display: flex; flex-direction: column; gap: 24px; }
                .fqm-content > x-filament\\:section,
                .fqm-content > section.fi-section,
                .fqm-content > x-filament\\:actions { margin: 0; }
                .fqm-stats-grid { display: grid; grid-template-columns: 1fr; gap: 8px; }
                @media (min-width: 640px) { .fqm-stats-grid { grid-template-columns: repeat(2, 1fr); } }
                @media (min-width: 1024px) { .fqm-stats-grid { grid-template-columns: repeat(4, 1fr); } }
                .fqm-stat-row { display: contents; }
                .fqm-stat-cell { display: flex; flex-direction: column; gap: 4px; padding: 12px 16px; border-bottom: 1px solid #e5e7eb; }
                .fqm-stats-group > .fqm-stat-cell:last-child { border-bottom: none; }
                .fqm-stat-label { font-size: 13px; color: #6b7280; }
                .fqm-stat-value { font-weight: 700; font-size: 1.25rem; line-height: 1.4; }
                .fqm-stat-desc { font-size: 13px; color: #6b7280; }
                .fqm-stat-value.warning { color: #d97706; }
                .fqm-stat-value.info { color: #0891b2; }
                .fqm-stat-value.danger { color: #dc2626; }
                html.dark .fqm-stat-label, html.dark .fqm-stat-desc { color: #9ca3af; }
            </style>

            <x-filament::section
                :collapsible="false"
                heading="Statistics"
            >
                <div class="fqm-stats-group">
                    <div class="fqm-stat-row">
                        <div class="fqm-stat-cell">
                            <span class="fqm-stat-label">Pending</span>
                            <span class="fqm-stat-value warning">{{ $queueInfo->pending ?? 0 }}</span>
                        </div>
                        <div class="fqm-stat-cell">
                            <span class="fqm-stat-desc">Awaiting processing</span>
                        </div>
                    </div>
                    <div class="fqm-stat-row">
                        <div class="fqm-stat-cell">
                            <span class="fqm-stat-label">Processing</span>
                            <span class="fqm-stat-value info">{{ $queueInfo->processing ?? 0 }}</span>
                        </div>
                        <div class="fqm-stat-cell">
                            <span class="fqm-stat-desc">Currently being worked on</span>
                        </div>
                    </div>
                    <div class="fqm-stat-row">
                        <div class="fqm-stat-cell">
                            <span class="fqm-stat-label">Delayed</span>
                            <span class="fqm-stat-value warning">{{ $queueInfo->delayed ?? 0 }}</span>
                        </div>
                        <div class="fqm-stat-cell">
                            <span class="fqm-stat-desc">Scheduled for later</span>
                        </div>
                    </div>
                    <div class="fqm-stat-row">
                        <div class="fqm-stat-cell">
                            <span class="fqm-stat-label">Failed</span>
                            <span class="fqm-stat-value danger">{{ $queueInfo->failed ?? 0 }}</span>
                        </div>
                        <div class="fqm-stat-cell">
                            <span class="fqm-stat-desc">Failed jobs</span>
                        </div>
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section
                :collapsible="false"
                heading="Details"
            >
                <dl class="space-y-4">
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500 dark:text-gray-400">Last Activity</dt>
                        <dd class="text-sm font-medium">{{ isset($queueInfo->lastActivityAt) ? $queueInfo->lastActivityAt->format('Y-m-d H:i:s') : 'N/A' }}</dd>
                    </div>
                </dl>
            </x-filament::section>

            <x-filament::actions>
                <x-filament::link
                    :href="\BlkDem\FilamentQueueMonitor\Filament\Pages\Jobs\ListJobs::getUrl(['queue' => $queue])"
                    icon="heroicon-o-arrow-right-end-on-rectangle"
                >
                    View Queued Jobs
                </x-filament::link>
            </x-filament::actions>
        </div>
    </div>
</div>