@extends('layouts.public')

@section('title', __('matches.nav.scorecard').' · '.$match->teamA->team->name.' '.__('matches.common.vs').' '.$match->teamB->team->name)

{{--
    Public PDF download is intentionally hidden for now (per the
    cricket-first redesign pass) — the CTA is removed from this page
    only. The route, MatchController::scorecardPdf(), and admin PDF
    functionality are all untouched; nothing was deleted, so exposing
    it again later is a one-line change back.
--}}
@section('content')
    @include('public.matches._header', ['match' => $match, 'active' => 'scorecard'])

    @if($match->match_status === 'completed' && $match->match_result)
        <div class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-800">
            {{ $match->match_result }}
        </div>
    @endif

    {{-- shared.scorecard._innings is also used by the admin scorecard, so
         it is deliberately left untouched here (Admin is out of scope);
         only the outer page chrome around it was flattened for this pass. --}}
    @if(count($inningsScorecards) > 1)
        {{-- Real innings switcher — only one innings visible at a time,
             like the reference. Pure CSS/HTML (radio inputs + :has()),
             no JS: each pill is a <label> wrapping its own radio, and
             each innings panel shows only while its matching radio is
             checked, via Tailwind's group-has-[] variant. innings_number
             is always 1 or 2 in this data model (GameMatch only ever has
             a first/second innings), so both cases are written out
             literally — a dynamically interpolated arbitrary-variant
             class name would not be picked up by Tailwind's static scan. --}}
        <div class="group/innings">
            <div class="mb-4 flex flex-wrap gap-2">
                @foreach($inningsScorecards as $card)
                    @php $inn = $card['innings']; @endphp
                    <label class="inline-flex min-h-10 cursor-pointer items-center rounded-full border border-slate-300 bg-white px-4 text-[13px] font-medium text-slate-600 transition hover:border-slate-400 has-checked:border-green-600 has-checked:bg-green-600 has-checked:text-white has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-green-600">
                        <input
                            type="radio"
                            name="innings-tab"
                            value="{{ $inn->innings_number }}"
                            class="sr-only"
                            {{ $loop->first ? 'checked' : '' }}
                        >
                        {{ $inn->battingTeam->team->short_name ?: $inn->battingTeam->team->name }}
                        &mdash; {{ $inn->total_runs }}/{{ $inn->total_wickets }}
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
        @forelse($inningsScorecards as $card)
            @include('shared.scorecard._innings', ['card' => $card])
        @empty
            <div class="pub-card pub-empty">
                {{ __('matches.scorecard.not_available') }}
            </div>
        @endforelse
    @endif
@endsection
