@extends('layouts.public')

@section('title', $match->teamA->team->name.' '.__('matches.common.vs').' '.$match->teamB->team->name.' · '.$branding->shortName)

@section('content')
    {{-- Same shape as the Live and Scorecard tabs: tabs first, a small score card, the page in the wide column and the
         sponsor boxes in the narrow one. --}}
    <div class="-mt-3 lg:-mt-6">
        @include('public.matches._match-tabs', ['match' => $match, 'active' => 'info'])
    </div>
    @include('public.matches._hero-scripts')

    @php
        $isLive = $match->match_status === 'live';
        $links = [];

        if ($match->innings_count > 0) {
            $links[] = ['icon' => 'document-chart', 'title' => __('matches.nav.scorecard'), 'hint' => __('ux_public_matches.match.scorecard_hint'), 'url' => route('public.matches.scorecard', $match)];
        }
        if ($isLive && $match->innings_count > 0) {
            $links[] = ['icon' => 'play', 'title' => __('matches.list.follow_live'), 'hint' => __('ux_public_matches.match.live_hint'), 'url' => route('public.matches.live', $match), 'live' => true];
        }
        $links[] = ['icon' => 'users', 'title' => __('matches.squads.playing_xi'), 'hint' => __('ux_public_matches.match.squads_hint'), 'url' => route('public.matches.squads', $match)];
        $links[] = ['icon' => 'chart-bar', 'title' => __('public.nav.points_table'), 'hint' => __('ux_public_matches.match.table_hint', ['edition' => $match->edition->name]), 'url' => route('public.editions.show', $match->edition).'#points'];
        $links[] = ['icon' => 'calendar', 'title' => __('ux_public_matches.match.season_matches'), 'hint' => __('ux_public_matches.match.season_matches_hint', ['edition' => $match->edition->name]), 'url' => route('public.matches.index', ['edition_id' => $match->edition_id])];
    @endphp

    <div class="mt-2 grid grid-cols-[minmax(0,1fr)] gap-3 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)] lg:items-start lg:gap-4">
        {{-- On a phone the columns flatten into one list: score card, a sponsor, details, links, a sponsor. --}}
        <div class="contents lg:block lg:min-w-0 lg:space-y-3">
            <div class="order-1">
                @include('public.matches._live-score', ['match' => $match, 'liveData' => $liveData])
            </div>

            <div class="order-3 min-w-0">
                @include('public.matches._details', ['match' => $match, 'wide' => true])
            </div>

            <div class="order-4 min-w-0">
                <h2 class="mx-section-title mb-2">{{ __('ux_public_matches.match.explore') }}</h2>
                <div class="grid gap-2.5 sm:grid-cols-2">
                    @foreach($links as $link)
                        <a href="{{ $link['url'] }}" @class(['mx-tile', 'mx-tile-live' => $link['live'] ?? false])>
                            <span class="mx-tile-icon"><x-icon :name="$link['icon']" class="size-5" /></span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-semibold text-slate-900">{{ $link['title'] }}</span>
                                <span class="mt-0.5 block truncate text-xs text-slate-500">{{ $link['hint'] }}</span>
                            </span>
                            <span class="text-slate-300" aria-hidden="true">&rarr;</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        <aside class="contents lg:sticky lg:top-24 lg:block lg:min-w-0 lg:space-y-3">
            <x-ad-side class="order-2" :offset="0" />
            <x-ad-side class="order-5" :offset="1" />
        </aside>
    </div>
@endsection
