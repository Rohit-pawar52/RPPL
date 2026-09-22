<!DOCTYPE html>
<html lang="en" class="h-full bg-neutral-50">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Admin') &middot; {{ $branding->shortName }} Admin</title>
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
    <div class="flex min-h-full items-center justify-center px-4 py-10">
        <div class="w-full max-w-sm">
            <div class="mb-6 flex items-center justify-center gap-2">
                @if($branding->logoUrl)
                    <img src="{{ $branding->logoUrl }}" alt="{{ $branding->applicationName }}" class="h-8 w-8 rounded-md object-contain" />
                @else
                    <span class="theme-primary-bg flex h-8 w-8 items-center justify-center rounded-md text-sm font-bold">
                        {{ Illuminate\Support\Str::substr($branding->shortName, 0, 1) }}
                    </span>
                @endif
                <span class="text-base font-semibold text-neutral-900">{{ $branding->shortName }} Admin</span>
            </div>

            <div class="rounded-lg border border-neutral-200 bg-white p-6 shadow-sm">
                @yield('content')
            </div>
        </div>
    </div>
</body>
</html>
