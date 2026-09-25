@php
    use BlkDem\FilamentQueueMonitor\Support\Trans;
@endphp
<div class="fi-page">
    <div class="fi-page-header-main-ctn">
        <header class="fi-header">
            <div>
                <x-filament::breadcrumbs :breadcrumbs="[
                    \BlkDem\FilamentQueueMonitor\Filament\Pages\Dashboard::getUrl() => Trans::get('navigation.label'),
                    \BlkDem\FilamentQueueMonitor\Filament\Pages\Queues\ListQueues::getUrl() => Trans::get('navigation.queues'),
                ]" />

                <h1 class="fi-header-heading">{{ Trans::get('queue_details.title', ['name' => $queueInfo->name ?? $queue]) }}</h1>
                <p class="fi-header-subheading">{{ Trans::get('queue_details.subheading') }}</p>
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
                :heading="Trans::get('queue_details.section_statistics')"
            >
                <div class="fqm-stats-grid">
                    <div class="fi-so-stat rounded-xl p-5 bg-white dark:bg-gray-800 shadow ring-1 ring-gray-950/5 dark:ring-white/10">
                        <div class="fi-so-stat-label text-sm text-gray-500 dark:text-gray-400">{{ Trans::get('stats.pending') }}</div>
                        <div class="fi-so-stat-value mt-1 text-3xl font-bold text-warning-600 dark:text-warning-400">{{ $queueInfo->pending ?? 0 }}</div>
                        <div class="fi-so-stat-description mt-2 text-xs text-gray-500 dark:text-gray-400">{{ Trans::get('stats.description.awaiting_processing') }}</div>
                    </div>
                    <div class="fi-so-stat rounded-xl p-5 bg-white dark:bg-gray-800 shadow ring-1 ring-gray-950/5 dark:ring-white/10">
                        <div class="fi-so-stat-label text-sm text-gray-500 dark:text-gray-400">{{ Trans::get('stats.processing') }}</div>
                        <div class="fi-so-stat-value mt-1 text-3xl font-bold text-info-600 dark:text-info-400">{{ $queueInfo->processing ?? 0 }}</div>
                        <div class="fi-so-stat-description mt-2 text-xs text-gray-500 dark:text-gray-400">{{ Trans::get('stats.description.currently_working') }}</div>
                    </div>
                    <div class="fi-so-stat rounded-xl p-5 bg-white dark:bg-gray-800 shadow ring-1 ring-gray-950/5 dark:ring-white/10">
                        <div class="fi-so-stat-label text-sm text-gray-500 dark:text-gray-400">{{ Trans::get('stats.delayed') }}</div>
                        <div class="fi-so-stat-value mt-1 text-3xl font-bold text-warning-600 dark:text-warning-400">{{ $queueInfo->delayed ?? 0 }}</div>
                        <div class="fi-so-stat-description mt-2 text-xs text-gray-500 dark:text-gray-400">{{ Trans::get('stats.description.scheduled_later') }}</div>
                    </div>
                    <div class="fi-so-stat rounded-xl p-5 bg-white dark:bg-gray-800 shadow ring-1 ring-gray-950/5 dark:ring-white/10">
                        <div class="fi-so-stat-label text-sm text-gray-500 dark:text-gray-400">{{ Trans::get('stats.failed') }}</div>
                        <div class="fi-so-stat-value mt-1 text-3xl font-bold text-danger-600 dark:text-danger-400">{{ $queueInfo->failed ?? 0 }}</div>
                        <div class="fi-so-stat-description mt-2 text-xs text-gray-500 dark:text-gray-400">{{ Trans::get('stats.description.failed_count') }}</div>
                    </div>
                </div>
            </x-filament::section>

            <x-filament::section
                :collapsible="false"
                :heading="Trans::get('queue_details.section_details')"
            >
                <dl class="space-y-4">
                    <div class="flex justify-between">
                        <dt class="text-sm text-gray-500 dark:text-gray-400">{{ Trans::get('queue_details.last_activity') }}</dt>
                        <dd class="text-sm font-medium">{{ isset($queueInfo->lastActivityAt) ? $queueInfo->lastActivityAt->format('Y-m-d H:i:s') : Trans::get('queue_details.not_available') }}</dd>
                    </div>
                </dl>
            </x-filament::section>

            <x-filament::actions>
                <x-filament::link
                    :href="\BlkDem\FilamentQueueMonitor\Filament\Pages\Jobs\ListJobs::getUrl(['queue' => $queue])"
                    icon="heroicon-o-arrow-right-end-on-rectangle"
                >
                    {{ Trans::get('queue_details.view_jobs') }}
                </x-filament::link>
            </x-filament::actions>
        </div>
    </div>
</div>
