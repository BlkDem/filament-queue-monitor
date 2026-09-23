@php
    use Filament\Support\Facades\FilamentView;
    use Filament\Widgets\View\WidgetsRenderHook;

    $requiresGroupCollapseInit = ! method_exists(\Filament\Tables\Table\Concerns\CanGroupRecords::class, 'collapsedGroupsByDefault');
@endphp

<x-filament-widgets::widget class="fi-wi-table" data-queue-monitor-group-table mb-2>
    {{ FilamentView::renderHook(WidgetsRenderHook::TABLE_WIDGET_START, scopes: static::class) }}

    <div class="flex items-center justify-end gap-x-3 px-4 pt-3">
        <label class="inline-flex items-center gap-x-2 text-sm font-medium text-gray-500 dark:text-gray-400">
            <span>Polling</span>

            @php
                $defaultInterval = (int) config('filament-queue-monitor.metrics.refresh_interval', 30);
            @endphp

            <x-filament::input.wrapper class="w-36">
                <x-filament::input.select wire:model.live="pollingInterval">
                    <option value="">Default{{ $defaultInterval > 0 ? ' (' . $defaultInterval . 's)' : ' (off)' }}</option>
                    <option value="5s">5 seconds</option>
                    <option value="10s">10 seconds</option>
                    <option value="30s">30 seconds</option>
                    <option value="60s">60 seconds</option>
                    <option value="off">Off</option>
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </label>
    </div>

    {{ $this->table }}

    @if ($requiresGroupCollapseInit)
        @include('filament-queue-monitor::widgets.partials.group-collapse-init')
    @endif

    {{ FilamentView::renderHook(WidgetsRenderHook::TABLE_WIDGET_END, scopes: static::class) }}
</x-filament-widgets::widget>