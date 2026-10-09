<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="h-full bg-surface motion-safe:scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="{{ $branding->headerColor }}">
    <meta name="description" content="@yield('meta_description', $branding->tagline ?: $branding->applicationName)">
    @php
        // Pages set either a full 'title', or just a 'page_title' (the error pages),
        // which gets the site name appended. Section values are already escaped.
        $pageTitle = trim($__env->yieldContent('page_title'));
    @endphp
    <title>{!! $pageTitle !== '' ? $pageTitle.' &middot; '.e($branding->applicationName) : $__env->yieldContent('title', $branding->applicationName) !!}</title>
    @if($branding->faviconUrl)
        <link rel="icon" href="{{ $branding->faviconUrl }}">
    @endif
    @include('layouts.partials.theme-vars')
    @include('layouts.partials.image-fallback')
    @vite(['resources/css/app.css', 'resources/js/push-notifications.js'])
</head>
<body class="pub-shell h-full text-[14px] text-slate-800 antialiased">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-3 focus:top-3 focus:z-[60] focus:rounded-lg focus:bg-white focus:px-4 focus:py-2.5 focus:text-sm focus:font-semibold focus:text-slate-900 focus:shadow-pop">
        {{ __('ux_public_shell.skip_to_content') }}
    </a>

    <div class="flex min-h-full flex-col">
        @include('layouts.partials.announcement-ticker')
        @include('layouts.partials.public-header')

        <main id="main" class="mx-auto w-full max-w-6xl flex-1 px-4 py-5 sm:py-6 lg:px-6 lg:py-8">
            @php
                $flashes = [
                    'info' => ['border-sky-200 bg-sky-50 text-sky-800', 'info', 'status'],
                    'success' => ['border-green-200 bg-green-50 text-green-800', 'check-circle', 'status'],
                    'warning' => ['border-amber-200 bg-amber-50 text-amber-800', 'alert', 'alert'],
                    'error' => ['border-red-200 bg-red-50 text-red-800', 'alert', 'alert'],
                ];
            @endphp
            @foreach($flashes as $key => [$tone, $icon, $role])
                @if(session($key))
                    <div class="mb-4 flex items-start gap-2.5 rounded-xl border px-3.5 py-3 text-[13px] leading-snug {{ $tone }}" role="{{ $role }}">
                        <x-icon :name="$icon" class="mt-px h-[18px] w-[18px] shrink-0" />
                        <p class="min-w-0">{{ session($key) }}</p>
                    </div>
                @endif
            @endforeach

            @yield('content')
        </main>

        @include('layouts.partials.public-footer')
        @include('layouts.partials.push-soft-prompt')
    </div>

    @stack('scripts')
</body>
</html>
