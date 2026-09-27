@php
    use BlkDem\FilamentQueueMonitor\Support\Trans;
@endphp
<x-page-shell
    :breadcrumbs="[
        \BlkDem\FilamentQueueMonitor\Filament\Pages\Dashboard::getUrl() => Trans::get('navigation.label'),
        \BlkDem\FilamentQueueMonitor\Filament\Pages\Queues\ListQueues::getUrl() => Trans::get('navigation.queues'),
    ]"
    :heading="Trans::get('queue_details.title', ['name' => $queueInfo->name ?? $queue])"
    :subheading="Trans::get('queue_details.subheading')"
>
    <x-slot name="content">
        <x-filament::section
            :collapsible="false"
            :heading="Trans::get('queue_details.section_statistics')"
        >
            {{--
                The stat cards carry filament's own fi-wi-stats-overview-stat-*
                classes, which the compiled theme styles in full through its
                design tokens, so nothing here depends on the host app's
                tailwind build. The grid is inline for the same reason:
                fi-wi-stats-overview-stats-ctn has no rule in the theme, and a
                tailwind grid class would only work if the app scanned this
                package, which it does not.
            --}}
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                @foreach ([
                    ['label' => 'stats.pending', 'value' => $queueInfo->pending ?? 0, 'description' => 'stats.description.awaiting_processing'],
                    ['label' => 'stats.processing', 'value' => $queueInfo->processing ?? 0, 'description' => 'stats.description.currently_working'],
                    ['label' => 'stats.delayed', 'value' => $queueInfo->delayed ?? 0, 'description' => 'stats.description.scheduled_later'],
                    ['label' => 'stats.failed', 'value' => $queueInfo->failed ?? 0, 'description' => 'stats.description.failed_count'],
                ] as $stat)
                    <div class="fi-wi-stats-overview-stat">
                        <div class="fi-wi-stats-overview-stat-label">
                            {{ Trans::get($stat['label']) }}
                        </div>

                        <div class="fi-wi-stats-overview-stat-value">
                            {{ $stat['value'] }}
                        </div>

                        <div class="fi-wi-stats-overview-stat-description">
                            {{ Trans::get($stat['description']) }}
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>

        <x-filament::section
            :collapsible="false"
            :heading="Trans::get('queue_details.section_details')"
        >
            <dl style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin: 0;">
                <div>
                    <dt style="font-size: 12px; font-weight: 500; opacity: 0.7;">{{ Trans::get('queue_details.last_activity') }}</dt>
                    <dd style="margin: 3px 0 0; font-size: 13px; overflow-wrap: anywhere;">
                        {{ isset($queueInfo->lastActivityAt) ? $queueInfo->lastActivityAt->format('Y-m-d H:i:s') : Trans::get('queue_details.not_available') }}
                    </dd>
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
    </x-slot>
</x-page-shell>
