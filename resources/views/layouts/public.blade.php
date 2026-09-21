<!DOCTYPE html>
<html lang="en" class="h-full bg-neutral-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', $branding->applicationName)</title>
    @if($branding->faviconUrl)
        <link rel="icon" href="{{ $branding->faviconUrl }}">
    @endif
    @include('layouts.partials.theme-vars')
    @vite(['resources/css/app.css', 'resources/js/push-notifications.js'])
</head>
<body class="h-full text-[13px] text-neutral-800 antialiased">
    <div class="flex min-h-full flex-col">
        @include('layouts.partials.public-header')

        <main class="mx-auto w-full max-w-5xl flex-1 p-4 lg:p-6">
            @if(session('info'))
                <div class="mb-4 rounded-md border border-blue-100 bg-blue-50 px-3 py-2 text-xs text-blue-700">
                    {{ session('info') }}
                </div>
            @endif

            @if(session('error'))
                <div class="mb-4 rounded-md border border-red-100 bg-red-50 px-3 py-2 text-xs text-red-700">
                    {{ session('error') }}
                </div>
            @endif

            @yield('content')
        </main>

        @include('layouts.partials.public-footer')
    </div>
</body>
</html>
