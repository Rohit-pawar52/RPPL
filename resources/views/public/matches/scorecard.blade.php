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

    <div class="rounded-lg border border-neutral-200 bg-white p-3 sm:p-4">
        <p class="truncate text-[11px] text-neutral-500">{{ $match->edition->name }}</p>
        <h1 class="mt-0.5 text-base font-semibold text-neutral-900">
            {{ $match->teamA->team->name }} vs {{ $match->teamB->team->name }}
        </h1>
        <p class="mt-1 text-[11px] text-neutral-500">
            @if($match->venue)
                {{ $match->venue->name }} &middot;
            @endif
            {{ display_datetime($match->scheduled_at, 'd M Y, h:i A') }}
        </p>
        <div class="mt-2">
            <x-status-badge :status="$match->match_status" />
        </div>
    </div>

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
