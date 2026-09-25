@php
    use BlkDem\FilamentQueueMonitor\Support\Trans;
@endphp
<div class="fi-page">
    <div class="fi-page-header-main-ctn">
        <header class="fi-header">
            <div>
                <x-filament::breadcrumbs :breadcrumbs="[
                    \BlkDem\FilamentQueueMonitor\Filament\Pages\Dashboard::getUrl() => Trans::get('navigation.label'),
                ]" />

                <h1 class="fi-header-heading">{{ Trans::get('queues.title') }}</h1>
            </div>
        </header>
    </div>

    <div class="fi-page-main">
        <div class="fi-page-content">
            {{ $this->getTable() }}
        </div>
    </div>
</div>
