{{--
    Featured Current Match — the single most relevant match, the primary
    block of the homepage. Priority: LIVE > NEXT > most recent result.
    Uses the same LiveMatchService payload as the Live page (current
    bowler/striker come straight from the already-fetched
    recent_deliveries feed — no second query). Expects $liveMatch/
    $liveMatchData, $nextMatch, $recentMatch (any may be null); renders
    nothing if all three are null.
--}}
@if($liveMatch || $nextMatch || $recentMatch)
    <section class="mt-4">
        @if($liveMatch)
            @php $latestBall = $liveMatchData['recent_deliveries'][0] ?? null; @endphp
            <div class="pub-card overflow-hidden border-l-4 border-l-green-500">
                <div class="flex flex-wrap items-center justify-between gap-2 bg-green-50/60 px-4 py-3">
                    <div class="flex min-w-0 items-center gap-2.5">
                        <x-public.status-pill :status="$liveMatch->match_status" />
                        <h2 class="truncate text-sm font-semibold text-slate-900">
                            {{ $liveMatch->teamA->team->name }} {{ __('matches.common.vs') }} {{ $liveMatch->teamB->team->name }}
                        </h2>
                    </div>
                    <span class="pub-meta truncate">{{ $liveMatch->venue->name ?? '' }}</span>
                </div>

                <div class="grid divide-y divide-line sm:grid-cols-2 sm:divide-x sm:divide-y-0">
                    @foreach($liveMatchData['innings'] as $innings)
                        <div class="px-4 py-4 sm:px-5">
                            <p class="truncate text-sm font-semibold text-slate-800">{{ $innings['batting_team'] }}</p>
                            <p class="mt-1 flex flex-wrap items-baseline gap-x-2">
                                <span class="score-figure">{{ $innings['total_runs'] }}/{{ $innings['total_wickets'] }}</span>
                                <span class="text-xs text-slate-500">({{ __('matches.common.overs_short', ['overs' => $innings['overs_display']]) }}, {{ __('matches.chase.crr', ['rate' => number_format($innings['crr'], 2)]) }})</span>
                            </p>
                        </div>
                    @endforeach
                </div>

                <div class="space-y-1 border-t border-line px-4 py-3 text-[13px]">
                    @if($liveMatchData['chase'])
                        <p class="font-semibold text-green-800">
                            {{ __('matches.chase.target', ['target' => $liveMatchData['chase']['target']]) }}
                            &middot; {{ __('matches.chase.need_from_balls', ['runs' => $liveMatchData['chase']['runs_needed'], 'balls' => $liveMatchData['chase']['balls_remaining']]) }}
                            &middot; {{ __('matches.chase.rrr', ['rate' => number_format($liveMatchData['chase']['required_run_rate'], 2)]) }}
                        </p>
                    @endif
                    @if($latestBall)
                        <p class="pub-meta">
                            {{ __('matches.centre.bowler_to_striker', ['bowler' => $latestBall['bowler'], 'striker' => $latestBall['striker']]) }} &middot; {{ $latestBall['commentary'] }}
                        </p>
                    @endif
                    <a href="{{ route('public.matches.live', $liveMatch) }}" class="pub-btn mt-2 min-h-10">
                        {{ __('matches.centre.live_scorecard') }} &rarr;
                    </a>
                </div>
            </div>
        @elseif($nextMatch)
            <div class="pub-card overflow-hidden">
                <div class="flex items-center justify-between gap-2 border-b border-line px-4 py-3">
                    <span class="pub-eyebrow">{{ __('matches.centre.next_match') }}</span>
                    <x-public.status-pill :status="$nextMatch->match_status" />
                </div>
                <div class="px-4 py-5 sm:px-5">
                    <h2 class="flex flex-wrap items-center gap-x-4 gap-y-2">
                        @foreach([$nextMatch->teamA->team, $nextMatch->teamB->team] as $nextTeam)
                            @if(! $loop->first)
                                <span class="text-sm font-medium text-slate-400">{{ __('matches.common.vs') }}</span>
                            @endif
                            <span class="inline-flex items-center gap-2.5">
                                <span class="flex h-10 min-w-10 items-center justify-center rounded-full bg-navy-900 px-2 text-xs font-bold uppercase tracking-wide text-white">{{ $nextTeam->short_name ?: mb_substr($nextTeam->name, 0, 3) }}</span>
                                <span class="text-lg font-bold tracking-tight text-slate-900">{{ $nextTeam->name }}</span>
                            </span>
                        @endforeach
                    </h2>
                    <p class="mt-1 text-[13px] text-slate-600">
                        {{ display_datetime($nextMatch->scheduled_at, 'D, d M Y, h:i A') }}
                        @if($nextMatch->venue)
                            &middot; {{ $nextMatch->venue->name }}
                        @endif
                        &middot; {{ __('matches.common.overs_count', ['overs' => $nextMatch->overs_per_innings]) }}
                    </p>
                    <a href="{{ route('public.matches.show', $nextMatch) }}" class="pub-btn mt-3 min-h-10">{{ __('matches.centre.match_info') }} &rarr;</a>
                </div>
            </div>
        @elseif($recentMatch)
            <div class="pub-card overflow-hidden">
                <div class="flex items-center justify-between gap-2 border-b border-line px-4 py-3">
                    <span class="pub-eyebrow">{{ __('matches.centre.recent_result') }}</span>
                    <x-public.status-pill :status="$recentMatch->match_status" />
                </div>

                <div class="grid divide-y divide-line sm:grid-cols-2 sm:divide-x sm:divide-y-0">
                    @foreach([$recentMatch->firstInnings, $recentMatch->secondInnings] as $inn)
                        @if($inn)
                            <div class="px-4 py-4 sm:px-5">
                                <p class="truncate text-sm font-semibold text-slate-800">{{ $inn->battingTeam->team->name }}</p>
                                <p class="mt-1 flex items-baseline gap-2">
                                    <span class="score-figure">{{ $inn->total_runs }}/{{ $inn->total_wickets }}</span>
                                    <span class="text-xs text-slate-500">({{ __('matches.common.overs_short', ['overs' => $inn->oversDisplay()]) }})</span>
                                </p>
                            </div>
                        @endif
                    @endforeach
                </div>

                <div class="flex flex-wrap items-center justify-between gap-2 border-t border-line px-4 py-3">
                    <p class="text-[13px] font-semibold text-green-700">{{ $recentMatch->match_result ?: __('matches.common.result_unavailable') }}</p>
                    <a href="{{ route('public.matches.scorecard', $recentMatch) }}" class="pub-btn-outline pub-btn-sm min-h-10">{{ __('matches.centre.full_scorecard') }}</a>
                </div>
            </div>
        @endif
    </section>
@endif
