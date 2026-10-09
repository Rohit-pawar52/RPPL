@extends('layouts.admin')

@section('title', 'Matches')

@section('content')
    @include('admin.editions._crumbs', ['edition' => $edition, 'section' => 'Matches'])

    @php
        $statusCounts = \App\Models\GameMatch::query()
            ->where('edition_id', $edition->id)
            ->selectRaw('match_status, count(*) as total')
            ->groupBy('match_status')
            ->pluck('total', 'match_status');
        $statusTabs = ['' => 'All'] + collect(\App\Models\GameMatch::STATUSES)->mapWithKeys(fn ($s) => [$s => ucfirst($s)])->all();
    @endphp

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <nav class="ops-chips min-w-0 flex-1" aria-label="Match status" id="season-match-filter">
            @foreach($statusTabs as $value => $label)
                @php $count = $value === '' ? $statusCounts->sum() : (int) ($statusCounts[$value] ?? 0); @endphp
                <a
                    href="{{ route('admin.editions.matches.index', array_filter(['edition' => $edition, 'match_status' => $value])) }}"
                    @class(['ops-chip', 'ops-chip-active' => ($status ?? '') === $value])
                    @if(($status ?? '') === $value) aria-current="page" @endif
                >
                    {{ $label }} <span class="ops-chip-count">{{ $count }}</span>
                </a>
            @endforeach
        </nav>

        @can('create', \App\Models\GameMatch::class)
            @if($canAdd)
                <x-admin.button :href="route('admin.editions.matches.create', $edition)">+ Add match</x-admin.button>
            @else
                <span class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                    This season is completed, so new matches cannot be scheduled.
                </span>
            @endif
        @endcan
    </div>

    <section class="ops-card">
        <div class="ops-card-head">
            <h3 class="ops-title">Matches in {{ $edition->name }} ({{ $matches->total() }})</h3>
        </div>

        <div class="divide-y divide-line">
            @forelse($matches as $match)
                @php
                    $matchStatus = $match->match_status;
                    $liveInnings = $matchStatus === 'live' ? $match->innings()->where('status', 'live')->first() : null;
                    $canScore = $liveInnings && auth()->user()->can('score', $match);
                @endphp
                <div class="relative flex flex-wrap items-center gap-x-4 gap-y-3 p-3 transition hover:bg-hover/50 sm:p-4 lg:flex-nowrap">
                    <div class="w-12 shrink-0 text-center">
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ display_datetime($match->scheduled_at, 'M') }}</p>
                        <p class="text-2xl font-bold leading-6 tabular-nums text-slate-900">{{ display_datetime($match->scheduled_at, 'd') }}</p>
                    </div>
                    <div class="min-w-0 flex-1 basis-48">
                        <a href="{{ route('admin.matches.show', $match) }}" class="block break-words text-[15px] font-semibold leading-5 text-slate-900 after:absolute after:inset-0 after:content-[''] hover:underline">
                            {{ $match->teamA->team->name }} vs {{ $match->teamB->team->name }}
                        </a>
                        <p class="mt-1 text-xs text-slate-500">
                            @if($match->match_number) Match {{ $match->match_number }} &middot; @endif
                            {{ display_datetime($match->scheduled_at, 'd M Y, h:i A') }}
                            &middot; {{ $match->venue->name ?? 'TBD' }}
                            @if($match->match_stage) &middot; {{ ucwords(str_replace('_', ' ', $match->match_stage)) }} @endif
                            @if($match->overs_per_innings) &middot; {{ $match->overs_per_innings }} overs @endif
                        </p>
                    </div>
                    <x-status-badge :status="$matchStatus" />
                    <div class="relative z-10 flex items-center gap-2 max-sm:w-full">
                        @if($canScore)
                            <a href="{{ route('admin.matches.innings.score', [$match, $liveInnings]) }}" class="btn btn-primary max-sm:flex-1"><x-ops.icon name="bolt" /> Score</a>
                        @elseif(in_array($matchStatus, ['scheduled', 'toss'], true))
                            <a href="{{ route('admin.matches.show', $match) }}" class="btn btn-secondary max-sm:flex-1">{{ $matchStatus === 'toss' ? 'Continue' : 'Start' }} <x-ops.icon name="arrow-right" /></a>
                        @else
                            <a href="{{ route('admin.matches.show', $match) }}" class="btn btn-secondary max-sm:flex-1">{{ $matchStatus === 'live' ? 'Open match' : 'Result' }}</a>
                        @endif
                        @can('update', $match)
                            <a href="{{ route('admin.matches.edit', $match) }}" class="btn btn-ghost btn-icon" title="Edit" aria-label="Edit match">
                                <x-icon name="pencil" class="h-4 w-4" />
                            </a>
                        @endcan
                    </div>
                </div>
            @empty
                <x-admin.empty icon="trophy" class="py-12">
                    @if($status)
                        No {{ $status }} matches in this season.
                    @else
                        No matches in this season yet.
                    @endif
                    <x-slot:action>
                        @if($status)
                            <a href="{{ route('admin.editions.matches.index', $edition) }}" class="btn btn-secondary btn-sm">Show all matches</a>
                        @elseif($canAdd)
                            @can('create', \App\Models\GameMatch::class)
                                <a href="{{ route('admin.editions.matches.create', $edition) }}" class="btn btn-primary btn-sm">+ Schedule the first match</a>
                            @endcan
                        @endif
                    </x-slot:action>
                </x-admin.empty>
            @endforelse
        </div>
    </section>

    <div class="mt-3">
        {{ $matches->links() }}
    </div>
@endsection
