@include('layouts.partials.public-locale')
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="h-full bg-navy-950">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="{{ $branding->headerColor }}">
    <meta name="robots" content="noindex">
    {{-- Comes back by itself: the page checks again every minute. --}}
    <meta http-equiv="refresh" content="60">
    <title>{{ $branding->applicationName }} &middot; {{ __('ux_public_shell.maintenance.title') }}</title>
    @if($branding->faviconUrl)
        <link rel="icon" href="{{ $branding->faviconUrl }}">
    @endif
    @include('layouts.partials.theme-vars')
    @include('layouts.partials.image-fallback')
    @vite(['resources/css/app.css'])
</head>
<body class="h-full text-[14px] text-slate-800 antialiased">
    <main class="ps-guest flex min-h-full items-center justify-center px-4 py-10">
        <div class="w-full max-w-md text-center">
            <div class="mb-6 flex justify-center">
                @if($branding->logoUrl)
                    <img
                        src="{{ $branding->logoUrl }}"
                        alt="{{ $branding->applicationName }}"
                        class="h-16 w-16 rounded-2xl bg-white/10 object-contain p-1"
                    />
                @else
                    <span class="flex h-16 w-16 items-center justify-center rounded-2xl bg-accent-dark text-2xl font-bold text-navy-950">
                        {{ Illuminate\Support\Str::substr($branding->shortName, 0, 1) }}
                    </span>
                @endif
            </div>

            <p class="text-sm font-semibold tracking-tight text-white/70">{{ $branding->applicationName }}</p>

            <div class="mt-5 rounded-2xl bg-white p-6 shadow-pop sm:p-8">
                <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-soft text-brand">
                    <x-icon name="wrench" class="h-7 w-7" />
                </span>
                <h1 class="mt-4 text-2xl font-bold tracking-tight text-slate-900">{{ __('ux_public_shell.maintenance.heading') }}</h1>
                <p class="mt-2 text-[15px] leading-relaxed text-slate-600">
                    {{ $message ?: __('ux_public_shell.maintenance.default_message') }}
                </p>

                <button type="button" onclick="window.location.reload()" class="btn btn-primary btn-lg mt-6">
                    <x-icon name="refresh" class="h-5 w-5" />
                    {{ __('ux_public_shell.maintenance.check_again') }}
                </button>
                <p class="mt-3 text-xs text-slate-400">{{ __('ux_public_shell.maintenance.auto_refresh') }}</p>
            </div>
        </div>
    </main>
</body>
</html>
