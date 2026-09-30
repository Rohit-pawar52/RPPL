@extends('layouts.public')

@section('title', $branding->shortName.' · '.$branding->applicationName)

{{--
    Public homepage — compact, match-first tournament portal. Top to
    bottom: edition strip → Featured match (LIVE > next > recent, the
    primary block) → Match Centre supporting cards → Featured Videos
    (only when any are active) → Points Table + Upcoming + Recent
    Results → Top Performers → Teams. Purely presentational: every
    score, standing and stat comes pre-computed from HomeController
    (LiveMatchService, StandingsService, PlayerStatisticsService) —
    nothing is recalculated here. Every section has its own empty state
    so an edition with no matches/teams/stats yet still renders cleanly.
--}}
@section('content')
    {{-- Edition context strip: application name + tagline (settings-
         driven, asserted by the branding tests) and the current edition
         with its status. One slim row — no hero banner. --}}
    <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
        <div class="min-w-0">
            <h1 class="truncate text-base font-bold tracking-tight text-slate-900">
                {{ $edition?->name ?? $branding->applicationName }}
            </h1>
            <p class="pub-meta truncate">
                {{ $branding->tagline ?: __('matches.home.tagline_fallback') }}
            </p>
        </div>
        @if($edition)
            <div class="flex shrink-0 items-center gap-3">
                <x-public.status-pill :status="$edition->status" />
                <a href="{{ route('public.editions.show', $edition) }}" class="pub-link text-xs">
                    {{ __('matches.home.edition_details') }} &rarr;
                </a>
            </div>
        @endif
    </div>

    @if(! $edition)
        <div class="pub-card mt-4 px-4 py-8 text-center">
            <x-icon name="trophy" class="mx-auto h-5 w-5 text-slate-300" />
            <p class="mt-2 text-[13px] text-slate-600">{{ __('matches.home.no_editions') }}</p>
            <p class="pub-meta mt-0.5">{{ __('matches.home.no_editions_hint') }}</p>
        </div>
    @else
        {{-- 1. Featured match: LIVE > NEXT > RECENT --}}
        @include('public._featured-match', [
            'liveMatch' => $liveMatch,
            'liveMatchData' => $liveMatchData,
            'nextMatch' => $nextMatch,
            'recentMatch' => $recentMatch,
        ])

        {{-- 2. Match Centre: supporting next / recent cards --}}
        @include('public._match-centre', [
            'liveMatch' => $liveMatch,
            'nextMatch' => $nextMatch,
            'recentMatch' => $recentMatch,
        ])

        @if(! $liveMatch && ! $nextMatch && ! $recentMatch)
            <div class="pub-card mt-4 px-4 py-8 text-center">
                <x-icon name="calendar" class="mx-auto h-5 w-5 text-slate-300" />
                <p class="mt-1.5 text-[13px] text-slate-600">{{ __('matches.list.no_scheduled') }}</p>
                <p class="pub-meta">{{ __('matches.home.no_matches_hint') }}</p>
            </div>
        @endif

        {{-- Featured Videos — below the match blocks so live/match info
             stays first; renders nothing when there are none. --}}
        @include('public._featured-videos', ['featuredVideos' => $featuredVideos])

        <div class="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-3 lg:items-start">
            {{-- 3. Points Table preview --}}
            <x-public.card :title="__('matches.home.points_table')" :href="route('public.editions.show', $edition)" :link-label="__('matches.home.full_table').' →'" flush class="lg:col-span-2">
                <div class="pub-table-wrap">
                    <table class="pub-table min-w-[320px]">
                        <thead>
                            <tr>
                                <th class="w-10">#</th>
                                <th>{{ __('matches.table.team') }}</th>
                                <th class="text-right">{{ __('matches.table.played') }}</th>
                                <th class="text-right">{{ __('matches.table.won') }}</th>
                                <th class="text-right">{{ __('matches.table.lost') }}</th>
                                <th class="text-right">{{ __('matches.table.points') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($standings as $row)
                                @php $standingTeam = $row['edition_team']?->team; @endphp
                                <tr>
                                    <td class="text-slate-400">{{ $row['position'] }}</td>
                                    <td class="font-medium text-slate-800">
                                        @if($standingTeam)
                                            <a href="{{ route('public.teams.show', $standingTeam) }}" class="hover:underline">
                                                <span class="sm:hidden">{{ $standingTeam->short_name ?: $standingTeam->name }}</span>
                                                <span class="hidden sm:inline">{{ $standingTeam->name }}</span>
                                            </a>
                                        @else
                                            &mdash;
                                        @endif
                                    </td>
                                    <td class="text-right tabular-nums">{{ $row['played'] }}</td>
                                    <td class="text-right tabular-nums">{{ $row['won'] }}</td>
                                    <td class="text-right tabular-nums">{{ $row['lost'] }}</td>
                                    <td class="text-right font-bold tabular-nums text-slate-900">{{ $row['points'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="pub-empty">{{ __('matches.home.standings_empty') }}</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </x-public.card>

            <div class="space-y-4">
                {{-- 4a. Upcoming Matches --}}
                <x-public.card :title="__('matches.home.upcoming_matches')" :href="route('public.matches.index')" :link-label="__('matches.home.view_all_matches').' →'" flush>
                    @forelse($upcomingMatches as $match)
                        @include('public.matches._list-row', ['match' => $match])
                    @empty
                        <x-public.empty>{{ __('matches.list.no_scheduled') }}</x-public.empty>
                    @endforelse
                </x-public.card>

                {{-- 4b. Recent Results --}}
                <x-public.card :title="__('matches.home.recent_results')" :href="route('public.matches.index')" :link-label="__('matches.home.view_all_matches').' →'" flush>
                    @forelse($recentMatches as $match)
                        <a href="{{ route('public.matches.scorecard', $match) }}" class="match-row">
                            <div class="min-w-0">
                                <p class="truncate font-semibold text-slate-900">
                                    {{ $match->teamA?->team?->short_name ?: $match->teamA?->team?->name }}
                                    <span class="font-normal text-slate-400">{{ __('matches.common.vs') }}</span>
                                    {{ $match->teamB?->team?->short_name ?: $match->teamB?->team?->name }}
                                </p>
                                <p class="truncate text-xs font-medium text-green-700">
                                    {{ $match->match_result ?: __('matches.common.result_unavailable') }}
                                </p>
                            </div>
                            <span class="pub-meta shrink-0">{{ display_datetime($match->scheduled_at, 'd M') }}</span>
                        </a>
                    @empty
                        <x-public.empty>{{ __('matches.list.no_completed') }}</x-public.empty>
                    @endforelse
                </x-public.card>
            </div>
        </div>

        {{-- 5. Top Performers + Teams --}}
        <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2 lg:items-start">
            <x-public.card :title="__('matches.home.top_performers')" :href="route('public.editions.show', $edition)" :link-label="__('matches.home.more_stats').' →'">
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    @foreach([
                        [__('matches.home.most_runs'), $topRunScorers, 'runs'],
                        [__('matches.home.most_wickets'), $topWicketTakers, 'wickets'],
                    ] as [$boardTitle, $entries, $statKey])
                        <div>
                            <p class="pub-eyebrow mb-1">{{ $boardTitle }}</p>
                            @forelse($entries as $entry)
                                <div class="flex items-center justify-between gap-2 border-b border-line py-2 text-[13px] last:border-b-0">
                                    <span class="flex min-w-0 items-center gap-2">
                                        <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-slate-100 text-[11px] font-semibold text-slate-500">{{ $loop->iteration }}</span>
                                        <span class="truncate text-slate-800">{{ $entry['player']?->name }}</span>
                                    </span>
                                    <span class="shrink-0 font-bold tabular-nums text-slate-900">{{ $entry['stats'][$statKey] ?? 0 }}</span>
                                </div>
                            @empty
                                <p class="py-2 text-xs text-slate-400">{{ __('matches.home.stats_empty') }}</p>
                            @endforelse
                        </div>
                    @endforeach
                </div>
            </x-public.card>

            <x-public.card :title="__('matches.home.teams')" :href="route('public.teams.index')" :link-label="__('matches.home.all_teams').' →'">
                <div class="grid grid-cols-2 gap-2">
                    @forelse($teams as $editionTeam)
                        @if($editionTeam->team)
                            <a
                                href="{{ route('public.teams.show', $editionTeam->team) }}"
                                class="flex min-h-12 min-w-0 items-center gap-2.5 rounded-lg border border-line p-2 transition hover:border-slate-300 hover:bg-slate-50"
                            >
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-full border border-line bg-slate-50 text-slate-300">
                                    @if($editionTeam->team->logo_path)
                                        <img
                                            src="{{ Illuminate\Support\Facades\Storage::url($editionTeam->team->logo_path) }}"
                                            alt="{{ $editionTeam->team->name }}"
                                            class="h-full w-full object-cover"
                                        />
                                    @else
                                        <x-icon name="shield" class="h-4 w-4" />
                                    @endif
                                </span>
                                <span class="min-w-0">
                                    <span class="block truncate text-[13px] font-medium text-slate-800">{{ $editionTeam->team->name }}</span>
                                    @if($editionTeam->team->short_name)
                                        <span class="block truncate text-[11px] text-slate-500">{{ $editionTeam->team->short_name }}</span>
                                    @endif
                                </span>
                            </a>
                        @endif
                    @empty
                        <p class="pub-empty col-span-full">{{ __('matches.home.no_teams') }}</p>
                    @endforelse
                </div>
            </x-public.card>
        </div>
    @endif
@endsection
