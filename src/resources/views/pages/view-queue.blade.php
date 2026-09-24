<div class="fi-page">
    <div class="fi-page-header-main-ctn">
        <header class="fi-header">
            <x-filament::breadcrumbs :breadcrumbs="[
                \BlkDem\FilamentQueueMonitor\Filament\Pages\Dashboard::getUrl() => 'Queue Monitor',
                \BlkDem\FilamentQueueMonitor\Filament\Pages\Queues\ListQueues::getUrl() => 'Queues',
            ]" />

            <h1 class="fi-header-heading">Queue Details: {{ $queueInfo->name ?? $queue }}</h1>
            <p class="fi-header-subheading">Statistics for the selected queue</p>
        </header>
    </div>

    <div class="fi-page-main">
        <div class="fi-page-content">
            <div class="space-y-6">
                <x-filament::section
                    :collapsible="false"
                    heading="Statistics"
                >
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="rounded-xl p-4 bg-white dark:bg-gray-800 shadow">
                            <div class="text-sm text-gray-500 dark:text-gray-400">Pending</div>
                            <div class="text-2xl font-bold text-warning-600">{{ $queueInfo->pending ?? 0 }}</div>
                        </div>
                        <div class="rounded-xl p-4 bg-white dark:bg-gray-800 shadow">
                            <div class="text-sm text-gray-500 dark:text-gray-400">Processing</div>
                            <div class="text-2xl font-bold text-info-600">{{ $queueInfo->processing ?? 0 }}</div>
                        </div>
                        <div class="rounded-xl p-4 bg-white dark:bg-gray-800 shadow">
                            <div class="text-sm text-gray-500 dark:text-gray-400">Delayed</div>
                            <div class="text-2xl font-bold text-warning-600">{{ $queueInfo->delayed ?? 0 }}</div>
                        </div>
                        <div class="rounded-xl p-4 bg-white dark:bg-gray-800 shadow">
                            <div class="text-sm text-gray-500 dark:text-gray-400">Failed</div>
                            <div class="text-2xl font-bold text-danger-600">{{ $queueInfo->failed ?? 0 }}</div>
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
</div>