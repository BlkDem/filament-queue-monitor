@php
    use BlkDem\FilamentQueueMonitor\Support\Trans;
@endphp
<x-page-shell
    :breadcrumbs="[\BlkDem\FilamentQueueMonitor\Filament\Pages\Dashboard::getUrl() => Trans::get('navigation.label')]"
    :heading="Trans::get('failed_jobs.title')"
    :content="$this->getTable()"
/>
