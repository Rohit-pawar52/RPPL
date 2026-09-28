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

    {{-- Compact innings anchors — both innings render fully below in
         sequence (no JS switcher), so these are simple same-page jump
         links rather than a tab-switching component. Kept only when
         there is more than one innings to jump between. --}}
    @if(count($inningsScorecards) > 1)
        <div class="mb-4 flex flex-wrap gap-2">
            @foreach($inningsScorecards as $card)
                @php $inn = $card['innings']; @endphp
                <a
                    href="#innings-{{ $inn->innings_number }}"
                    class="rounded-full border theme-primary-border theme-primary-text px-3 py-1 text-[12px] font-medium theme-hover-primary"
                >
                    {{ $inn->battingTeam->team->short_name ?: $inn->battingTeam->team->name }}
                    &mdash; {{ $inn->total_runs }}/{{ $inn->total_wickets }}
                </a>
            @endforeach
        </div>
    @endif

    {{-- shared.scorecard._innings is also used by the admin scorecard, so
         it is deliberately left untouched here (Admin is out of scope);
         only the outer page chrome above was flattened for this pass. --}}
    @forelse($inningsScorecards as $card)
        @include('shared.scorecard._innings', ['card' => $card])
    @empty
        <div class="mt-4 rounded-lg border border-neutral-200 bg-white p-4 text-center text-xs text-neutral-400">
            Scorecard will be available once the match begins.
        </div>
    @endforelse
@endsection
