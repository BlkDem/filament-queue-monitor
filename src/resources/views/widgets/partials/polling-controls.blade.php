@php
    use BlkDem\FilamentQueueMonitor\Support\Trans;
@endphp
<label class="fi-pagination-records-per-page-select">
    <x-filament::input.wrapper :prefix="Trans::get('polling.label')">
        <x-filament::input.select wire:model.live="pollingInterval">
            <option value="">
                {{ $defaultInterval > 0
                    ? Trans::get('polling.default', ['seconds' => $defaultInterval])
                    : Trans::get('polling.default_off') }}
            </option>
            <option value="5s">{{ Trans::get('polling.5s') }}</option>
            <option value="10s">{{ Trans::get('polling.10s') }}</option>
            <option value="30s">{{ Trans::get('polling.30s') }}</option>
            <option value="60s">{{ Trans::get('polling.60s') }}</option>
            <option value="off">{{ Trans::get('polling.off') }}</option>
        </x-filament::input.select>
    </x-filament::input.wrapper>
</label>
