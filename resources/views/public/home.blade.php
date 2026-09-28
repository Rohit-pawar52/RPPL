@extends('layouts.public')

@section('title', $branding->shortName.' · '.$branding->applicationName)

{{--
    Public homepage — compact, match-first tournament portal. Top to
    bottom: edition context strip → Match Centre → Featured Match →
    Points Table → Recent Results / Upcoming Matches → Teams → Top
    Performers. Purely presentational: every score, standing and stat
    comes pre-computed from HomeController (LiveMatchService,
    StandingsService, PlayerStatisticsService) — nothing is recalculated
    here. Every section has its own empty state so an edition with no
    matches/teams/stats yet still renders cleanly.
--}}
@section('content')
    {{-- Edition context strip: application name + tagline (settings-
         driven, asserted by the branding tests) and the current edition
         with its status. Deliberately one slim row — no hero banner. --}}
    <div class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
        <div class="min-w-0">
            <h1 class="truncate text-sm font-semibold text-neutral-900">
                {{ $edition?->name ?? $branding->applicationName }}
            </h1>
            <p class="truncate text-[11px] {{ $branding->tagline ? 'theme-secondary-text' : 'text-neutral-500' }}">
                {{ $branding->tagline ?: 'Local cricket tournament scores, fixtures, and standings.' }}
            </p>
        </div>
        @if($edition)
            <div class="flex shrink-0 items-center gap-2">
                <x-status-badge :status="$edition->status" />
                <a href="{{ route('public.editions.show', $edition) }}" class="text-[11px] font-medium theme-link hover:underline">
                    Edition details &rarr;
                </a>
            </div>
        @endif
    </div>

    @if(! $edition)
        <div class="mt-4 rounded-lg border border-neutral-200 bg-white p-6 text-center">
            <x-icon name="trophy" class="mx-auto h-5 w-5 text-neutral-300" />
            <p class="mt-2 text-[13px] text-neutral-500">No tournament editions available yet.</p>
            <p class="mt-0.5 text-[11px] text-neutral-400">Fixtures, scores and standings will appear here once a tournament is set up.</p>
        </div>
    @else
        {{-- 1. Match Centre strip (LIVE / NEXT / RECENT) --}}
        @include('public._match-centre', [
            'liveMatch' => $liveMatch,
            'liveMatchData' => $liveMatchData,
            'nextMatch' => $nextMatch,
            'recentMatch' => $recentMatch,
        ])

        {{-- 2. Featured match card --}}
        @include('public._featured-match', [
            'liveMatch' => $liveMatch,
            'liveMatchData' => $liveMatchData,
            'nextMatch' => $nextMatch,
            'recentMatch' => $recentMatch,
        ])

        @if(! $liveMatch && ! $nextMatch && ! $recentMatch)
            <div class="mt-3 rounded-lg border border-neutral-200 bg-white p-4 text-center sm:mt-4">
                <x-icon name="calendar" class="mx-auto h-5 w-5 text-neutral-300" />
                <p class="mt-1.5 text-[13px] text-neutral-500">No matches scheduled yet.</p>
                <p class="text-[11px] text-neutral-400">The fixture list will appear here once matches are announced.</p>
            </div>
        @endif

        {{-- 3. Points Table preview --}}
        <section class="mt-4 rounded-lg border border-neutral-200 bg-white p-4">
            <div class="mb-2 flex items-center justify-between gap-2">
                <h2 class="inline-flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">
                    <x-icon name="chart-bar" class="h-4 w-4" />
                    Points Table
                </h2>
                <a href="{{ route('public.editions.show', $edition) }}" class="text-[11px] font-medium theme-link hover:underline">Full table &rarr;</a>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[320px] text-left text-[13px]">
                    <thead class="border-b border-neutral-200 text-[11px] uppercase tracking-wide text-neutral-400">
                        <tr>
                            <th class="w-8 px-2 py-1.5 font-medium">#</th>
                            <th class="px-2 py-1.5 font-medium">Team</th>
                            <th class="px-2 py-1.5 text-right font-medium">P</th>
                            <th class="px-2 py-1.5 text-right font-medium">W</th>
                            <th class="px-2 py-1.5 text-right font-medium">L</th>
                            <th class="px-2 py-1.5 text-right font-medium">Pts</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-neutral-100">
                        @forelse($standings as $row)
                            @php $standingTeam = $row['edition_team']?->team; @endphp
                            <tr>
                                <td class="px-2 py-1.5 text-neutral-500">{{ $row['position'] }}</td>
                                <td class="px-2 py-1.5 font-medium text-neutral-800">
                                    @if($standingTeam)
                                        <a href="{{ route('public.teams.show', $standingTeam) }}" class="hover:underline">
                                            <span class="sm:hidden">{{ $standingTeam->short_name ?: $standingTeam->name }}</span>
                                            <span class="hidden sm:inline">{{ $standingTeam->name }}</span>
                                        </a>
                                    @else
                                        &mdash;
                                    @endif
                                </td>
                                <td class="px-2 py-1.5 text-right text-neutral-700">{{ $row['played'] }}</td>
                                <td class="px-2 py-1.5 text-right text-neutral-700">{{ $row['won'] }}</td>
                                <td class="px-2 py-1.5 text-right text-neutral-700">{{ $row['lost'] }}</td>
                                <td class="px-2 py-1.5 text-right font-semibold text-neutral-900">{{ $row['points'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-2 py-4 text-center text-[11px] text-neutral-400">Standings will appear once teams are added.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- 4. Recent Results + Upcoming Matches --}}
        <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
            <section class="rounded-lg border border-neutral-200 bg-white p-4">
                <div class="mb-1 flex items-center justify-between gap-2">
                    <h2 class="inline-flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">
                        <x-icon name="trophy" class="h-4 w-4" />
                        Recent Results
                    </h2>
                    <a href="{{ route('public.matches.index') }}" class="text-[11px] font-medium theme-link hover:underline">View All Matches &rarr;</a>
                </div>
                @forelse($recentMatches as $match)
                    @php
                        $homeTeam = $match->teamA?->team;
                        $awayTeam = $match->teamB?->team;
                    @endphp
                    <a href="{{ route('public.matches.scorecard', $match) }}" class="flex items-center justify-between gap-3 border-b border-neutral-100 py-2 last:border-b-0 hover:bg-neutral-50">
                        <div class="min-w-0">
                            <p class="truncate text-[13px] font-medium text-neutral-800">
                                {{ $homeTeam?->short_name ?: $homeTeam?->name }}
                                <span class="font-normal text-neutral-400">vs</span>
                                {{ $awayTeam?->short_name ?: $awayTeam?->name }}
                            </p>
                            <p class="truncate text-[11px] text-neutral-500">
                                {{ $match->match_result ?: 'Result unavailable' }}
                            </p>
                        </div>
                        <span class="shrink-0 text-[11px] text-neutral-400">{{ display_datetime($match->scheduled_at, 'd M') }}</span>
                    </a>
                @empty
                    <p class="py-4 text-center text-[11px] text-neutral-400">No completed matches yet.</p>
                @endforelse
            </section>

            <section class="rounded-lg border border-neutral-200 bg-white p-4">
                <div class="mb-1 flex items-center justify-between gap-2">
                    <h2 class="inline-flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">
                        <x-icon name="calendar" class="h-4 w-4" />
                        Upcoming Matches
                    </h2>
                    <a href="{{ route('public.matches.index') }}" class="text-[11px] font-medium theme-link hover:underline">View All Matches &rarr;</a>
                </div>
                @forelse($upcomingMatches as $match)
                    @php
                        $homeTeam = $match->teamA?->team;
                        $awayTeam = $match->teamB?->team;
                        $isLive = $match->match_status === 'live';
                    @endphp
                    <a href="{{ $isLive ? route('public.matches.live', $match) : route('public.matches.show', $match) }}" class="flex items-center justify-between gap-3 border-b border-neutral-100 py-2 last:border-b-0 hover:bg-neutral-50">
                        <div class="min-w-0">
                            <p class="truncate text-[13px] font-medium text-neutral-800">
                                {{ $homeTeam?->short_name ?: $homeTeam?->name }}
                                <span class="font-normal text-neutral-400">vs</span>
                                {{ $awayTeam?->short_name ?: $awayTeam?->name }}
                            </p>
                            <p class="truncate text-[11px] text-neutral-500">
                                {{ display_datetime($match->scheduled_at, 'd M, h:i A') }}
                                @if($match->venue)
                                    &middot; {{ $match->venue->name }}
                                @endif
                            </p>
                        </div>
                        <x-status-badge :status="$match->match_status" />
                    </a>
                @empty
                    <p class="py-4 text-center text-[11px] text-neutral-400">No matches scheduled yet.</p>
                @endforelse
            </section>
        </div>

        {{-- 5. Teams --}}
        <section class="mt-4 rounded-lg border border-neutral-200 bg-white p-4">
            <div class="mb-2 flex items-center justify-between gap-2">
                <h2 class="inline-flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">
                    <x-icon name="shield" class="h-4 w-4" />
                    Teams
                </h2>
                <a href="{{ route('public.teams.index') }}" class="text-[11px] font-medium theme-link hover:underline">All teams &rarr;</a>
            </div>
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
                @forelse($teams as $editionTeam)
                    @if($editionTeam->team)
                        <a
                            href="{{ route('public.teams.show', $editionTeam->team) }}"
                            class="flex min-w-0 items-center gap-2 rounded-md border border-neutral-200 p-2 hover:bg-neutral-50"
                        >
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-full border border-neutral-200 bg-neutral-50 text-neutral-300">
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
                                <span class="block truncate text-[13px] font-medium text-neutral-800">{{ $editionTeam->team->name }}</span>
                                @if($editionTeam->team->short_name)
                                    <span class="block truncate text-[11px] text-neutral-500">{{ $editionTeam->team->short_name }}</span>
                                @endif
                            </span>
                        </a>
                    @endif
                @empty
                    <p class="col-span-full py-4 text-center text-[11px] text-neutral-400">No teams participating yet.</p>
                @endforelse
            </div>
        </section>

        {{-- 6. Top Performers --}}
        <section class="mt-4 rounded-lg border border-neutral-200 bg-white p-4">
            <div class="mb-2 flex items-center justify-between gap-2">
                <h2 class="inline-flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">
                    <x-icon name="star" class="h-4 w-4" />
                    Top Performers
                </h2>
                <a href="{{ route('public.editions.show', $edition) }}" class="text-[11px] font-medium theme-link hover:underline">More stats &rarr;</a>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <p class="mb-1 text-[11px] font-medium text-neutral-500">Most Runs</p>
                    @forelse($topRunScorers as $entry)
                        <div class="flex items-center justify-between gap-2 border-b border-neutral-100 py-1.5 text-[13px] last:border-b-0">
                            <span class="flex min-w-0 items-center gap-2">
                                <span class="w-4 shrink-0 text-[11px] text-neutral-400">{{ $loop->iteration }}</span>
                                <span class="truncate text-neutral-800">{{ $entry['player']?->name }}</span>
                            </span>
                            <span class="shrink-0 font-semibold text-neutral-900">{{ $entry['stats']['runs'] ?? 0 }}</span>
                        </div>
                    @empty
                        <p class="py-2 text-[11px] text-neutral-400">Player statistics will appear once scoring begins.</p>
                    @endforelse
                </div>

                <div>
                    <p class="mb-1 text-[11px] font-medium text-neutral-500">Most Wickets</p>
                    @forelse($topWicketTakers as $entry)
                        <div class="flex items-center justify-between gap-2 border-b border-neutral-100 py-1.5 text-[13px] last:border-b-0">
                            <span class="flex min-w-0 items-center gap-2">
                                <span class="w-4 shrink-0 text-[11px] text-neutral-400">{{ $loop->iteration }}</span>
                                <span class="truncate text-neutral-800">{{ $entry['player']?->name }}</span>
                            </span>
                            <span class="shrink-0 font-semibold text-neutral-900">{{ $entry['stats']['wickets'] ?? 0 }}</span>
                        </div>
                    @empty
                        <p class="py-2 text-[11px] text-neutral-400">Player statistics will appear once scoring begins.</p>
                    @endforelse
                </div>
            </div>
        </section>
    @endif
@endsection
