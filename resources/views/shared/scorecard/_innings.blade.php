{{--
    One innings' full scorecard: batting table, Did Not Bat, extras,
    fall of wickets, bowling table. Shared verbatim between the admin
    and public scorecard pages - purely presentational, no admin-only
    controls, so both page shells can @include it as-is.

    Phone layout: each table scrolls sideways inside its own card with the
    player column pinned to the left, the dismissal sits under the batter's
    name, and the strike-rate / economy columns are drawn quieter than the
    runs and wickets that matter.

    Expects: $card (one entry from ScorecardService::getMatchScorecard())
    and $match (the match the innings belongs to).
--}}
@php
    $innings = $card['innings'];
    // The match can be abandoned/cancelled without ever touching Innings
    // rows (MatchFlowService intentionally leaves scoring history exactly
    // as it was), so a still-'live' innings under such a match is correct
    // data, not a bug - only the badge shown here needs to reflect the
    // match outcome instead of contradicting it.
    $inningsInterrupted = $innings->status === 'live' && in_array($match->match_status, ['abandoned', 'cancelled'], true);
    $extras = $card['extras'];
    $extraParts = collect([
        'b' => $extras['byes'],
        'lb' => $extras['legByes'],
        'w' => $extras['wides'],
        'nb' => $extras['noBalls'],
        'p' => $extras['penalty'],
    ])->filter(fn ($value) => (int) $value > 0)->map(fn ($value, $key) => "{$key} {$value}")->implode(', ');
@endphp
<section id="innings-{{ $innings->innings_number }}" class="mx-innings">
    <header class="mx-innings-head">
        <div class="flex min-w-0 items-center gap-3">
            <x-mx.team-logo :team="$innings->battingTeam->team" size="md" />
            <div class="min-w-0">
                <h3 class="truncate text-[15px] font-semibold tracking-tight text-slate-900">{{ $innings->battingTeam->team->name }}</h3>
                <p class="mt-0.5 flex items-center gap-2 text-xs text-slate-500">
                    {{ __('ux_public_matches.scorecard.innings_n', ['n' => $innings->innings_number]) }}
                    <x-public.status-pill :status="$inningsInterrupted ? $match->match_status : $innings->status" />
                </p>
            </div>
        </div>
        <p class="shrink-0 text-right">
            <span class="block text-2xl font-bold leading-none tracking-tight tabular-nums text-slate-900">{{ $innings->total_runs }}/{{ $innings->total_wickets }}</span>
            <span class="mt-1 block text-xs text-slate-500">({{ $innings->oversDisplay() }} overs)</span>
        </p>
    </header>

    @if($inningsInterrupted)
        <p class="mx-innings-note">{{ __('ux_public_matches.scorecard.interrupted', ['status' => app()->getLocale() !== 'en' && \Illuminate\Support\Facades\Lang::has('public.status.'.$match->match_status) ? __('public.status.'.$match->match_status) : $match->match_status]) }}</p>
    @endif

    @if($innings->status === 'live' && $card['lastDelivery'])
        <p class="mx-innings-note">
            {{ __('ux_public_matches.scorecard.last_delivery') }} {{ $card['lastDelivery']['striker'] }} &middot; {{ $card['lastDelivery']['nonStriker'] }} &middot; {{ __('ux_public_matches.scorecard.bowling', ['bowler' => $card['lastDelivery']['bowler']]) }}
        </p>
    @endif

    {{-- Batting --}}
    <div class="mx-scroll">
        <table class="mx-table">
            <thead>
                <tr>
                    <th class="mx-sticky">{{ __('ux_public_matches.scorecard.batter') }}</th>
                    <th class="hidden md:table-cell">{{ __('ux_public_matches.scorecard.dismissal') }}</th>
                    <th class="mx-num">R</th>
                    <th class="mx-num">B</th>
                    <th class="mx-num">4s</th>
                    <th class="mx-num">6s</th>
                    <th class="mx-num mx-quiet">SR</th>
                </tr>
            </thead>
            <tbody>
                @forelse($card['battingRows'] as $row)
                    @php
                        $player = $row['matchPlayer']->teamPlayer->playerRegistration->player;
                        $notOut = $row['dismissalText'] === 'not out';
                    @endphp
                    <tr>
                        <td class="mx-sticky">
                            <span class="block font-semibold text-slate-900">{{ $player->name }}</span>
                            <span @class(['mx-dismissal md:hidden', 'is-notout' => $notOut])>{{ $row['dismissalText'] }}</span>
                        </td>
                        <td @class(['mx-dismissal hidden md:table-cell', 'is-notout' => $notOut])>{{ $row['dismissalText'] }}</td>
                        <td class="mx-num mx-strong">{{ $row['runs'] }}</td>
                        <td class="mx-num">{{ $row['balls'] }}</td>
                        <td class="mx-num">{{ $row['fours'] }}</td>
                        <td class="mx-num">{{ $row['sixes'] }}</td>
                        <td class="mx-num mx-quiet">{{ number_format($row['strikeRate'], 2) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="mx-empty-cell">{{ __('ux_public_matches.scorecard.no_batting') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($card['didNotBat']->isNotEmpty())
        <p class="mx-innings-line">
            <span class="mx-innings-label">{{ __('ux_public_matches.scorecard.did_not_bat') }}</span>
            {{ $card['didNotBat']->map(fn ($mp) => $mp->teamPlayer->playerRegistration->player->name)->implode(', ') }}
        </p>
    @endif

    {{-- Extras / total --}}
    <div class="mx-innings-total">
        <p>
            <span class="mx-innings-label">{{ __('ux_public_matches.scorecard.extras') }}</span>
            <span class="font-semibold text-slate-800">{{ $extras['total'] }}</span>
            @if($extraParts !== '')
                <span class="text-slate-400">({{ $extraParts }})</span>
            @endif
        </p>
        <p>
            <span class="mx-innings-label">{{ __('ux_public_matches.scorecard.total') }}</span>
            <span class="text-base font-bold tabular-nums text-slate-900">{{ $innings->total_runs }}/{{ $innings->total_wickets }}</span>
            <span class="text-slate-400">({{ $innings->oversDisplay() }} overs)</span>
        </p>
    </div>

    {{-- Fall of wickets --}}
    @if(! empty($card['fallOfWickets']))
        <div class="mx-innings-line">
            <p class="mx-innings-label mb-1.5">{{ __('ux_public_matches.scorecard.fall_of_wickets') }}</p>
            <ul class="mx-fow">
                @foreach($card['fallOfWickets'] as $fow)
                    <li>
                        <b>{{ $fow['wicketNumber'] }}-{{ $fow['score'] }}</b>
                        <span>{{ $fow['player'] }}</span>
                        <i>{{ __('matches.common.overs_short', ['overs' => $fow['overNotation']]) }}</i>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Bowling --}}
    <div class="mx-scroll mt-2 border-t border-line">
        <table class="mx-table">
            <thead>
                <tr>
                    <th class="mx-sticky">{{ __('ux_public_matches.scorecard.bowler') }}</th>
                    <th class="mx-num">O</th>
                    <th class="mx-num">R</th>
                    <th class="mx-num">W</th>
                    <th class="mx-num mx-quiet">Econ</th>
                    <th class="mx-num mx-quiet hidden md:table-cell">Wd</th>
                    <th class="mx-num mx-quiet hidden md:table-cell">NB</th>
                </tr>
            </thead>
            <tbody>
                @forelse($card['bowlingRows'] as $row)
                    @php $player = $row['matchPlayer']->teamPlayer->playerRegistration->player; @endphp
                    <tr>
                        <td class="mx-sticky"><span class="block font-semibold text-slate-900">{{ $player->name }}</span></td>
                        <td class="mx-num">{{ $row['oversDisplay'] }}</td>
                        <td class="mx-num">{{ $row['runsConceded'] }}</td>
                        <td class="mx-num mx-strong">{{ $row['wickets'] }}</td>
                        <td class="mx-num mx-quiet">{{ number_format($row['economy'], 2) }}</td>
                        <td class="mx-num mx-quiet hidden md:table-cell">{{ $row['wideRuns'] }}</td>
                        <td class="mx-num mx-quiet hidden md:table-cell">{{ $row['noBallRuns'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="mx-empty-cell">{{ __('ux_public_matches.scorecard.no_bowling') }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
