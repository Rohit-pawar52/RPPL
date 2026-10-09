{{--
    The one friendly error page, on the public layout. Each status file
    (404, 403, 419, 429) extends this with its code and an icon:
        @extends('errors.layout', ['code' => 404, 'icon' => 'search', 'search' => true])
    Optional: 'retry' => true adds a "Try again" button (reload / go back).
    The visitor's saved language is applied first (see public-locale), because an
    unknown address is answered before the language middleware runs.
    500 and 503 are NOT on this layout: they must not depend on the database.
--}}
@include('layouts.partials.public-locale')
@extends('layouts.public')

@php
    $title = __('ux_public_shell.errors.'.$code.'.title');
    $signedIn = rescue(fn () => auth()->check(), false, false);
@endphp

@section('page_title', $title)

@section('content')
    <div class="mx-auto flex max-w-2xl flex-col items-center py-6 text-center sm:py-12">
        <p class="ps-error-code select-none" aria-hidden="true">{{ $code }}</p>
        <span class="relative -mt-4 flex h-16 w-16 items-center justify-center rounded-2xl bg-brand-soft text-brand shadow-card ring-8 ring-surface sm:-mt-6">
            <x-icon :name="$icon" class="h-8 w-8" />
        </span>

        <h1 class="mt-6 text-2xl font-bold tracking-tight text-slate-900 sm:text-3xl">{{ $title }}</h1>
        <p class="mt-2 max-w-md text-[15px] leading-relaxed text-slate-500">{{ __('ux_public_shell.errors.'.$code.'.message') }}</p>

        <div class="mt-7 flex w-full flex-col gap-2.5 sm:w-auto sm:flex-row sm:justify-center">
            <a href="{{ route('public.home') }}" class="btn btn-primary btn-lg">
                <x-icon name="home" class="h-5 w-5" />
                {{ __('ux_public_shell.errors.go_home') }}
            </a>
            @if(! empty($retry))
                <button type="button" onclick="window.history.length > 1 ? window.history.back() : window.location.reload()" class="btn btn-secondary btn-lg">
                    <x-icon name="refresh" class="h-5 w-5" />
                    {{ __('ux_public_shell.errors.try_again') }}
                </button>
            @else
                <a href="{{ route('public.matches.index') }}" class="btn btn-secondary btn-lg">
                    <x-icon name="calendar" class="h-5 w-5" />
                    {{ __('ux_public_shell.errors.see_matches') }}
                </a>
            @endif
            @if($signedIn)
                <a href="{{ route('admin.dashboard') }}" class="btn btn-ghost btn-lg">{{ __('ux_public_shell.errors.admin_back') }}</a>
            @endif
        </div>

        @if(! empty($search))
            <form method="GET" action="{{ route('public.players.index') }}" role="search" class="relative mt-8 w-full max-w-md text-left">
                <label for="error-search" class="pub-eyebrow mb-1.5 block">{{ __('ux_public_shell.errors.search_label') }}</label>
                <x-icon name="search" class="pointer-events-none absolute bottom-3.5 left-3.5 h-[18px] w-[18px] text-slate-400" />
                <input
                    id="error-search"
                    type="search"
                    name="search"
                    placeholder="{{ __('ux_public_shell.errors.search_placeholder') }}"
                    enterkeyhint="search"
                    class="h-12 w-full rounded-xl border border-line bg-white pl-10 pr-24 text-[15px] text-slate-900 shadow-card placeholder:text-slate-400 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20"
                />
                <button type="submit" class="btn btn-primary btn-sm absolute bottom-2 right-2">{{ __('ux_public_shell.errors.search_button') }}</button>
            </form>
        @endif

        <div class="mt-10 w-full">
            <p class="pub-eyebrow mb-3">{{ __('ux_public_shell.errors.popular') }}</p>
            <div class="grid grid-cols-2 gap-2.5 text-left sm:grid-cols-4">
                @foreach([
                    [route('public.matches.index'), __('public.nav.matches'), 'calendar'],
                    [route('public.teams.index'), __('public.nav.teams'), 'users'],
                    [route('public.players.index'), __('public.nav.players'), 'user'],
                    [route('public.player-registration.create'), __('ux_public_shell.nav.register'), 'user-plus'],
                ] as [$url, $label, $icon_name])
                    <a href="{{ $url }}" class="ps-tile">
                        <span class="ps-tile-icon"><x-icon :name="$icon_name" class="h-5 w-5" /></span>
                        <span class="min-w-0 flex-1 truncate">{{ $label }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
@endsection
