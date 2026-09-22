{{--
    One innings' full scorecard: batting table, Did Not Bat, extras,
    fall of wickets, bowling table. Shared verbatim between the admin
    and public scorecard pages — purely presentational, no admin-only
    controls, so both page shells can @include it as-is.

    Expects: $card (one entry from ScorecardService::getMatchScorecard())
--}}
@php
    $innings = $card['innings'];
    // The match can be abandoned/cancelled without ever touching Innings
    // rows (MatchFlowService intentionally leaves scoring history exactly
    // as it was), so a still-'live' innings under such a match is correct
    // data, not a bug — only the badge shown here needs to reflect the
    // match outcome instead of contradicting it.
    $inningsInterrupted = $innings->status === 'live' && in_array($match->match_status, ['abandoned', 'cancelled'], true);
@endphp
<div class="mt-4 rounded-lg border border-neutral-200 bg-white p-4">
    <div class="flex items-center justify-between gap-3">
        <h3 class="text-sm font-semibold text-neutral-900">
            Innings {{ $innings->innings_number }} &mdash; {{ $innings->battingTeam->team->name }}
        </h3>
        <x-status-badge :status="$inningsInterrupted ? $match->match_status : $innings->status" />
    </div>
    <p class="mt-1 text-lg font-semibold text-neutral-900">
        {{ $innings->total_runs }}/{{ $innings->total_wickets }}
        <span class="text-xs font-normal text-neutral-500">({{ $innings->oversDisplay() }} overs)</span>
    </p>

    @if($inningsInterrupted)
        <p class="mt-1 text-xs text-neutral-500">Innings in progress when the match was {{ $match->match_status }}.</p>
    @endif

    @if($innings->status === 'live' && $card['lastDelivery'])
        <p class="mt-1 text-xs text-neutral-500">
            Last delivery: {{ $card['lastDelivery']['striker'] }} &middot; {{ $card['lastDelivery']['nonStriker'] }} &middot; {{ $card['lastDelivery']['bowler'] }} bowling
        </p>
    @endif

    {{-- Batting --}}
    <div class="mt-4 overflow-x-auto">
        <table class="w-full min-w-[520px] text-left text-[13px]">
            <thead class="border-b border-neutral-200 text-[11px] uppercase tracking-wide text-neutral-400">
                <tr>
                    <th class="px-2 py-1.5 font-medium">Batter</th>
                    <th class="hidden px-2 py-1.5 font-medium md:table-cell">Dismissal</th>
                    <th class="px-2 py-1.5 text-right font-medium">R</th>
                    <th class="px-2 py-1.5 text-right font-medium">B</th>
                    <th class="hidden px-2 py-1.5 text-right font-medium md:table-cell">4s</th>
                    <th class="hidden px-2 py-1.5 text-right font-medium md:table-cell">6s</th>
                    <th class="px-2 py-1.5 text-right font-medium">SR</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100">
                @forelse($card['battingRows'] as $row)
                    @php $player = $row['matchPlayer']->teamPlayer->playerRegistration->player; @endphp
                    <tr>
                        <td class="px-2 py-1.5">
                            <div class="flex items-center gap-2">
                                <div class="flex h-6 w-6 shrink-0 items-center justify-center overflow-hidden rounded-full border border-neutral-200 bg-neutral-50 text-neutral-300">
                                    @if($player->photo_path)
                                        <img src="{{ Illuminate\Support\Facades\Storage::url($player->photo_path) }}" alt="{{ $player->name }}" class="h-full w-full object-cover" />
                                    @else
                                        <x-icon name="user" class="h-3.5 w-3.5" />
                                    @endif
                                </div>
                                <span class="font-medium text-neutral-800">{{ $player->name }}</span>
                            </div>
                        </td>
                        <td class="hidden px-2 py-1.5 text-neutral-500 md:table-cell">{{ $row['dismissalText'] }}</td>
                        <td class="px-2 py-1.5 text-right font-medium text-neutral-800">{{ $row['runs'] }}</td>
                        <td class="px-2 py-1.5 text-right text-neutral-600">{{ $row['balls'] }}</td>
                        <td class="hidden px-2 py-1.5 text-right text-neutral-600 md:table-cell">{{ $row['fours'] }}</td>
                        <td class="hidden px-2 py-1.5 text-right text-neutral-600 md:table-cell">{{ $row['sixes'] }}</td>
                        <td class="px-2 py-1.5 text-right text-neutral-600">{{ number_format($row['strikeRate'], 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-2 py-4 text-center text-neutral-400">No batting activity yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($card['didNotBat']->isNotEmpty())
        <p class="mt-2 text-xs text-neutral-500">
            <span class="font-medium text-neutral-600">Did not bat:</span>
            {{ $card['didNotBat']->map(fn ($mp) => $mp->teamPlayer->playerRegistration->player->name)->implode(', ') }}
        </p>
    @endif

    {{-- Extras / total --}}
    <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-neutral-100 pt-3 text-xs text-neutral-600">
        <span>
            Extras: {{ $card['extras']['total'] }}
            <span class="text-neutral-400">
                (b {{ $card['extras']['byes'] }}, lb {{ $card['extras']['legByes'] }}, w {{ $card['extras']['wides'] }}, nb {{ $card['extras']['noBalls'] }}, p {{ $card['extras']['penalty'] }})
            </span>
        </span>
        <span class="font-medium text-neutral-800">Total: {{ $innings->total_runs }}/{{ $innings->total_wickets }}</span>
    </div>

    {{-- Fall of wickets --}}
    @if(! empty($card['fallOfWickets']))
        <p class="mt-2 text-xs text-neutral-500">
            <span class="font-medium text-neutral-600">Fall of wickets:</span>
            {{ collect($card['fallOfWickets'])->map(fn ($fow) => "{$fow['wicketNumber']}-{$fow['score']} ({$fow['player']}, {$fow['overNotation']})")->implode(', ') }}
        </p>
    @endif

    {{-- Bowling --}}
    <div class="mt-4 overflow-x-auto">
        <table class="w-full min-w-[520px] text-left text-[13px]">
            <thead class="border-b border-neutral-200 text-[11px] uppercase tracking-wide text-neutral-400">
                <tr>
                    <th class="px-2 py-1.5 font-medium">Bowler</th>
                    <th class="px-2 py-1.5 text-right font-medium">O</th>
                    <th class="px-2 py-1.5 text-right font-medium">R</th>
                    <th class="px-2 py-1.5 text-right font-medium">W</th>
                    <th class="px-2 py-1.5 text-right font-medium">Econ</th>
                    <th class="hidden px-2 py-1.5 text-right font-medium md:table-cell">Wd</th>
                    <th class="hidden px-2 py-1.5 text-right font-medium md:table-cell">NB</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100">
                @forelse($card['bowlingRows'] as $row)
                    @php $player = $row['matchPlayer']->teamPlayer->playerRegistration->player; @endphp
                    <tr>
                        <td class="px-2 py-1.5">
                            <div class="flex items-center gap-2">
                                <div class="flex h-6 w-6 shrink-0 items-center justify-center overflow-hidden rounded-full border border-neutral-200 bg-neutral-50 text-neutral-300">
                                    @if($player->photo_path)
                                        <img src="{{ Illuminate\Support\Facades\Storage::url($player->photo_path) }}" alt="{{ $player->name }}" class="h-full w-full object-cover" />
                                    @else
                                        <x-icon name="user" class="h-3.5 w-3.5" />
                                    @endif
                                </div>
                                <span class="font-medium text-neutral-800">{{ $player->name }}</span>
                            </div>
                        </td>
                        <td class="px-2 py-1.5 text-right text-neutral-600">{{ $row['oversDisplay'] }}</td>
                        <td class="px-2 py-1.5 text-right text-neutral-600">{{ $row['runsConceded'] }}</td>
                        <td class="px-2 py-1.5 text-right font-medium text-neutral-800">{{ $row['wickets'] }}</td>
                        <td class="px-2 py-1.5 text-right text-neutral-600">{{ number_format($row['economy'], 2) }}</td>
                        <td class="hidden px-2 py-1.5 text-right text-neutral-600 md:table-cell">{{ $row['wideRuns'] }}</td>
                        <td class="hidden px-2 py-1.5 text-right text-neutral-600 md:table-cell">{{ $row['noBallRuns'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-2 py-4 text-center text-neutral-400">No bowling activity yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
