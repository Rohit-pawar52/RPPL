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
    @include('public.matches._header', ['match' => $match, 'active' => 'scorecard'])

    {{-- shared.scorecard._innings is also used by the admin scorecard, so it
         keeps its inputs; the result line now lives in the score header. --}}
    @if(count($inningsScorecards) > 1)
        {{-- Real innings switcher - only one innings visible at a time. Pure
             CSS/HTML (radio inputs + :has()), no JS: each pill is a <label>
             wrapping its own radio, and each innings panel shows only while
             its matching radio is checked, via Tailwind's group-has-[]
             variant. innings_number is always 1 or 2 in this data model
             (GameMatch only ever has a first/second innings), so both cases
             are written out literally - a dynamically interpolated
             arbitrary-variant class name would not be picked up by
             Tailwind's static scan. --}}
        <div class="group/innings mt-4">
            <div class="mx-seg" role="radiogroup" aria-label="{{ __('ux_public_matches.scorecard.choose_innings') }}">
                @foreach($inningsScorecards as $card)
                    @php $inn = $card['innings']; @endphp
                    <label class="mx-seg-item">
                        <input
                            type="radio"
                            name="innings-tab"
                            value="{{ $inn->innings_number }}"
                            class="sr-only"
                            {{ $loop->first ? 'checked' : '' }}
                        >
                        <x-mx.team-logo :team="$inn->battingTeam->team" size="xs" class="hidden sm:inline-flex" />
                        <span class="truncate">{{ $inn->battingTeam->team->short_name ?: $inn->battingTeam->team->name }}</span>
                        <span class="tabular-nums text-slate-500">{{ $inn->total_runs }}/{{ $inn->total_wickets }}</span>
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
@endsection
