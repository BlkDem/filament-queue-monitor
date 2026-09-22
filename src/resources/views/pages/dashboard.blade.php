<div class="space-y-6">
    <div class="flex justify-between items-center">
        <h1 class="text-2xl font-semibold">Queue Monitor</h1>

        <div class="flex items-center gap-3">
            <label for="period" class="text-sm text-gray-600 dark:text-gray-400">
                Period:
            </label>
            <select
                id="period"
                wire:model.live="selectedPeriod"
                class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
            >
                @foreach ($periods as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <p class="text-sm text-gray-600 dark:text-gray-400">
        Overview of all queue connections and their health.
    </p>

    <div class="mt-4">
        {{ $this->getHeaderWidgets() }}
    </div>

    @if (config('filament-queue-monitor.metrics.enabled', true))
        <div class="mt-6">
            {{ $this->getFooterWidgets() }}
        </div>
    @endif
</div>
