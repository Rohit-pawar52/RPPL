{{--
    Who is batting and bowling right now: the two batters at the crease (* = on strike), the bowler of this over and
    the one before, the partnership and the last wicket. Same data as the Scorecard tab, read from the live payload.
    Expects: $board (LiveMatchService 'board', never null here).
    KEEP IN SYNC with renderBoard() in resources/js/public-live-match.js.
--}}
<table class="mx-lb">
    <thead>
        <tr>
            <th>{{ __('ux_public_matches.live.batter') }}</th>
            <th>{{ __('ux_public_matches.live.col_runs') }}</th>
            <th>{{ __('ux_public_matches.live.col_balls') }}</th>
            <th>{{ __('ux_public_matches.live.col_fours') }}</th>
            <th>{{ __('ux_public_matches.live.col_sixes') }}</th>
            <th>{{ __('ux_public_matches.live.col_sr') }}</th>
        </tr>
    </thead>
    <tbody>
        @forelse($board['batters'] as $batter)
            <tr @class(['is-strike' => $batter['on_strike']])>
                <td class="mx-lb-name">{{ $batter['name'] }}@if($batter['on_strike'])<span class="mx-lb-star" title="{{ __('ux_public_matches.live.on_strike') }}">*</span>@endif</td>
                <td class="mx-lb-strong">{{ $batter['runs'] }}</td>
                <td>{{ $batter['balls'] }}</td>
                <td>{{ $batter['fours'] }}</td>
                <td>{{ $batter['sixes'] }}</td>
                <td>{{ number_format((float) $batter['strike_rate'], 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="mx-lb-empty">&mdash;</td></tr>
        @endforelse
    </tbody>
</table>

<table class="mx-lb">
    <thead>
        <tr>
            <th>{{ __('ux_public_matches.live.bowler') }}</th>
            <th>{{ __('ux_public_matches.live.col_overs') }}</th>
            <th>{{ __('ux_public_matches.live.col_conceded') }}</th>
            <th>{{ __('ux_public_matches.live.col_wickets') }}</th>
            <th>{{ __('ux_public_matches.live.col_eco') }}</th>
        </tr>
    </thead>
    <tbody>
        @forelse($board['bowlers'] as $bowler)
            <tr @class(['is-strike' => $bowler['current']])>
                <td class="mx-lb-name">{{ $bowler['name'] }}@if($bowler['current'])<span class="mx-lb-star">*</span>@endif</td>
                <td>{{ $bowler['overs'] }}</td>
                <td>{{ $bowler['runs'] }}</td>
                <td class="mx-lb-strong">{{ $bowler['wickets'] }}</td>
                <td>{{ number_format((float) $bowler['economy'], 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="mx-lb-empty">&mdash;</td></tr>
        @endforelse
    </tbody>
</table>

<dl class="mx-lb-facts">
    <div>
        <dt>{{ __('ux_public_matches.live.partnership') }}</dt>
        <dd>{{ $board['partnership']['runs'] }} ({{ $board['partnership']['balls'] }})</dd>
    </div>
    @if($board['last_wicket'])
        <div>
            <dt>{{ __('ux_public_matches.live.last_wkt') }}</dt>
            <dd>
                {{ $board['last_wicket']['player'] }}
                {{ __('ux_public_matches.live.last_wkt_at', [
                    'runs' => $board['last_wicket']['runs'] ?? 0,
                    'balls' => $board['last_wicket']['balls'] ?? 0,
                    'score' => $board['last_wicket']['team_score'].'/'.$board['last_wicket']['wickets'],
                    'over' => $board['last_wicket']['over'],
                ]) }}
            </dd>
        </div>
    @endif
</dl>
