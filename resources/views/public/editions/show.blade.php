@extends('layouts.public')

@section('title', $edition->name.' · '.$branding->shortName)

@section('content')
    @php
        // Every other season, one tap away (a tiny read: id + name only).
        $allEditions = \App\Models\Edition::query()->orderByDesc('year')->get(['id', 'name']);
        $playedCount = intdiv(collect($standings)->sum('played'), 2);
        $liveMatch = $matches->firstWhere('match_status', 'live');
        $boards = \App\Services\Statistics\PlayerStatisticsService::HIGHLIGHT_BOARDS;
    @endphp

    {{-- Season header --}}
    <header class="mx-hero">
        <div class="mx-hero-glow" aria-hidden="true"></div>

        <div class="relative px-4 pb-5 pt-3 sm:px-6 sm:pb-6 sm:pt-4">
            <div class="flex items-center justify-between gap-3">
                <a href="{{ route('public.editions.index') }}" class="mx-hero-back">
                    <span aria-hidden="true">&larr;</span> {{ __('directory.editions.all_editions') }}
                </a>
                <x-public.status-pill :status="$edition->status" />
            </div>

            <p class="mx-hero-context"><span>{{ $edition->year }}</span></p>
            <h1 class="mx-hero-title mt-1">{{ $edition->name }}</h1>

            <dl class="mx-stats mt-5">
                <div class="mx-stat"><b>{{ $edition->edition_teams_count }}</b><span>{{ __('directory.editions.teams') }}</span></div>
                <div class="mx-stat"><b>{{ $edition->matches_count }}</b><span>{{ __('directory.editions.matches') }}</span></div>
                <div class="mx-stat"><b>{{ $playedCount }}</b><span>{{ __('ux_public_matches.edition.played') }}</span></div>
            </dl>

            <nav class="mx-chips mt-5" aria-label="{{ $edition->name }}">
                @if($liveMatch)
                    <a href="{{ route('public.matches.live', $liveMatch) }}" class="mx-chip-dark bg-green-600! text-white!">
                        <span class="live-dot" aria-hidden="true"></span> {{ __('ux_public_matches.edition.live_now') }}
                    </a>
                @endif
                <a href="#points" class="mx-chip-dark">{{ __('ux_public_matches.edition.quick_table') }}</a>
                <a href="#stats" class="mx-chip-dark">{{ __('ux_public_matches.edition.quick_stats') }}</a>
                <a href="#teams" class="mx-chip-dark">{{ __('ux_public_matches.edition.quick_teams') }}</a>
                <a href="#fixtures" class="mx-chip-dark">{{ __('ux_public_matches.edition.fixtures') }}</a>
                <a href="{{ route('public.matches.index', ['edition_id' => $edition->id]) }}" class="mx-chip-dark">{{ __('ux_public_matches.edition.quick_matches') }} &rarr;</a>
            </nav>
        </div>
    </header>

    @if($allEditions->count() > 1)
        <nav class="mt-4" aria-label="{{ __('ux_public_matches.list.season') }}">
            <div class="mx-chips">
                @foreach($allEditions as $other)
                    <a href="{{ route('public.editions.show', $other) }}" @class(['mx-chip', 'is-active' => $other->id === $edition->id]) @if($other->id === $edition->id) aria-current="page" @endif>{{ $other->name }}</a>
                @endforeach
            </div>
        </nav>
    @endif

    <div class="mt-4 space-y-4 lg:space-y-5">
        {{-- Points table --}}
        @include('shared.standings._table', ['standings' => $standings])

        {{-- Leaderboard --}}
        <div id="stats" class="scroll-mt-32 space-y-4">
            @include('shared.statistics._leaderboard', ['leaderboard' => $leaderboard, 'edition' => $edition, 'showMore' => true])

            <nav class="mx-chips" aria-label="{{ __('home.stats.title') }}">
                @foreach($boards as $slug)
                    <a href="{{ route('public.editions.stats', [$edition, $slug]) }}" class="mx-chip">{{ __('home.boards.'.$slug) }}</a>
                @endforeach
            </nav>
        </div>

        {{-- Records --}}
        @include('shared.statistics._records', ['records' => $records])

        {{-- Participating teams --}}
        <x-public.card id="teams" class="scroll-mt-32" :title="__('directory.editions.participating_teams')" icon="users">
            @if($teams->isEmpty())
                <x-public.empty icon="users">{{ __('directory.editions.participating_teams_empty') }}</x-public.empty>
            @else
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                    @foreach($teams as $editionTeam)
                        <a href="{{ route('public.teams.show', ['team' => $editionTeam->team, 'edition_id' => $edition->id]) }}" class="flex flex-col items-center gap-2 rounded-xl border border-line bg-white p-4 text-center transition duration-150 hover:-translate-y-px hover:shadow-raised focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand">
                            <x-mx.team-logo :team="$editionTeam->team" size="lg" />
                            <span class="line-clamp-2 text-sm font-semibold leading-tight text-slate-900">{{ $editionTeam->team->name }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </x-public.card>

        {{-- Recognition only - no contribution amount is ever rendered here
             (Phase 3.40). Ranking itself is still amount-driven, computed
             entirely by ContributorRankingService; this view only reads
             position/name/photo_path. --}}
        @if(! empty($contributorRanking))
            <x-public.card :title="__('directory.editions.top_contributors', ['name' => $branding->shortName])" icon="star">
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                    @foreach($contributorRanking as $row)
                        @php
                            $badge = match (true) {
                                $row['position'] === 1 => ['label' => __('directory.editions.badge_top'), 'class' => 'bg-amber-100 text-amber-700', 'icon' => 'trophy'],
                                $row['position'] === 2 => ['label' => __('directory.editions.badge_second'), 'class' => 'bg-slate-200 text-slate-700', 'icon' => 'star'],
                                $row['position'] === 3 => ['label' => __('directory.editions.badge_third'), 'class' => 'bg-orange-100 text-orange-700', 'icon' => 'star'],
                                $row['is_top_ten'] => ['label' => __('directory.editions.badge_top_ten'), 'class' => 'bg-sky-50 text-sky-700', 'icon' => null],
                                default => null,
                            };
                            $initials = collect(preg_split('/\s+/', trim($row['name'])))
                                ->filter()
                                ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
                                ->take(2)
                                ->implode('');
                        @endphp
                        <div class="flex flex-col items-center gap-1.5 rounded-xl border border-line p-3 text-center">
                            <div class="relative">
                                <div class="pub-media flex h-14 w-14 items-center justify-center rounded-full border border-line text-sm font-semibold text-slate-500">
                                    <x-media-image :path="$row['photo_path']" kind="user" alt="" class="absolute inset-0 h-full w-full bg-white object-cover" />
                                </div>
                                <span class="absolute -bottom-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full border border-white bg-slate-800 text-[10px] font-semibold text-white">
                                    {{ $row['position'] }}
                                </span>
                            </div>
                            <p class="max-w-full truncate text-xs font-semibold text-slate-900">{{ $row['name'] }}</p>
                            @if($badge)
                                <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $badge['class'] }}">
                                    @if($badge['icon'])
                                        <x-icon name="{{ $badge['icon'] }}" class="h-3 w-3" />
                                    @endif
                                    {{ $badge['label'] }}
                                </span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-public.card>
        @endif

        {{-- Fixtures and results --}}
        <x-public.card id="fixtures" class="scroll-mt-32" flush :title="__('ux_public_matches.edition.fixtures')" icon="calendar" :href="route('public.matches.index', ['edition_id' => $edition->id])" :link-label="__('ux_public_matches.edition.all_fixtures').' →'">
            @forelse($matches as $match)
                @include('public.matches._list-row', ['match' => $match])
            @empty
                <x-public.empty icon="calendar">{{ __('directory.editions.matches_empty') }}</x-public.empty>
            @endforelse
        </x-public.card>
    </div>
@endsection
