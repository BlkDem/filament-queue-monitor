<div class="fi-page">
    <header class="fi-header">
        <x-filament::breadcrumbs :breadcrumbs="[
            \BlkDem\FilamentQueueMonitor\Filament\Pages\Dashboard::getUrl() => 'Queue Monitor',
        ]" />

        <h1 class="fi-header-heading">Jobs</h1>
    </header>

    {{ $this->getTable() }}
</div>