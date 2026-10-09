@extends('layouts.admin')

@section('title', $edition->name)

@section('actions')
    <a href="{{ route('admin.editions.report.pdf', $edition) }}" class="btn btn-secondary">
        <x-ops.icon name="download" class="h-4 w-4" />
        {{ __('Summary PDF') }}
    </a>
    @can('update', $edition)
        <a href="{{ route('admin.editions.edit', $edition) }}" class="btn btn-secondary">
            <x-icon name="pencil" class="h-4 w-4" />
            {{ __('Edit') }}
        </a>
    @endcan
@endsection

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
        $auction = $canOpen['auction'] ? $edition->auction : null;
        $pending = (int) $cards['registrations']['pending'];
        $withoutTeam = (int) $cards['squads']['without_team'];
        $remaining = (int) $cards['matches']['remaining'];

        // The season as a journey: each step says where it stands and what to do about it.
        $steps = [];
        if ($canOpen['teams']) {
            $steps['teams'] = [
                'label' => __('Teams'), 'icon' => 'shield',
                'value' => $edition->edition_teams_count,
                'sub' => $edition->edition_teams_count < 2 ? __('Add at least two teams') : __('Teams playing this season'),
                'cta' => $edition->edition_teams_count < 2 ? __('Add teams') : __('Manage teams'),
                'done' => $edition->edition_teams_count >= 2,
                'href' => route('admin.editions.teams.index', $edition),
            ];
        }
        if ($canOpen['registrations']) {
            $steps['registrations'] = [
                'label' => __('Registrations'), 'icon' => 'clipboard',
                'value' => $cards['registrations']['total'],
                'sub' => __(':count pending', ['count' => $pending]),
                'cta' => $pending > 0 ? __('Check the pending ones') : __('View registrations'),
                'done' => $cards['registrations']['total'] > 0 && $pending === 0,
                'href' => route('admin.editions.registrations.index', $edition),
            ];
        }
        if ($canOpen['squads']) {
            $steps['squads'] = [
                'label' => __('Squads'), 'icon' => 'users',
                'value' => $cards['squads']['players'],
                'sub' => __(':count without a team', ['count' => $withoutTeam]),
                'cta' => $withoutTeam > 0 ? __('Put players in teams') : __('View squads'),
                'done' => $cards['squads']['players'] > 0 && $withoutTeam === 0,
                'href' => route('admin.editions.squads.index', $edition),
            ];
        }
        if ($canOpen['matches']) {
            $steps['matches'] = [
                'label' => __('Matches'), 'icon' => 'trophy',
                'value' => $edition->matches_count,
                'sub' => __(':played played · :remaining to play', ['played' => $cards['matches']['played'], 'remaining' => $remaining]),
                'cta' => $edition->matches_count === 0 ? __('Schedule matches') : __('Open the fixtures'),
                'done' => $edition->matches_count > 0 && $remaining === 0,
                'href' => route('admin.editions.matches.index', $edition),
            ];
        }
        if ($canOpen['auction']) {
            $steps['auction'] = [
                'label' => __('Auction'), 'icon' => 'gavel',
                'value' => $auction ? ucfirst(__($auction->status)) : __('Not set up'),
                'sub' => $auction ? __(':count players in the pool', ['count' => $auction->lots()->count()]) : __('Players bid for by points'),
                'cta' => $auction ? __('Open the auction') : __('Set up the auction'),
                'done' => $auction && $auction->status === 'completed',
                'href' => route('admin.auctions.show', $edition),
            ];
        }
        $nextKey = collect($steps)->filter(fn ($step) => ! $step['done'])->keys()->first();
        $number = 0;
    @endphp

    {{-- The season at a glance. --}}
    <header class="mb-4 flex flex-wrap items-center gap-x-4 gap-y-2 rounded-2xl bg-gradient-to-br from-navy-900 to-navy-800 px-4 py-4 text-white shadow-raised sm:px-6">
        <div class="min-w-0 flex-1">
            <p class="sc-kicker">{{ __('Season :year', ['year' => $edition->year]) }}</p>
            <h2 class="truncate text-2xl font-bold tracking-tight">{{ $edition->name }}</h2>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <x-status-badge :status="$edition->status" />
            @if($edition->registration_open)
                <span class="ops-pill ops-pill-green">{{ __('Registration open') }}</span>
            @endif
        </div>
    </header>

    @if($steps)
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
            @foreach($steps as $key => $step)
                @php $number++; $isNext = $key === $nextKey; @endphp
                <a
                    href="{{ $step['href'] }}"
                    @class([
                        'group relative flex flex-col gap-3 rounded-xl border bg-white p-4 shadow-card transition hover:-translate-y-px hover:shadow-raised focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand sm:p-5',
                        'border-brand ring-2 ring-brand/20' => $isNext,
                        'border-line' => ! $isNext,
                    ])
                >
                    <div class="flex items-center gap-3">
                        <span @class([
                            'flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-sm font-bold',
                            'bg-green-50 text-green-700' => $step['done'],
                            'bg-brand text-brand-fg' => ! $step['done'] && $isNext,
                            'bg-slate-100 text-slate-500' => ! $step['done'] && ! $isNext,
                        ])>
                            @if($step['done'])
                                <x-ops.icon name="check" class="h-4 w-4" />
                            @else
                                {{ $number }}
                            @endif
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="flex items-center gap-1.5 text-[13px] font-semibold text-slate-600">
                                <x-icon :name="$step['icon']" class="h-4 w-4 text-brand" />
                                {{ $step['label'] }}
                            </p>
                        </div>
                        @if($step['done'])
                            <span class="ops-pill ops-pill-green">{{ __('Done') }}</span>
                        @elseif($isNext)
                            <span class="ops-pill ops-pill-amber">{{ __('Next') }}</span>
                        @endif
                    </div>
                    <div>
                        <p class="text-3xl font-bold leading-8 tracking-tight tabular-nums text-slate-900">{{ $step['value'] }}</p>
                        <p class="mt-0.5 text-xs text-slate-500">{{ $step['sub'] }}</p>
                    </div>
                    <span class="mt-auto inline-flex items-center gap-1 text-[13px] font-semibold text-link group-hover:text-link-hover">
                        {{ $step['cta'] }}
                        <x-ops.icon name="arrow-right" class="h-4 w-4 transition group-hover:translate-x-0.5" />
                    </span>
                </a>
            @endforeach

            @if($canOpen['finance'])
                <div class="group relative flex flex-col gap-3 rounded-xl border border-line bg-white p-4 shadow-card transition hover:-translate-y-px hover:shadow-raised sm:p-5">
                    <div class="flex items-center gap-3">
                        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-slate-500"><x-icon name="currency" class="h-4 w-4" /></span>
                        <p class="text-[13px] font-semibold text-slate-600">{{ __('Finance') }}</p>
                    </div>
                    <div>
                        <p class="text-3xl font-bold leading-8 tracking-tight tabular-nums text-slate-900">{{ money($financeSummary['balance']) }}</p>
                        <p class="mt-0.5 text-xs text-slate-500">{{ __('In :income · Out :expense', ['income' => money($financeSummary['income']), 'expense' => money($financeSummary['expense'])]) }}</p>
                    </div>
                    <div class="mt-auto flex flex-wrap items-center justify-between gap-x-3 gap-y-1 text-[13px] font-semibold">
                        <a href="{{ route('admin.edition-transactions.index', ['edition_id' => $edition->id]) }}" class="inline-flex items-center gap-1 text-link after:absolute after:inset-0 after:content-[''] hover:text-link-hover">
                            {{ __('Open the books') }} <x-ops.icon name="arrow-right" class="h-4 w-4" />
                        </a>
                        <a href="{{ route('admin.edition-contributions.index', ['edition_id' => $edition->id]) }}" class="relative z-10 font-medium text-slate-500 hover:text-link hover:underline">{{ __('Contributions :amount', ['amount' => money($contributionSummary['total'])]) }}</a>
                    </div>
                </div>
            @endif
        </div>
    @endif

    {{-- Season summary, kept compact: full points table, top 5s, one records strip. --}}
    <div class="mt-4">
        @include('shared.standings._table', ['standings' => $standings['standings'], 'ignoredMatchesCount' => $standings['ignored_matches_count']])
    </div>

    <div class="mt-4">
        @include('shared.statistics._leaderboard', ['leaderboard' => $leaderboard])
    </div>

    <div class="mt-4">
        @include('shared.statistics._records', ['records' => $records])
    </div>
@endsection
