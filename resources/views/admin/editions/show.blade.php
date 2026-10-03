@extends('layouts.admin')

@section('title', $edition->name)

@section('content')
    @include('admin.editions._crumbs', ['edition' => $edition])

    {{-- The season at a glance: a slim strip, then cards that are also the way in. --}}
    <div class="mb-3 flex flex-wrap items-center justify-between gap-2 rounded-lg border border-slate-200 bg-white px-4 py-2.5">
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
            <x-status-badge :status="$edition->status" />
            <span class="text-xs text-slate-500">{{ $edition->year }}</span>
            @if($edition->registration_open)
                <span class="rounded-full bg-green-50 px-2 py-0.5 text-[11px] font-medium text-green-700 ring-1 ring-inset ring-green-200">Registration open</span>
            @endif
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.editions.report.pdf', $edition) }}" class="inline-flex items-center gap-1.5 rounded-md border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50">
                Summary PDF
            </a>
            <a href="{{ route('admin.editions.edit', $edition) }}" class="inline-flex items-center gap-1.5 rounded-md border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50">
                <x-icon name="pencil" class="h-3.5 w-3.5" />
                Edit
            </a>
        </div>
    </div>

    <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5">
        <x-admin.hub-card
            label="Registrations"
            icon="clipboard"
            :value="$cards['registrations']['total']"
            :sub="$cards['registrations']['pending'].' pending'"
            :href="route('admin.player-registrations.index', ['edition_id' => $edition->id])"
        />
        <x-admin.hub-card
            label="Teams"
            icon="shield"
            :value="$edition->edition_teams_count"
            :href="route('admin.editions.teams.index', $edition)"
        />
        <x-admin.hub-card
            label="Squads"
            icon="users"
            :value="$cards['squads']['players']"
            :sub="$cards['squads']['without_team'].' without a team'"
            :href="route('admin.team-players.index')"
        />
        <x-admin.hub-card
            label="Matches"
            icon="trophy"
            :value="$edition->matches_count"
            :sub="$cards['matches']['played'].' played · '.$cards['matches']['remaining'].' to play'"
            :href="route('admin.matches.index', ['edition_id' => $edition->id])"
        />
        <x-admin.hub-card
            label="Finance"
            icon="currency"
            :value="money($financeSummary['balance'])"
            :sub="'In '.money($financeSummary['income']).' · Out '.money($financeSummary['expense'])"
            :href="route('admin.edition-transactions.index', ['edition_id' => $edition->id])"
            extra-label="Contributions {{ money($contributionSummary['total']) }}"
            :extra-href="route('admin.edition-contributions.index', ['edition_id' => $edition->id])"
            class="col-span-2 md:col-span-1"
        />
    </div>

    {{-- Season summary, kept compact: full points table, top 5s, one records strip. --}}
    <div class="mt-3">
        @include('shared.standings._table', ['standings' => $standings['standings'], 'ignoredMatchesCount' => $standings['ignored_matches_count']])
    </div>

    <div class="mt-3">
        @include('shared.statistics._leaderboard', ['leaderboard' => $leaderboard])
    </div>

    <div class="mt-3">
        @include('shared.statistics._records', ['records' => $records])
    </div>
@endsection
