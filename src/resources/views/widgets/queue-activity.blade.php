@php
    use Filament\Support\Facades\FilamentView;
    use Filament\Support\View\ComponentAttributeBag;
    use Filament\Widgets\View\WidgetsRenderHook;

    $requiresGroupCollapseInit = ! method_exists(\Filament\Tables\Table\Concerns\CanGroupRecords::class, 'collapsedGroupsByDefault');
    $pollingInterval = $this->getPollingInterval();
@endphp

<x-filament-widgets::widget
    class="fi-wi-table"
    data-queue-monitor-group-table
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