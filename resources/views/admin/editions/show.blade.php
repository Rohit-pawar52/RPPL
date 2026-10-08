@extends('layouts.admin')

@section('title', $edition->name)

@section('content')
    @include('admin.editions._crumbs', ['edition' => $edition])

    @php
        $user = auth()->user();
        // Each card is the way into one section of the season, so a role only gets the cards whose page it
        // may open (the same check that page's controller makes). Finance also needs its figures, which the
        // controller only works out for a role holding finance.view.
        $canOpen = [
            'registrations' => $user->can('viewAny', \App\Models\PlayerRegistration::class),
            'teams' => $user->can('viewAny', \App\Models\EditionTeam::class),
            'squads' => $user->can('viewAny', \App\Models\TeamPlayer::class),
            'matches' => $user->can('viewAny', \App\Models\GameMatch::class),
            'auction' => $user->can('viewAny', \App\Models\Auction::class),
            'finance' => $financeSummary !== null && $user->can('finance.view'),
        ];
    @endphp

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
            @can('update', $edition)
                <a href="{{ route('admin.editions.edit', $edition) }}" class="inline-flex items-center gap-1.5 rounded-md border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-600 hover:bg-slate-50">
                    <x-icon name="pencil" class="h-3.5 w-3.5" />
                    Edit
                </a>
            @endcan
        </div>
    </div>

    @if(in_array(true, $canOpen, true))
        <div class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
            @if($canOpen['registrations'])
                <x-admin.hub-card
                    label="Registrations"
                    icon="clipboard"
                    :value="$cards['registrations']['total']"
                    :sub="$cards['registrations']['pending'].' pending'"
                    :href="route('admin.editions.registrations.index', $edition)"
                />
            @endif
            @if($canOpen['teams'])
                <x-admin.hub-card
                    label="Teams"
                    icon="shield"
                    :value="$edition->edition_teams_count"
                    :href="route('admin.editions.teams.index', $edition)"
                />
            @endif
            @if($canOpen['squads'])
                <x-admin.hub-card
                    label="Squads"
                    icon="users"
                    :value="$cards['squads']['players']"
                    :sub="$cards['squads']['without_team'].' without a team'"
                    :href="route('admin.editions.squads.index', $edition)"
                />
            @endif
            @if($canOpen['matches'])
                <x-admin.hub-card
                    label="Matches"
                    icon="trophy"
                    :value="$edition->matches_count"
                    :sub="$cards['matches']['played'].' played · '.$cards['matches']['remaining'].' to play'"
                    :href="route('admin.editions.matches.index', $edition)"
                />
            @endif
            @if($canOpen['auction'])
                @php $auction = $edition->auction; @endphp
                <x-admin.hub-card
                    label="Auction"
                    icon="gavel"
                    :value="$auction ? ucfirst($auction->status) : 'Not set up'"
                    :sub="$auction ? $auction->lots()->count().' players in the pool' : 'Players bid for by points'"
                    :href="route('admin.auctions.show', $edition)"
                />
            @endif
            @if($canOpen['finance'])
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
            @endif
        </div>
    @endif

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
