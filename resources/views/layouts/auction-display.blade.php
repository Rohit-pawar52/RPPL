<!DOCTYPE html>
{{-- The projector layout for the auction (?display=big): no header, ticker
     or footer, a dark full-height stage. Used only by the public auction
     page. --}}
<html lang="{{ app()->getLocale() }}" class="h-full bg-slate-950">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#020617">
    <title>@yield('title', $branding->applicationName)</title>
    @if($branding->faviconUrl)
        <link rel="icon" href="{{ $branding->faviconUrl }}">
    @endif
    @include('layouts.partials.theme-vars')
    @include('layouts.partials.image-fallback')
    @vite(['resources/css/app.css'])
</head>
<body class="h-full bg-slate-950 text-white antialiased">
    <a
        href="{{ route('public.auction.show') }}"
        class="fixed right-3 top-3 z-10 rounded-md px-2 py-1 text-xs text-white/30 transition hover:bg-white/10 hover:text-white"
    >&times; {{ __('auction.back_to_page') }}</a>

    <main class="mx-auto flex min-h-full w-full max-w-[2200px] flex-col px-[clamp(1rem,1.6vw,2.5rem)] py-[clamp(0.75rem,1.2vw,1.75rem)] xl:h-screen xl:overflow-hidden">
        @yield('content')
    </main>
</body>
</html>
