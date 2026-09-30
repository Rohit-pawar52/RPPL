<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="h-full bg-surface">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0b2e3f">
    <title>@yield('title', $branding->applicationName)</title>
    @if($branding->faviconUrl)
        <link rel="icon" href="{{ $branding->faviconUrl }}">
    @endif
    @include('layouts.partials.theme-vars')
    @vite(['resources/css/app.css', 'resources/js/push-notifications.js'])
</head>
<body class="pub-shell h-full text-[14px] text-slate-800 antialiased">
    <div class="flex min-h-full flex-col">
        @include('layouts.partials.announcement-ticker')
        @include('layouts.partials.public-header')

        <main class="mx-auto w-full max-w-6xl flex-1 px-4 py-5 lg:px-6 lg:py-6">
            @if(session('info'))
                <div class="mb-4 rounded-lg border border-sky-200 bg-sky-50 px-3 py-2 text-xs text-sky-800" role="status">
                    {{ session('info') }}
                </div>
            @endif

            @if(session('error'))
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-800" role="alert">
                    {{ session('error') }}
                </div>
            @endif

            @yield('content')
        </main>

        @include('layouts.partials.public-footer')
        @include('layouts.partials.push-soft-prompt')
    </div>
</body>
</html>
