@extends('layouts.public')

@section('title', $branding->shortName.' · '.$branding->applicationName)

{{--
    Public homepage — compact, match-first. Top to bottom: sponsor banner →
    one scrolling row of match cards (live/next, a sponsor card, latest
    results) → Videos / News / Photos cards → sponsor banner → the current
    season's summary (points table + top few of each stats board) → sponsor
    logos. Every sponsor slot renders nothing when no sponsor is live, so an
    empty slot never leaves a gap. Purely presentational: every score,
    standing and stat comes pre-computed from HomeController — nothing is
    recalculated here. The edition itself is reached from the navbar.
--}}
@section('content')
    <x-ad-slot tier="main" />

    @include('public.home._auction-card')

    @if(! $edition)
        <div class="pub-card mt-4 px-4 py-8 text-center">
            <x-icon name="trophy" class="mx-auto h-5 w-5 text-slate-300" />
            <p class="mt-2 text-[13px] text-slate-600">{{ __('matches.home.no_editions') }}</p>
            <p class="pub-meta mt-0.5">{{ __('matches.home.no_editions_hint') }}</p>
        </div>
    @else
        @include('public.home._match-row')
    @endif

    @include('public.home._media-cards')

    @if($edition)
        <x-ad-slot tier="normal" class="mt-4" />

        @include('public.home._season-summary')
    @endif

    <x-ad-slot tier="mini" class="mt-4" />
@endsection
