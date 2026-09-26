{{--
    Single page shell for the package, mirroring filament's own page layout:
    fi-page-header-main-ctn carries the top and bottom padding, fi-page-main
    separates the header from the content, and fi-page-content lays the
    content out as a grid.

    These are filament 3 classes and do have styles in the compiled theme.
--}}
@php
    $heading ??= null;
    $subheading ??= null;
    $backUrl ??= null;
    $backLabel ??= null;
    $content ??= null;
@endphp
<div class="fi-page">
    <div class="fi-page-header-main-ctn">
        <header
            @class([
                'fi-header',
                'fi-header-has-subheading' => filled($subheading),
            ])
        >
            <div>
                @if (filled($breadcrumbs ?? []))
                    <x-filament::breadcrumbs :breadcrumbs="$breadcrumbs" />
                @endif

                @if (filled($heading))
                    <h1 class="fi-header-heading">
                        {{ $heading }}
                    </h1>
                @endif

                @if (filled($subheading))
                    <p class="fi-header-subheading">
                        {{ $subheading }}
                    </p>
                @endif
            </div>

            @if (filled($backUrl))
                <div class="fi-header-actions-ctn">
                    <x-filament::link :href="$backUrl" icon="heroicon-o-arrow-left">
                        {{ $backLabel }}
                    </x-filament::link>
                </div>
            @endif
        </header>
    </div>

    <div class="fi-page-main">
        <div class="fi-page-content">
            {{ $content }}
        </div>
    </div>
</div>
