@extends('layouts.public')

@section('title', $match->teamA->team->name.' '.__('matches.common.vs').' '.$match->teamB->team->name.' · '.$branding->shortName)

@section('content')
    @include('public.matches._header', ['match' => $match, 'active' => 'info'])

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

    <div class="mt-4 grid gap-4 lg:grid-cols-[minmax(0,1fr)_340px] lg:items-start lg:gap-5">
        <div class="min-w-0">
            <h2 class="mx-section-title mb-3">{{ __('ux_public_matches.match.explore') }}</h2>
            <div class="grid gap-3 sm:grid-cols-2">
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

        <aside class="min-w-0">
            @include('public.matches._details', ['match' => $match])
        </aside>
    </div>
@endsection
