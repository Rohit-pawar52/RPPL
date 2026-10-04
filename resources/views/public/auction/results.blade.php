@extends('layouts.public')

@section('title', __('auction.results').' · '.$state['auction']['edition'].' · '.$branding->shortName)

{{--
    The finished auction: who bought whom, for how many points. Plain HTML
    (no script). Expects $state from AuctionStateService::public().
--}}
@section('content')
    @php
        $results = $state['results'];
        $top = $results['top_buys'][0] ?? null;
    @endphp

    <x-public.page-header :title="$state['auction']['edition'].' · '.__('auction.results')" :subtitle="__('auction.completed')" />

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <div class="pub-card p-4">
            <p class="pub-eyebrow">{{ __('auction.players_sold') }}</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-slate-900">{{ $results['sold_count'] }}</p>
        </div>
        <div class="pub-card p-4">
            <p class="pub-eyebrow">{{ __('auction.points_spent') }}</p>
            <p class="mt-1 text-2xl font-bold tabular-nums text-slate-900">{{ points($results['points_spent']) }}</p>
        </div>
        <div class="pub-card p-4">
            <p class="pub-eyebrow">{{ __('auction.most_expensive') }}</p>
            @if($top)
                <p class="mt-1 text-2xl font-bold tabular-nums text-slate-900">{{ points($top['amount']) }}</p>
                <p class="pub-meta mt-0.5 truncate">{{ $top['name'] }} &middot; {{ $top['team'] }}</p>
            @else
                <p class="mt-1 text-2xl font-bold text-slate-300">—</p>
            @endif
        </div>
    </div>

    @if(count($results['top_buys']) > 1)
        <x-public.card class="mt-4" flush :title="__('auction.top_buys')">
            <ol class="divide-y divide-line">
                @foreach($results['top_buys'] as $buy)
                    <li class="flex items-center gap-3 px-4 py-2.5 text-[13px]">
                        <span class="w-5 text-center text-xs tabular-nums text-slate-400">{{ $loop->iteration }}</span>
                        <span class="min-w-0 flex-1 truncate font-medium text-slate-900">{{ $buy['name'] }}</span>
                        <span class="hidden truncate text-slate-500 sm:block">{{ $buy['team'] }}</span>
                        <span class="shrink-0 font-bold tabular-nums text-slate-900">{{ points($buy['amount']) }} <span class="text-[11px] font-normal text-slate-400">{{ __('auction.pts') }}</span></span>
                    </li>
                @endforeach
            </ol>
        </x-public.card>
    @endif

    <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach($state['teams'] as $team)
            <x-public.card flush>
                <header class="pub-card-head">
                    <h2 class="pub-card-title truncate">{{ $team['name'] }}</h2>
                    <span class="pub-meta shrink-0 tabular-nums">{{ __('auction.players_count', ['count' => $team['count'], 'max' => $state['auction']['max_squad']]) }}</span>
                </header>
                <p class="border-b border-line px-4 py-2 text-xs text-slate-500">
                    {{ __('auction.spent') }} <b class="tabular-nums text-slate-800">{{ points($team['spent']) }}</b>
                    &middot; <b class="tabular-nums text-slate-800">{{ points($team['left']) }}</b> {{ __('auction.left') }}
                </p>
                @if(count($team['players']))
                    <ol class="divide-y divide-line">
                        @foreach($team['players'] as $player)
                            <li class="flex items-center justify-between gap-2 px-4 py-2 text-[13px]">
                                <span class="min-w-0">
                                    <span class="block truncate font-medium text-slate-900">{{ $player['name'] }}</span>
                                    @if($player['role'])<span class="block truncate text-[11px] text-slate-400">{{ $player['role'] }}</span>@endif
                                </span>
                                <span class="shrink-0 tabular-nums text-slate-700">{{ $player['amount'] === null ? '—' : points($player['amount']) }}</span>
                            </li>
                        @endforeach
                    </ol>
                @else
                    <p class="pub-empty">{{ __('auction.no_squad') }}</p>
                @endif
            </x-public.card>
        @endforeach
    </div>

    <x-public.card class="mt-4" :title="__('auction.unsold')">
        @if(count($results['unsold']))
            <p class="text-[13px] leading-relaxed text-slate-600">{{ implode(' · ', $results['unsold']) }}</p>
        @else
            <p class="text-[13px] text-slate-500">{{ __('auction.no_unsold') }}</p>
        @endif
    </x-public.card>

    <x-ad-popup />
@endsection
