@extends('layouts.public')

@section('title', $branding->shortName.' · '.$branding->applicationName)

{{--
    Public homepage - "what is on right now?" first. Top to bottom: sponsor
    banner -> auction
    banner (only while an auction is on) -> the match row (live / next matches,
    a sponsor card, latest results) -> sponsor banner -> the season summary
    (points table + top players) -> latest news / photos / videos -> sponsor
    logos. Every sponsor slot renders nothing when no sponsor is live, so an
    empty slot never leaves a gap. Purely presentational: every score,
    standing and stat comes pre-computed from HomeController - nothing is
    recalculated here. The edition itself is reached from the navbar.
--}}
@section('content')
    <div class="space-y-5 lg:space-y-8">
        <x-ad-slot tier="main" />

        @include('public.home._auction-card')

        @if(! $edition)
            <div class="pub-card px-4 py-10 text-center">
                <x-public.empty icon="trophy">
                    {{ __('matches.home.no_editions') }}
                    <span class="mt-1 block text-xs text-slate-400">{{ __('matches.home.no_editions_hint') }}</span>
                </x-public.empty>
            </div>
        @else
            @include('public.home._match-row')
        @endif

        @if($edition)
            <x-ad-slot tier="normal" />

            @include('public.home._season-summary')
        @endif

        @include('public.home._media-cards')

        <x-ad-slot tier="mini" />

        @include('public.home._contributors')
    </div>
@endsection
