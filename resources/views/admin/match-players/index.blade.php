@extends('layouts.admin')

@section('title', 'Playing XI')

@section('content')
    <div class="mb-4">
        <a href="{{ route('admin.matches.show', $match) }}" class="text-xs text-neutral-500 hover:text-neutral-700">
            &larr; Back to match
        </a>
    </div>

    <div class="rounded-lg border border-neutral-200 bg-white p-4">
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-base font-semibold text-neutral-900">
                {{ $match->teamA->team->name }} vs {{ $match->teamB->team->name }} &mdash; Playing XI
            </h2>
            <x-status-badge :status="$match->match_status" />
        </div>
        <p class="mt-1 text-xs text-neutral-500">
            {{ $match->edition->name }}
            &middot;
            {{ display_datetime($match->scheduled_at, 'd M Y, h:i A') }}
        </p>
    </div>

    @unless($canModify)
        <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2.5 text-xs text-amber-700">
            The Playing XI for this match is locked and can no longer be changed.
        </div>
    @endunless

    <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
        @include('admin.match-players._team', [
            'match' => $match,
            'team' => $match->teamA->team,
            'editionTeamId' => $match->edition_team_a_id,
            'selected' => $teamASelected,
            'eligible' => $teamAEligible,
            'canModify' => $canModify,
        ])

        @include('admin.match-players._team', [
            'match' => $match,
            'team' => $match->teamB->team,
            'editionTeamId' => $match->edition_team_b_id,
            'selected' => $teamBSelected,
            'eligible' => $teamBEligible,
            'canModify' => $canModify,
        ])
    </div>
@endsection
