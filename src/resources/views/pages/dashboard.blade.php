<x-filament-panels::page class="fi-dashboard-page">
    <div class="flex justify-end">
        <div class="flex items-center gap-2">
            <x-filament::input.wrapper inline-prefix="period">
                <x-filament::input.select
                    wire:model.live="selectedPeriod"
                    class="w-44"
                >
                    @foreach ($periods as $value => $label)
                        <option value="{{ $value }}" @selected($this->selectedPeriod === $value)>
                            {{ $label }}
                        </option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>
    </div>
</x-filament-panels::page>