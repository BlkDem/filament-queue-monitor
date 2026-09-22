<div class="space-y-6">
    <h1 class="text-2xl font-semibold">Queue Details: {{ $queueInfo->name ?? $queue }}</h1>

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

    @if (isset($queueInfo->lastActivityAt))
        <div class="text-sm text-gray-500 dark:text-gray-400">
            Last Activity: {{ $queueInfo->lastActivityAt }}
        </div>
    @else
        <div class="text-sm text-gray-500 dark:text-gray-400">
            Last Activity: N/A
        </div>
    @endif

    <div class="mt-6">
        <a href="{{ \Filament\Pages\Page::getUrl(ListQueuedJobs::class, ['queue' => $queue]) }}" class="text-primary-600 hover:text-primary-500">
            View Queued Jobs &rarr;
        </a>
    </div>
</div>
