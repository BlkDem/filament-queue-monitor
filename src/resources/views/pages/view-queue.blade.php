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
                .fqm-stats-grid { display: grid; grid-template-columns: repeat(1, 1fr); gap: 16px; }
                @media (min-width: 640px) { .fqm-stats-grid { grid-template-columns: repeat(2, 1fr); } }
                @media (min-width: 1024px) { .fqm-stats-grid { grid-template-columns: repeat(4, 1fr); } }
            </style>

            <x-filament::section
                :collapsible="false"
                heading="Statistics"
            >
                <div class="fqm-stats-grid">
                    <div class="fi-so-stat rounded-xl p-5 bg-white dark:bg-gray-800 shadow ring-1 ring-gray-950/5 dark:ring-white/10">
                        <div class="fi-so-stat-label text-sm text-gray-500 dark:text-gray-400">Pending</div>
                        <div class="fi-so-stat-value mt-1 text-3xl font-bold text-warning-600 dark:text-warning-400">{{ $queueInfo->pending ?? 0 }}</div>
                        <div class="fi-so-stat-description mt-2 text-xs text-gray-500 dark:text-gray-400">Awaiting processing</div>
                    </div>
                    <div class="fi-so-stat rounded-xl p-5 bg-white dark:bg-gray-800 shadow ring-1 ring-gray-950/5 dark:ring-white/10">
                        <div class="fi-so-stat-label text-sm text-gray-500 dark:text-gray-400">Processing</div>
                        <div class="fi-so-stat-value mt-1 text-3xl font-bold text-info-600 dark:text-info-400">{{ $queueInfo->processing ?? 0 }}</div>
                        <div class="fi-so-stat-description mt-2 text-xs text-gray-500 dark:text-gray-400">Currently being worked on</div>
                    </div>
                    <div class="fi-so-stat rounded-xl p-5 bg-white dark:bg-gray-800 shadow ring-1 ring-gray-950/5 dark:ring-white/10">
                        <div class="fi-so-stat-label text-sm text-gray-500 dark:text-gray-400">Delayed</div>
                        <div class="fi-so-stat-value mt-1 text-3xl font-bold text-warning-600 dark:text-warning-400">{{ $queueInfo->delayed ?? 0 }}</div>
                        <div class="fi-so-stat-description mt-2 text-xs text-gray-500 dark:text-gray-400">Scheduled for later</div>
                    </div>
                    <div class="fi-so-stat rounded-xl p-5 bg-white dark:bg-gray-800 shadow ring-1 ring-gray-950/5 dark:ring-white/10">
                        <div class="fi-so-stat-label text-sm text-gray-500 dark:text-gray-400">Failed</div>
                        <div class="fi-so-stat-value mt-1 text-3xl font-bold text-danger-600 dark:text-danger-400">{{ $queueInfo->failed ?? 0 }}</div>
                        <div class="fi-so-stat-description mt-2 text-xs text-gray-500 dark:text-gray-400">Failed jobs</div>
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