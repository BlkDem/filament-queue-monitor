@php
    use BlkDem\FilamentQueueMonitor\Support\Trans;
@endphp
<label class="fi-pagination-records-per-page-select">
    <x-filament::input.wrapper :prefix="Trans::get('periods.label')">
        <x-filament::input.select wire:model.live="selectedPeriod">
            @foreach ($periods as $value => $label)
                <option value="{{ $value }}" @selected($selectedPeriod === $value)>
                    {{ $label }}
                </option>
            @endforeach
        </x-filament::input.select>
    </x-filament::input.wrapper>
</label>

@include('filament-queue-monitor::widgets.partials.polling-controls')
