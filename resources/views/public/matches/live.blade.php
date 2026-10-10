@extends('layouts.public')

@section('title', __('matches.nav.live').' · '.$match->teamA->team->name.' '.__('matches.common.vs').' '.$match->teamB->team->name)

@section('content')
    @php
        $deliveries = $liveData['recent_deliveries'];

        // Everything public-live-match.js needs to word an update the same way
        // this page worded the first render (it never hard-codes a language).
        $statusLabels = collect(['scheduled', 'toss', 'live', 'completed', 'abandoned', 'cancelled'])
            ->mapWithKeys(fn ($status) => [$status => app()->getLocale() !== 'en' && \Illuminate\Support\Facades\Lang::has('public.status.'.$status) ? __('public.status.'.$status) : $status])
            ->all();
        $i18n = [
            'overs' => __('matches.common.overs_count', ['overs' => ':overs']),
            'crr' => __('matches.chase.crr', ['rate' => ':rate']),
            'chasingSide' => __('ux_public_matches.chase.chasing_side'),
            'need' => __('ux_public_matches.chase.need', ['team' => ':team', 'runs' => ':runs', 'balls' => ':balls']),
            'reached' => __('ux_public_matches.chase.reached', ['team' => ':team']),
            'runOne' => trans_choice('ux_public_matches.chase.runs', 1, ['count' => ':count']),
            'runMany' => trans_choice('ux_public_matches.chase.runs', 2, ['count' => ':count']),
            'ballOne' => trans_choice('ux_public_matches.chase.balls', 1, ['count' => ':count']),
            'ballMany' => trans_choice('ux_public_matches.chase.balls', 2, ['count' => ':count']),
            'target' => __('ux_public_matches.chase.target'),
            'needLabel' => __('ux_public_matches.chase.need_label'),
            'ballsLabel' => __('ux_public_matches.chase.balls_label'),
            'rrr' => __('ux_public_matches.chase.rrr'),
            'overN' => __('ux_public_matches.live.over_n', ['n' => ':n']),
            'lastBall' => __('ux_public_matches.live.last_ball'),
            'lastWicket' => __('ux_public_matches.live.last_wicket'),
            'bowlerToStriker' => __('matches.centre.bowler_to_striker', ['bowler' => ':bowler', 'striker' => ':striker']),
            'noDeliveries' => __('ux_public_matches.live.no_deliveries'),
            'batter' => __('ux_public_matches.live.batter'),
            'bowler' => __('ux_public_matches.live.bowler'),
            'colRuns' => __('ux_public_matches.live.col_runs'),
            'colBalls' => __('ux_public_matches.live.col_balls'),
            'colFours' => __('ux_public_matches.live.col_fours'),
            'colSixes' => __('ux_public_matches.live.col_sixes'),
            'colSr' => __('ux_public_matches.live.col_sr'),
            'colOvers' => __('ux_public_matches.live.col_overs'),
            'colConceded' => __('ux_public_matches.live.col_conceded'),
            'colWickets' => __('ux_public_matches.live.col_wickets'),
            'colEco' => __('ux_public_matches.live.col_eco'),
            'partnership' => __('ux_public_matches.live.partnership'),
            'lastWkt' => __('ux_public_matches.live.last_wkt'),
            'lastWktAt' => __('ux_public_matches.live.last_wkt_at', ['runs' => ':runs', 'balls' => ':balls', 'score' => ':score', 'over' => ':over']),
            'onStrike' => __('ux_public_matches.live.on_strike'),
            'status' => $statusLabels,
        ];
    @endphp

    @include('public.matches._header', ['match' => $match, 'active' => 'live', 'liveStatus' => true, 'liveData' => $liveData])

    <div
        id="live-match-root"
        class="mt-3 grid gap-3 lg:grid-cols-[minmax(0,1fr)_320px] lg:items-start lg:gap-4"
        data-match-id="{{ $match->id }}"
        data-live-data-url="{{ route('public.matches.live-data', $match) }}"
        data-should-poll="{{ $liveData['should_poll'] ? '1' : '0' }}"
        data-i18n="{{ json_encode($i18n, JSON_UNESCAPED_UNICODE) }}"
    >
        {{-- On a phone the two columns flatten into one list: board, a sponsor, this over, commentary, a sponsor, details. --}}
        <div class="contents lg:block lg:min-w-0 lg:space-y-3">
            <section id="live-board-card" class="pub-card order-1 overflow-hidden {{ $liveData['board'] ? '' : 'hidden' }}" aria-label="{{ __('ux_public_matches.live.batter') }} / {{ __('ux_public_matches.live.bowler') }}">
                <div id="live-board" class="mx-lb-wrap">
                    @if($liveData['board'])
                        @include('public.matches._live-board', ['board' => $liveData['board']])
                    @endif
                </div>
            </section>

            <section class="pub-card order-3 overflow-hidden">
                <header class="pub-card-head">
                    <h2 class="pub-card-title">{{ __('matches.live.this_over') }}</h2>
                    @if($liveData['should_poll'])
                        <span class="inline-flex items-center gap-1.5 pub-meta">
                            <span class="live-dot text-green-600" aria-hidden="true"></span> {{ __('matches.live.auto_updates') }}
                        </span>
                    @endif
                </header>
                <div id="live-this-over" class="p-4 sm:p-5">
                    @include('public.matches._live-over', ['deliveries' => $deliveries])
                </div>
            </section>

            <section class="pub-card order-4 overflow-hidden">
                <header class="pub-card-head">
                    <h2 class="pub-card-title">{{ __('matches.live.commentary') }}</h2>
                </header>
                <div id="live-deliveries">
                    @forelse($deliveries as $delivery)
                        @include('public.matches._live-delivery-row', ['delivery' => $delivery])
                    @empty
                        <p class="pub-empty">{{ __('ux_public_matches.live.no_deliveries') }}</p>
                    @endforelse
                </div>
            </section>
        </div>

        <aside class="contents lg:block lg:min-w-0 lg:space-y-3">
            <x-ad-side class="order-2" :offset="0" />
            <x-ad-side class="order-5" :offset="1" />
            <div class="order-6">
                @include('public.matches._details', ['match' => $match])
            </div>
        </aside>
    </div>

    @vite(['resources/js/public-live-match.js'])
@endsection
