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
                .fqm-stats-table { width: 100%; border-collapse: collapse; }
                .fqm-stats-table th, .fqm-stats-table td { padding: 12px 16px; text-align: left; border-bottom: 1px solid #e5e7eb; }
                .fqm-stats-table th { font-weight: 600; color: #374151; background: #f9fafb; }
                .fqm-stats-table td { color: #111827; }
                .fqm-stats-table tr:last-child td { border-bottom: none; }
                html.dark .fqm-stats-table th { color: #f9fafb; background: #1f2937; }
                html.dark .fqm-stats-table td { color: #f9fafb; border-bottom-color: #374151; }
                .fqm-stats-table .stat-label { color: #6b7280; }
                html.dark .fqm-stats-table .stat-label { color: #9ca3af; }
                .fqm-stats-table .stat-value { font-weight: 700; font-size: 1.25rem; }
                .fqm-stats-table .stat-value.warning { color: #d97706; }
                .fqm-stats-table .stat-value.info { color: #0891b2; }
                .fqm-stats-table .stat-value.danger { color: #dc2626; }
            </style>

            <table class="fqm-stats-table">
                <thead>
                    <tr>
                        <th class="stat-label">Status</th>
                        <th class="stat-label">Count</th>
                        <th class="stat-label">Description</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td class="stat-label">Pending</td>
                        <td class="stat-value warning">{{ $queueInfo->pending ?? 0 }}</td>
                        <td class="stat-label">Awaiting processing</td>
                    </tr>
                    <tr>
                        <td class="stat-label">Processing</td>
                        <td class="stat-value info">{{ $queueInfo->processing ?? 0 }}</td>
                        <td class="stat-label">Currently being worked on</td>
                    </tr>
                    <tr>
                        <td class="stat-label">Delayed</td>
                        <td class="stat-value warning">{{ $queueInfo->delayed ?? 0 }}</td>
                        <td class="stat-label">Scheduled for later</td>
                    </tr>
                    <tr>
                        <td class="stat-label">Failed</td>
                        <td class="stat-value danger">{{ $queueInfo->failed ?? 0 }}</td>
                        <td class="stat-label">Failed jobs</td>
                    </tr>
                </tbody>
            </table>

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