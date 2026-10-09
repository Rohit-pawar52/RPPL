<!DOCTYPE html>
<html lang="en" class="h-full bg-navy-950">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="{{ $branding->headerColor }}">
    <title>@yield('title', 'Admin') &middot; {{ $branding->shortName }} Admin</title>
    @if($branding->faviconUrl)
        <link rel="icon" href="{{ $branding->faviconUrl }}">
    @endif
    @include('layouts.partials.theme-vars')
    @include('layouts.partials.image-fallback')

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
<body class="h-full text-[13px] text-slate-800 antialiased">
    {{-- Sign-in and password pages: a calm navy backdrop with one white card. --}}
    <div class="ps-guest relative flex min-h-full items-center justify-center overflow-hidden px-4 py-10">
        <div class="relative w-full max-w-sm">
            <div class="mb-6 flex flex-col items-center gap-3 text-center">
                @if($branding->logoUrl)
                    <img src="{{ $branding->logoUrl }}" alt="{{ $branding->applicationName }}" class="h-14 w-14 rounded-2xl bg-white/10 object-contain p-1" />
                @else
                    <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-accent-dark text-2xl font-bold text-navy-950">
                        {{ Illuminate\Support\Str::substr($branding->shortName, 0, 1) }}
                    </span>
                @endif
                <div>
                    <p class="text-lg font-bold tracking-tight text-white">{{ $branding->shortName }} Admin</p>
                    <p class="mt-0.5 text-xs text-white/60">{{ $branding->applicationName }}</p>
                </div>
            </div>

            <div class="rounded-2xl bg-white p-6 shadow-pop sm:p-7">
                @yield('content')
            </div>

            <p class="mt-5 text-center">
                <a href="{{ route('public.home') }}" class="inline-flex items-center gap-1.5 text-xs font-medium text-white/60 transition-colors hover:text-white">
                    <x-icon name="arrow-left" class="h-3.5 w-3.5" />
                    Back to the website
                </a>
            </p>
        </div>
    </div>
</body>
</html>
