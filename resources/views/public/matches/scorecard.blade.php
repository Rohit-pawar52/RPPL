@extends('layouts.public')

@section('title', __('matches.nav.scorecard').' · '.$match->teamA->team->name.' '.__('matches.common.vs').' '.$match->teamB->team->name)

{{--
    Public PDF download is intentionally hidden for now (per the
    cricket-first redesign pass) - the CTA is removed from this page
    only. The route, MatchController::scorecardPdf(), and admin PDF
    functionality are all untouched; nothing was deleted, so exposing
    it again later is a one-line change back.
--}}
@section('content')
    {{-- Same shape as the Live tab: tabs first (close under the site header), a small score card, the innings in the wide
         column and the sponsor boxes in the narrow one. --}}
    <div class="-mt-3 lg:-mt-6">
        @include('public.matches._match-tabs', ['match' => $match, 'active' => 'scorecard'])
    </div>
    @include('public.matches._hero-scripts')

    <div class="mt-2 grid grid-cols-[minmax(0,1fr)] gap-3 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)] lg:items-start lg:gap-4">
        {{-- On a phone the columns flatten into one list: score card, a sponsor, the innings, a sponsor. --}}
        <div class="contents lg:block lg:min-w-0 lg:space-y-3">
            <div class="order-1">
                @include('public.matches._live-score', ['match' => $match, 'liveData' => $liveData])
            </div>

            {{-- shared.scorecard._innings is also used by the admin scorecard, so it keeps its inputs. --}}
            <div class="order-3 min-w-0">
                @if(count($inningsScorecards) > 1)
                    {{-- Real innings switcher - only one innings visible at a time. Pure CSS/HTML (radio inputs +
                         :has()), no JS: each pill is a <label> wrapping its own radio, and each innings panel shows
                         only while its matching radio is checked, via Tailwind's group-has-[] variant. innings_number
                         is always 1 or 2 in this data model, so both cases are written out literally - a dynamically
                         interpolated arbitrary-variant class name would not be picked up by Tailwind's static scan. --}}
                    <div class="group/innings">
                        <div class="mx-pills" role="radiogroup" aria-label="{{ __('ux_public_matches.scorecard.choose_innings') }}">
                            @foreach($inningsScorecards as $card)
                                @php $inn = $card['innings']; @endphp
                                <label class="mx-pill">
                                    <input
                                        type="radio"
                                        name="innings-tab"
                                        value="{{ $inn->innings_number }}"
                                        class="sr-only"
                                        {{ $loop->first ? 'checked' : '' }}
                                    >
                                    <span class="truncate">{{ $inn->battingTeam->team->short_name ?: $inn->battingTeam->team->name }}</span>
                                    <span class="tabular-nums opacity-80">{{ $inn->total_runs }}/{{ $inn->total_wickets }}</span>
                                </label>
                            @endforeach
                        </div>

                        @foreach($inningsScorecards as $card)
                            @if($card['innings']->innings_number === 1)
                                <div class="hidden group-has-[input[value='1']:checked]/innings:block">
                            @else
                                <div class="hidden group-has-[input[value='2']:checked]/innings:block">
                            @endif
                                @include('shared.scorecard._innings', ['card' => $card])
                            </div>
                        @endforeach
                    </div>
                @else
                    <div>
                        @forelse($inningsScorecards as $card)
                            @include('shared.scorecard._innings', ['card' => $card])
                        @empty
                            <div class="pub-card">
                                <p class="pub-empty">{{ __('matches.scorecard.not_available') }}</p>
                            </div>
                        @endforelse
                    </div>
                @endif
            </div>
        </div>

        <aside class="contents lg:sticky lg:top-24 lg:block lg:min-w-0 lg:space-y-3">
            <x-ad-side class="order-2" :offset="0" />
            <x-ad-side class="order-4" :offset="1" />
        </aside>
    </div>
@endsection
