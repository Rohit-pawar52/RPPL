{{--
    Compact Match Centre strip — LIVE > NEXT > RECENT priority (frozen
    for this pass). Expects $liveMatch/$liveMatchData, $nextMatch,
    $recentMatch (any may be null). Renders nothing at all if none of
    the three exist, so a brand-new edition with zero matches never
    shows an empty/broken strip.

    Homepage-only for now, not a global layout partial: it depends on
    HomeController's own Match Centre query (live/next/recent), which
    no other public controller currently computes. Wiring it into every
    public page would mean duplicating that query (or a shared
    composer) across controllers that don't otherwise need it — a
    bigger change than this pass's scope; see the completion report.
--}}
@if($liveMatch || $nextMatch || $recentMatch)
    <div class="mt-3 flex gap-2.5 overflow-x-auto pb-1 sm:mt-4">
        @if($liveMatch)
            <a
                href="{{ route('public.matches.live', $liveMatch) }}"
                class="flex w-64 shrink-0 flex-col gap-1.5 rounded-lg border border-green-200 bg-green-50/60 p-3 hover:border-green-300 hover:bg-green-50"
            >
                <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wide text-green-700">
                    <span class="relative flex h-2 w-2">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-500 opacity-75"></span>
                        <span class="relative inline-flex h-2 w-2 rounded-full bg-green-600"></span>
                    </span>
                    {{ __('matches.centre.live') }}
                </span>

                @foreach($liveMatchData['innings'] as $innings)
                    <p class="text-[13px] text-neutral-800">
                        <span class="font-medium">{{ $innings['batting_team'] }}</span>
                        {{ $innings['total_runs'] }}/{{ $innings['total_wickets'] }}
                        <span class="text-neutral-500">({{ $innings['overs_display'] }})</span>
                    </p>
                @endforeach

                @if($liveMatchData['chase'])
                    <p class="text-[11px] text-neutral-600">
                        {{ __('matches.chase.need_from_balls', ['runs' => $liveMatchData['chase']['runs_needed'], 'balls' => $liveMatchData['chase']['balls_remaining']]) }}
                    </p>
                @endif

                <span class="mt-auto text-[11px] font-medium theme-link">{{ __('matches.centre.view_live') }} &rarr;</span>
            </a>
        @elseif($nextMatch)
            <a
                href="{{ route('public.matches.show', $nextMatch) }}"
                class="flex w-64 shrink-0 flex-col gap-1.5 rounded-lg border border-blue-200 bg-blue-50/60 p-3 hover:border-blue-300 hover:bg-blue-50"
            >
                <span class="text-[11px] font-semibold uppercase tracking-wide text-blue-700">{{ __('matches.centre.next_match') }}</span>
                <p class="text-[13px] font-medium text-neutral-800">
                    {{ $nextMatch->teamA->team->short_name ?: $nextMatch->teamA->team->name }}
                    {{ __('matches.common.vs') }}
                    {{ $nextMatch->teamB->team->short_name ?: $nextMatch->teamB->team->name }}
                </p>
                <p class="text-[11px] text-neutral-600">
                    {{ display_datetime($nextMatch->scheduled_at, 'd M, h:i A') }}
                    @if($nextMatch->venue)
                        &middot; {{ $nextMatch->venue->name }}
                    @endif
                </p>
                <span class="mt-auto text-[11px] font-medium theme-link">{{ __('matches.centre.match_info') }} &rarr;</span>
            </a>
        @endif

        @if($recentMatch)
            <a
                href="{{ route('public.matches.scorecard', $recentMatch) }}"
                class="flex w-64 shrink-0 flex-col gap-1.5 rounded-lg border border-neutral-200 bg-white p-3 hover:bg-neutral-50"
            >
                <span class="text-[11px] font-semibold uppercase tracking-wide text-neutral-400">{{ __('matches.centre.recent_result') }}</span>

                @if($recentMatch->firstInnings)
                    <p class="text-[13px] text-neutral-800">
                        <span class="font-medium">{{ $recentMatch->firstInnings->battingTeam->team->short_name ?: $recentMatch->firstInnings->battingTeam->team->name }}</span>
                        {{ $recentMatch->firstInnings->total_runs }}/{{ $recentMatch->firstInnings->total_wickets }}
                    </p>
                @endif
                @if($recentMatch->secondInnings)
                    <p class="text-[13px] text-neutral-800">
                        <span class="font-medium">{{ $recentMatch->secondInnings->battingTeam->team->short_name ?: $recentMatch->secondInnings->battingTeam->team->name }}</span>
                        {{ $recentMatch->secondInnings->total_runs }}/{{ $recentMatch->secondInnings->total_wickets }}
                    </p>
                @endif

                <p class="text-[11px] text-neutral-600">{{ $recentMatch->match_result ?: __('matches.common.result_unavailable') }}</p>
                <span class="mt-auto text-[11px] font-medium theme-link">{{ __('matches.centre.scorecard') }} &rarr;</span>
            </a>
        @endif
    </div>
@endif
