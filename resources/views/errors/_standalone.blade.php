{{--
    A full, self-contained error page for failures where the database or the
    settings may be what broke (500, 503): no layout composers, no settings
    reads - only translations, the compiled stylesheet and fixed colours from
    its own fallbacks. Expects $code and $icon.
--}}
@include('layouts.partials.public-locale')
<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="h-full bg-surface">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>{{ __('ux_public_shell.errors.'.$code.'.title') }} &middot; {{ config('app.name') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="h-full bg-surface text-[14px] text-slate-800 antialiased">
    <main class="flex min-h-full items-center justify-center px-4 py-10">
        <div class="w-full max-w-lg text-center">
            <p class="ps-error-code select-none" aria-hidden="true">{{ $code }}</p>
            <span class="relative -mt-4 inline-flex h-16 w-16 items-center justify-center rounded-2xl bg-brand-soft text-brand shadow-card ring-8 ring-surface sm:-mt-6">
                <x-icon :name="$icon" class="h-8 w-8" />
            </span>

            <h1 class="mt-6 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ __('ux_public_shell.errors.'.$code.'.title') }}</h1>
            <p class="mx-auto mt-2 max-w-md text-[15px] leading-relaxed text-slate-500">{{ __('ux_public_shell.errors.'.$code.'.message') }}</p>

            <div class="mt-7 flex flex-col gap-2.5 sm:flex-row sm:justify-center">
                <button type="button" onclick="window.location.reload()" class="btn btn-primary btn-lg">
                    <x-icon name="refresh" class="h-5 w-5" />
                    {{ __('ux_public_shell.errors.try_again') }}
                </button>
                <a href="{{ url('/') }}" class="btn btn-secondary btn-lg">
                    <x-icon name="home" class="h-5 w-5" />
                    {{ __('ux_public_shell.errors.go_home') }}
                </a>
            </div>

            <p class="mt-8 text-xs text-slate-400">{{ config('app.name') }}</p>
        </div>
    </main>
</body>
</html>
