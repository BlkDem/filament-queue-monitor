{{--
    Single page shell for the package.

    The other pages used to carry the filament v2 wrappers
    (fi-page-header-main-ctn, fi-page-main, fi-page-content), which no longer
    exist in filament 3 and rendered as unstyled divs. Spacing comes from
    py-8 on the section and gap-y-8 between the header and the content, the
    same as filament's own page component, so every page lines up.
--}}
@php
    $heading ??= null;
    $subheading ??= null;
    $backUrl ??= null;
    $backLabel ??= null;
    $content ??= null;
@endphp
<div class="fi-page">
    <section class="flex flex-col gap-y-8 py-8">
        <header class="fi-header flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                @if (filled($breadcrumbs ?? []))
                    <x-filament::breadcrumbs :breadcrumbs="$breadcrumbs" class="mb-2 hidden sm:block" />
                @endif

                @if (filled($heading))
                    <h1 class="fi-header-heading text-2xl font-bold tracking-tight text-gray-950 dark:text-white sm:text-3xl">
                        {{ $heading }}
                    </h1>
                @endif

                @if (filled($subheading))
                    <p class="fi-header-subheading mt-2 max-w-2xl text-lg text-gray-600 dark:text-gray-400">
                        {{ $subheading }}
                    </p>
                @endif
            </div>

            @if (filled($backUrl))
                {{-- mt-7 aligns the link with the heading rather than the breadcrumbs. --}}
                <div class="flex shrink-0 items-center gap-3 sm:mt-7">
                    <x-filament::link :href="$backUrl" icon="heroicon-o-arrow-left">
                        {{ $backLabel }}
                    </x-filament::link>
                </div>
            @endif
        </header>

        {{ $content }}
    </section>
</div>
