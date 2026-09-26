@php
    use BlkDem\FilamentQueueMonitor\Support\Trans;
@endphp
<div class="fi-page">
    <section class="flex flex-col gap-y-8 py-8">
        <header class="fi-header flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <x-filament::breadcrumbs :breadcrumbs="[
                    \BlkDem\FilamentQueueMonitor\Filament\Pages\Dashboard::getUrl() => Trans::get('navigation.label'),
                ]" class="mb-2 hidden sm:block" />

                <h1 class="fi-header-heading text-2xl font-bold tracking-tight text-gray-950 dark:text-white sm:text-3xl">
                    {{ Trans::get('completed_jobs.title') }}
                </h1>

                <p class="fi-header-subheading mt-2 max-w-2xl text-lg text-gray-600 dark:text-gray-400">
                    {{ Trans::get('completed_jobs.subtitle') }}
                </p>
            </div>
        </header>

        <div class="fi-page-main">
            <div class="fi-page-content">
                {{ $this->getTable() }}
            </div>
        </div>
    </section>
</div>
