<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Edition Summary &middot; {{ $edition->name }}</title>
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
            color: {{ $branding->primaryColor }};
        }
        .brand .subtitle {
            font-size: 11px;
            color: {{ $branding->secondaryColor }};
            margin-top: 2px;
        }
        .edition-header {
            text-align: center;
            margin-bottom: 12px;
        }
        .edition-header .name {
            font-size: 14px;
            font-weight: bold;
            color: #111827;
        }
        .edition-header .status {
            font-size: 10px;
            color: #1d4ed8;
            font-weight: bold;
            text-transform: uppercase;
        }
        .section {
            page-break-inside: avoid;
            margin-bottom: 16px;
        }
        .section h2 {
            font-size: 12px;
            color: #111827;
            border-bottom: 1px solid #d1d5db;
            padding-bottom: 3px;
            margin: 0 0 6px;
        }
        table.data {
            width: 100%;
            border-collapse: collapse;
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
        .summary-line {
            font-size: 10.5px;
            color: #374151;
            margin: 2px 0;
        }
        .summary-line .label {
            color: #6b7280;
        }
        .empty-note {
            font-size: 10px;
            color: #9ca3af;
            font-style: italic;
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
        <div class="title">{{ $branding->applicationName }}</div>
        <div class="subtitle">Edition Summary</div>
    </div>

    <div class="edition-header">
        <div class="name">{{ $edition->name }} ({{ $edition->year }})</div>
        <div class="status">{{ $edition->status }}</div>
    </div>

    {{-- A. Edition overview --}}
    <div class="section">
        <h2>Edition Overview</h2>
        <p class="summary-line"><span class="label">Registered Players:</span> {{ $edition->player_registrations_count }}</p>
        <p class="summary-line"><span class="label">Participating Teams:</span> {{ $edition->edition_teams_count }}</p>
        <p class="summary-line"><span class="label">Total Matches:</span> {{ $edition->matches_count }}</p>
        @if($matchStatusCounts->isNotEmpty())
            <p class="summary-line">
                <span class="label">By Status:</span>
                {{ $matchStatusCounts->map(fn ($count, $status) => ucfirst(str_replace('_', ' ', $status)).': '.$count)->implode(', ') }}
            </p>
        @endif
    </div>

    {{-- Registration summary --}}
    <div class="section">
        <h2>Registration Summary</h2>
        @if($registrationCounts->isEmpty())
            <p class="empty-note">No player registrations yet.</p>
        @else
            <p class="summary-line">
                <span class="label">Total Registrations:</span> {{ $registrationCounts->sum() }}
                &mdash;
                {{ $registrationCounts->map(fn ($count, $status) => ucfirst($status).': '.$count)->implode(', ') }}
            </p>
            @if($paidRegistrationFees > 0)
                <p class="summary-line"><span class="label">Paid Registration Fees:</span> {{ money($paidRegistrationFees) }}</p>
            @endif
        @endif
    </div>

    {{-- Teams --}}
    <div class="section">
        <h2>Participating Teams</h2>
        @if($teams->isEmpty())
            <p class="empty-note">No teams have joined this edition yet.</p>
        @else
            <table class="data">
                <thead>
                    <tr>
                        <th>Team</th>
                        <th class="num">Squad Size</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($teams as $editionTeam)
                        <tr>
                            <td>{{ $editionTeam->team->name }}</td>
                            <td class="num">{{ $editionTeam->team_players_count }}</td>
                            <td>{{ $editionTeam->team->is_active ? 'Active' : 'Inactive' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{-- Match summary --}}
    <div class="section">
        <h2>Match Summary</h2>
        @if($matches->isEmpty())
            <p class="empty-note">No matches scheduled yet.</p>
        @else
            <table class="data">
                <thead>
                    <tr>
                        <th>Match</th>
                        <th>Stage</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Result</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($matches as $match)
                        <tr>
                            <td>{{ $match->teamA->team->name }} vs {{ $match->teamB->team->name }}</td>
                            <td>{{ ucfirst(str_replace('_', ' ', $match->match_stage ?? '—')) }}</td>
                            <td>{{ display_datetime($match->scheduled_at, 'd M Y') ?? '—' }}</td>
                            <td>{{ ucfirst(str_replace('_', ' ', $match->match_status)) }}</td>
                            <td>{{ in_array($match->match_status, ['completed', 'abandoned', 'cancelled'], true) ? ($match->match_result ?? '—') : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{-- Standings --}}
    <div class="section">
        <h2>Standings</h2>
        @if(empty($standings['standings']))
            <p class="empty-note">No completed match data available.</p>
        @else
            <table class="data">
                <thead>
                    <tr>
                        <th class="num">Pos</th>
                        <th>Team</th>
                        <th class="num">P</th>
                        <th class="num">W</th>
                        <th class="num">L</th>
                        <th class="num">T</th>
                        <th class="num">NR</th>
                        <th class="num">Pts</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($standings['standings'] as $row)
                        <tr>
                            <td class="num">{{ $row['position'] }}</td>
                            <td>{{ $row['edition_team']->team->name }}</td>
                            <td class="num">{{ $row['played'] }}</td>
                            <td class="num">{{ $row['won'] }}</td>
                            <td class="num">{{ $row['lost'] }}</td>
                            <td class="num">{{ $row['tied'] }}</td>
                            <td class="num">{{ $row['no_result'] }}</td>
                            <td class="num">{{ $row['points'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{-- Top run scorers --}}
    <div class="section">
        <h2>Top Run Scorers</h2>
        @if(empty($leaderboard['topRunScorers']))
            <p class="empty-note">No batting statistics available.</p>
        @else
            <table class="data">
                <thead>
                    <tr>
                        <th>Player</th>
                        <th class="num">Runs</th>
                        <th class="num">Innings</th>
                        <th class="num">Avg</th>
                        <th class="num">SR</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($leaderboard['topRunScorers'] as $entry)
                        <tr>
                            <td>{{ $entry['player']->name }}</td>
                            <td class="num">{{ $entry['stats']['runs'] }}</td>
                            <td class="num">{{ $entry['stats']['innings_batted'] }}</td>
                            <td class="num">{{ $entry['stats']['batting_average'] !== null ? number_format($entry['stats']['batting_average'], 2) : '—' }}</td>
                            <td class="num">{{ number_format($entry['stats']['strike_rate'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{-- Top wicket takers --}}
    <div class="section">
        <h2>Top Wicket Takers</h2>
        @if(empty($leaderboard['topWicketTakers']))
            <p class="empty-note">No bowling statistics available.</p>
        @else
            <table class="data">
                <thead>
                    <tr>
                        <th>Player</th>
                        <th class="num">Wickets</th>
                        <th class="num">Innings</th>
                        <th class="num">Avg</th>
                        <th class="num">Econ</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($leaderboard['topWicketTakers'] as $entry)
                        <tr>
                            <td>{{ $entry['player']->name }}</td>
                            <td class="num">{{ $entry['stats']['wickets'] }}</td>
                            <td class="num">{{ $entry['stats']['innings_bowled'] }}</td>
                            <td class="num">{{ $entry['stats']['bowling_average'] !== null ? number_format($entry['stats']['bowling_average'], 2) : '—' }}</td>
                            <td class="num">{{ number_format($entry['stats']['economy'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <div class="footer">
        <p>Generated by {{ $branding->applicationName }} on {{ display_datetime(now(), 'd M Y, h:i A') }}.</p>
    </div>
</body>
</html>
