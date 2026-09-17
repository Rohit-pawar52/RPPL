<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Scorecard &middot; {{ $match->teamA->team->name }} vs {{ $match->teamB->team->name }}</title>
    <style>
        body {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            color: #1f2937;
            font-size: 11px;
            margin: 0;
            padding: 24px;
        }
        .brand {
            text-align: center;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 10px;
            margin-bottom: 12px;
        }
        .brand .title {
            font-size: 16px;
            font-weight: bold;
            color: #1d4ed8;
        }
        .brand .subtitle {
            font-size: 11px;
            color: #6b7280;
            margin-top: 2px;
        }
        .match-header {
            text-align: center;
            margin-bottom: 4px;
        }
        .match-header .teams {
            font-size: 14px;
            font-weight: bold;
            color: #111827;
        }
        .match-header .status {
            font-size: 10px;
            color: #1d4ed8;
            font-weight: bold;
            text-transform: uppercase;
        }
        .match-meta {
            width: 100%;
            margin: 10px 0 16px;
            border-collapse: collapse;
        }
        .match-meta td {
            padding: 2px 4px;
            font-size: 10px;
            color: #374151;
        }
        .match-meta td.label {
            color: #6b7280;
            width: 90px;
        }
        .match-result {
            text-align: center;
            font-size: 11px;
            font-weight: bold;
            color: #111827;
            margin-bottom: 14px;
        }
        .innings {
            page-break-inside: avoid;
            margin-bottom: 18px;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            padding: 10px;
        }
        .innings-header {
            font-size: 12px;
            font-weight: bold;
            color: #111827;
            margin-bottom: 2px;
        }
        .innings-score {
            font-size: 11px;
            color: #374151;
            margin-bottom: 8px;
        }
        table.data {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 8px;
            page-break-inside: avoid;
        }
        table.data th {
            text-align: left;
            font-size: 9px;
            text-transform: uppercase;
            color: #6b7280;
            border-bottom: 1px solid #d1d5db;
            padding: 3px 4px;
        }
        table.data th.num, table.data td.num {
            text-align: right;
        }
        table.data td {
            font-size: 10px;
            padding: 3px 4px;
            border-bottom: 1px solid #f3f4f6;
            color: #1f2937;
        }
        .note-line {
            font-size: 9.5px;
            color: #4b5563;
            margin: 4px 0;
        }
        .note-line .label {
            font-weight: bold;
            color: #374151;
        }
        .footer {
            text-align: center;
            font-size: 9px;
            color: #9ca3af;
            margin-top: 16px;
        }
    </style>
</head>
<body>
    <div class="brand">
        <div class="title">RPPL Tournament</div>
        <div class="subtitle">Match Scorecard</div>
    </div>

    <div class="match-header">
        <div class="teams">{{ $match->teamA->team->name }} vs {{ $match->teamB->team->name }}</div>
        <div class="status">{{ str_replace('_', ' ', $match->match_status) }}</div>
    </div>

    <table class="match-meta">
        <tr>
            <td class="label">Edition</td>
            <td>{{ $match->edition->name }}</td>
            <td class="label">Venue</td>
            <td>{{ $match->venue->name ?? '—' }}</td>
        </tr>
        <tr>
            <td class="label">Match Date</td>
            <td>{{ optional($match->scheduled_at)->format('d M Y, h:i A') ?? '—' }}</td>
            @if($match->tossWinner)
                <td class="label">Toss</td>
                <td>{{ $match->tossWinner->team->name }} chose to {{ $match->toss_decision }}</td>
            @endif
        </tr>
    </table>

    @if($match->match_status === 'completed' && $match->match_result)
        <div class="match-result">{{ $match->match_result }}</div>
    @endif

    @foreach($inningsScorecards as $card)
        @php $innings = $card['innings']; @endphp
        <div class="innings">
            <div class="innings-header">Innings {{ $innings->innings_number }} &mdash; {{ $innings->battingTeam->team->name }}</div>
            <div class="innings-score">
                {{ $innings->total_runs }}/{{ $innings->total_wickets }} ({{ $innings->oversDisplay() }} overs)
                @if($innings->status === 'live') &mdash; <strong>LIVE</strong> @endif
            </div>

            <table class="data">
                <thead>
                    <tr>
                        <th>Batter</th>
                        <th>Dismissal</th>
                        <th class="num">R</th>
                        <th class="num">B</th>
                        <th class="num">4s</th>
                        <th class="num">6s</th>
                        <th class="num">SR</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($card['battingRows'] as $row)
                        @php $player = $row['matchPlayer']->teamPlayer->playerRegistration->player; @endphp
                        <tr>
                            <td>{{ $player->name }}</td>
                            <td>{{ $row['dismissalText'] }}</td>
                            <td class="num">{{ $row['runs'] }}</td>
                            <td class="num">{{ $row['balls'] }}</td>
                            <td class="num">{{ $row['fours'] }}</td>
                            <td class="num">{{ $row['sixes'] }}</td>
                            <td class="num">{{ number_format($row['strikeRate'], 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7">No batting activity yet.</td></tr>
                    @endforelse
                </tbody>
            </table>

            @if($card['didNotBat']->isNotEmpty())
                <div class="note-line">
                    <span class="label">Did not bat:</span>
                    {{ $card['didNotBat']->map(fn ($mp) => $mp->teamPlayer->playerRegistration->player->name)->implode(', ') }}
                </div>
            @endif

            <div class="note-line">
                <span class="label">Extras:</span> {{ $card['extras']['total'] }}
                (b {{ $card['extras']['byes'] }}, lb {{ $card['extras']['legByes'] }}, w {{ $card['extras']['wides'] }}, nb {{ $card['extras']['noBalls'] }}, p {{ $card['extras']['penalty'] }})
            </div>

            @if(! empty($card['fallOfWickets']))
                <div class="note-line">
                    <span class="label">Fall of wickets:</span>
                    {{ collect($card['fallOfWickets'])->map(fn ($fow) => "{$fow['wicketNumber']}-{$fow['score']} ({$fow['player']}, {$fow['overNotation']})")->implode(', ') }}
                </div>
            @endif

            <table class="data">
                <thead>
                    <tr>
                        <th>Bowler</th>
                        <th class="num">O</th>
                        <th class="num">R</th>
                        <th class="num">W</th>
                        <th class="num">Econ</th>
                        <th class="num">Wd</th>
                        <th class="num">NB</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($card['bowlingRows'] as $row)
                        @php $player = $row['matchPlayer']->teamPlayer->playerRegistration->player; @endphp
                        <tr>
                            <td>{{ $player->name }}</td>
                            <td class="num">{{ $row['oversDisplay'] }}</td>
                            <td class="num">{{ $row['runsConceded'] }}</td>
                            <td class="num">{{ $row['wickets'] }}</td>
                            <td class="num">{{ number_format($row['economy'], 2) }}</td>
                            <td class="num">{{ $row['wideRuns'] }}</td>
                            <td class="num">{{ $row['noBallRuns'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7">No bowling activity yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    @endforeach

    <div class="footer">
        <p>Generated by RPPL Tournament Management System &mdash; this is a computer-generated scorecard.</p>
    </div>
</body>
</html>
