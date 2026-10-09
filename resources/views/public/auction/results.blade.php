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
        $max = $state['auction']['max_squad'];
    @endphp

    {{-- The headline --}}
    <section class="pc-hero p-5 sm:p-7">
        <p class="pc-eyebrow">{{ $state['auction']['edition'] }}</p>
        <h1 class="mt-1 text-2xl font-bold tracking-tight sm:text-4xl">{{ __('auction.results') }}</h1>
        <p class="mt-1 text-sm text-slate-300">{{ __('auction.completed') }}</p>

        <div class="mt-5 grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="rounded-xl bg-white/10 px-4 py-3 ring-1 ring-white/15">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-300">{{ __('auction.players_sold') }}</p>
                <p class="mt-1 text-3xl font-bold tabular-nums">{{ $results['sold_count'] }}</p>
            </div>
            <div class="rounded-xl bg-white/10 px-4 py-3 ring-1 ring-white/15">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-300">{{ __('auction.points_spent') }}</p>
                <p class="mt-1 text-3xl font-bold tabular-nums">{{ points($results['points_spent']) }}</p>
            </div>
            <div class="rounded-xl bg-brand/30 px-4 py-3 ring-1 ring-accent-dark/40">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-200">{{ __('auction.most_expensive') }}</p>
                @if($top)
                    <p class="mt-1 text-3xl font-bold tabular-nums text-accent-dark">{{ points($top['amount']) }}</p>
                    <p class="mt-0.5 truncate text-xs text-slate-200">{{ $top['name'] }} &middot; {{ $top['team'] }}</p>
                @else
                    <p class="mt-1 text-3xl font-bold text-slate-400">—</p>
                @endif
            </div>
        </div>
    </section>

    {{-- The five dearest players --}}
    @if(count($results['top_buys']) > 1)
        <section class="mt-6 lg:mt-8">
            <h2 class="pc-h2">{{ __('auction.top_buys') }}</h2>
            <ol class="pc-panel divide-y divide-line">
                @foreach($results['top_buys'] as $buy)
                    <li class="flex items-center gap-3 px-4 py-3 text-[13px]">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold tabular-nums {{ $loop->first ? 'bg-brand text-brand-fg' : 'bg-slate-100 text-slate-500' }}">{{ $loop->iteration }}</span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-semibold text-slate-900">{{ $buy['name'] }}</span>
                            <span class="block truncate text-xs text-slate-500">{{ $buy['team'] }}</span>
                        </span>
                        <span class="shrink-0 text-base font-bold tabular-nums text-slate-900">{{ points($buy['amount']) }} <span class="text-[11px] font-normal text-slate-400">{{ __('auction.pts') }}</span></span>
                    </li>
                @endforeach
            </ol>
        </section>
    @endif

    {{-- Every squad --}}
    <section class="mt-6 lg:mt-8">
        <h2 class="pc-h2">{{ __('auction.teams') }}</h2>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach($state['teams'] as $team)
                @php $usedPct = $team['purse'] > 0 ? min(100, (int) round(($team['spent'] / $team['purse']) * 100)) : 0; @endphp
                <article class="pc-panel">
                    <header class="flex items-center gap-3 border-b border-line px-4 py-3">
                        <span class="pc-avatar h-10 w-10">
                            <x-media-image :url="$team['logo']" kind="image" alt="" loading="lazy" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <h3 class="truncate text-sm font-semibold text-slate-900">{{ $team['name'] }}</h3>
                            <p class="text-xs tabular-nums text-slate-500">{{ __('auction.players_count', ['count' => $team['count'], 'max' => $max]) }}</p>
                        </div>
                    </header>
                    <div class="border-b border-line px-4 py-3">
                        <div class="h-1.5 overflow-hidden rounded-full bg-slate-100" aria-hidden="true"><div class="h-full rounded-full bg-brand" style="width: {{ $usedPct }}%"></div></div>
                        <p class="mt-2 text-xs text-slate-500">
                            {{ __('auction.spent') }} <b class="tabular-nums text-slate-800">{{ points($team['spent']) }}</b>
                            &middot; <b class="tabular-nums text-slate-800">{{ points($team['left']) }}</b> {{ __('auction.left') }}
                        </p>
                    </div>
                    @if(count($team['players']))
                        <ol class="divide-y divide-line">
                            @foreach($team['players'] as $player)
                                <li class="flex items-center justify-between gap-2 px-4 py-2 text-[13px]">
                                    <span class="min-w-0">
                                        <span class="block truncate font-medium text-slate-900">{{ $player['name'] }}</span>
                                        @if($player['role'])<span class="block truncate text-[11px] text-slate-400">{{ $player['role'] }}</span>@endif
                                    </span>
                                    <span class="shrink-0 font-semibold tabular-nums text-slate-700">{{ $player['amount'] === null ? '—' : points($player['amount']) }}</span>
                                </li>
                            @endforeach
                        </ol>
                    @else
                        <p class="pub-empty">{{ __('auction.no_squad') }}</p>
                    @endif
                </article>
            @endforeach
        </div>
    </section>

    {{-- Who was not bought --}}
    <section class="mt-6 lg:mt-8">
        <h2 class="pc-h2">{{ __('auction.unsold') }} <span class="pc-h2-badge">{{ count($results['unsold']) }}</span></h2>
        <div class="pc-panel p-4">
            @if(count($results['unsold']))
                <ul class="flex flex-wrap gap-2">
                    @foreach($results['unsold'] as $name)
                        <li class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-700">{{ $name }}</li>
                    @endforeach
                </ul>
            @else
                <p class="flex items-center gap-2 text-[13px] text-slate-600">
                    <x-icon name="star" class="h-4 w-4 text-brand" />
                    {{ __('auction.no_unsold') }}
                </p>
            @endif
        </div>
    </section>

    <x-ad-popup />
@endsection
