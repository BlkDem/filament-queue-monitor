@php
    use Filament\Support\Facades\FilamentView;
    // Laravel's bag, not Filament\Support\View\ComponentAttributeBag: that one
    // only exists in Filament 4, and on Filament 3 the widget took the whole
    // panel dashboard down with a missing class error.
    use Illuminate\View\ComponentAttributeBag;
    use Filament\Widgets\View\WidgetsRenderHook;

    $requiresGroupCollapseInit = ! method_exists(\Filament\Tables\Table\Concerns\CanGroupRecords::class, 'collapsedGroupsByDefault');
    $pollingInterval = $this->getPollingInterval();
@endphp

<x-filament-widgets::widget
    class="fi-wi-table"
    data-queue-monitor-group-table
    mb-2
    :attributes="
        (new ComponentAttributeBag)
            ->merge([
                'wire:poll.' . $pollingInterval => $pollingInterval ? true : null,
            ], escape: false)
    "
>
    {{ FilamentView::renderHook(WidgetsRenderHook::TABLE_WIDGET_START, scopes: static::class) }}

    {{ $this->table }}

    @if ($requiresGroupCollapseInit)
        @include('filament-queue-monitor::widgets.partials.group-collapse-init')
    @endif

    {{ FilamentView::renderHook(WidgetsRenderHook::TABLE_WIDGET_END, scopes: static::class) }}
</x-filament-widgets::widget>