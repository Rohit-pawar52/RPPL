<!DOCTYPE html>
{{-- The projector layout for the auction (?display=big): no header, ticker
     or footer, a dark full-height stage with a soft glow of the brand colour
     behind it. Used only by the public auction page. --}}
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
<body class="relative h-full overflow-x-hidden bg-slate-950 text-white antialiased">
    {{-- Soft brand glow, only decoration. --}}
    <div class="pointer-events-none fixed inset-0 z-0 overflow-hidden" aria-hidden="true">
        <div class="absolute -right-40 -top-48 h-[44rem] w-[44rem] rounded-full bg-brand/20 blur-3xl"></div>
        <div class="absolute -bottom-56 -left-40 h-[40rem] w-[40rem] rounded-full bg-navy-700/30 blur-3xl"></div>
    </div>

    <a
        href="{{ route('public.auction.show') }}"
        class="fixed bottom-3 right-3 z-20 rounded-lg px-2.5 py-1.5 text-xs font-medium text-white/40 transition hover:bg-white/10 hover:text-white focus-visible:outline-2 focus-visible:outline-accent-dark"
    >&times; {{ __('auction.back_to_page') }}</a>

    <main class="relative z-10 mx-auto flex min-h-full w-full max-w-[2200px] flex-col px-[clamp(1rem,1.6vw,2.5rem)] py-[clamp(0.75rem,1.2vw,1.75rem)] xl:h-screen xl:overflow-hidden">
        @yield('content')
    </main>
</body>
</html>
