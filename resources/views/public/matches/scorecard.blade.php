@extends('layouts.public')

@section('title', 'Scorecard · '.$match->teamA->team->name.' vs '.$match->teamB->team->name)

{{--
    Public PDF download is intentionally hidden for now (per the
    cricket-first redesign pass) — the CTA is removed from this page
    only. The route, MatchController::scorecardPdf(), and admin PDF
    functionality are all untouched; nothing was deleted, so exposing
    it again later is a one-line change back.
--}}
@section('content')
    <div class="mb-3">
        <a href="{{ route('public.matches.index') }}" class="text-xs text-neutral-500 hover:text-neutral-700">
            &larr; All matches
        </a>
    </div>

    {{-- No match-info card here on purpose — team names/venue/date/
         status already live one tab over (Match Info); the Scorecard
         tab goes straight from the tabs into the result and the
         innings themselves, per the cricket-portal reference. --}}
    @include('public.matches._match-tabs', ['match' => $match, 'active' => 'scorecard'])

    @if($match->match_status === 'completed' && $match->match_result)
        <div class="mb-4 rounded-md border theme-primary-border theme-primary-soft-bg px-3 py-2 text-[13px] font-semibold theme-primary-text">
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
                    {{-- has-checked: only works with real Tailwind utilities,
                         not the plain .theme-primary-bg/-fg CSS classes, so
                         the active-pill fill uses the same --rppl-primary
                         custom properties directly via arbitrary values. --}}
                    <label class="cursor-pointer rounded-full border theme-primary-border px-3 py-1 text-[12px] font-medium text-neutral-600 has-checked:bg-[var(--rppl-primary)] has-checked:text-[var(--rppl-primary-fg)]">
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
            <div class="mt-4 rounded-lg border border-neutral-200 bg-white p-4 text-center text-xs text-neutral-400">
                Scorecard will be available once the match begins.
            </div>
        @endforelse
    @endif
@endsection
