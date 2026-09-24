<div class="fi-page">
    <div class="fi-page-header-main-ctn">
        <header class="fi-header">
            <x-filament::breadcrumbs :breadcrumbs="[
                \BlkDem\FilamentQueueMonitor\Filament\Pages\Dashboard::getUrl() => 'Queue Monitor',
            ]" />

            <h1 class="fi-header-heading">Failed Jobs</h1>
        </header>
    </div>

    <div class="fi-page-main">
        <div class="fi-page-content">
            {{ $this->getTable() }}
        </div>
    </div>
</div>