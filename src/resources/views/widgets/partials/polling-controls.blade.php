<label class="fi-pagination-records-per-page-select">
    <x-filament::input.wrapper prefix="Polling">
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