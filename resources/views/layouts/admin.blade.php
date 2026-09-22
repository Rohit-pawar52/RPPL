<!DOCTYPE html>
<html lang="en" class="h-full bg-neutral-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard') &middot; {{ $branding->shortName }} Admin</title>
    @if($branding->faviconUrl)
        <link rel="icon" href="{{ $branding->faviconUrl }}">
    @endif
    @include('layouts.partials.theme-vars')

    @php
        $flash = [
            'success' => session('success'),
            'error' => session('error'),
            'warning' => session('warning'),
            'info' => session('info'),
        ];
    @endphp
    <script>
        window.flash = @json($flash);
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full text-[13px] text-neutral-800 antialiased">
    <div class="flex min-h-full flex-col">
        @include('layouts.partials.admin-header')

        <div class="flex flex-1">
            @include('layouts.partials.admin-sidebar')

            <main class="min-w-0 flex-1 p-4 lg:p-6">
                <h1 class="mb-4 text-xl font-semibold text-neutral-900">@yield('title', 'Dashboard')</h1>

                @yield('content')
            </main>
        </div>

        @include('layouts.partials.admin-footer')
    </div>
</body>
</html>
