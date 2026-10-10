<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Auction results &middot; {{ $edition->name }}</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; color: #1f2937; font-size: 11px; margin: 0; padding: 24px; }
        .brand { text-align: center; border-bottom: 1px solid #e5e7eb; padding-bottom: 10px; margin-bottom: 12px; }
        .brand .title { font-size: 16px; font-weight: bold; color: {{ $branding->primaryColor }}; }
        .brand .subtitle { font-size: 11px; color: {{ $branding->secondaryColor }}; margin-top: 2px; }
        h1 { font-size: 14px; text-align: center; margin: 0 0 4px; }
        .meta { text-align: center; color: #6b7280; font-size: 10px; margin-bottom: 14px; }
        .summary { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        .summary td { border: 1px solid #e5e7eb; padding: 6px; text-align: center; }
        .summary .n { font-size: 15px; font-weight: bold; display: block; }
        .team { margin-bottom: 14px; page-break-inside: avoid; }
        .team h2 { font-size: 12px; margin: 0 0 4px; padding: 5px 8px; background: #f3f4f6; }
        .team h2 span { float: right; font-weight: normal; color: #4b5563; }
        table.rows { width: 100%; border-collapse: collapse; }
        table.rows th { text-align: left; font-size: 9px; text-transform: uppercase; color: #6b7280; border-bottom: 1px solid #e5e7eb; padding: 3px 6px; }
        table.rows td { padding: 3px 6px; border-bottom: 1px solid #f3f4f6; }
        .r { text-align: right; }
        .empty { color: #9ca3af; padding: 6px; }
    </style>
</head>
<body>
    <div class="brand">
        <div class="title">{{ $branding->applicationName }}</div>
        <div class="subtitle">{{ $branding->shortName }}</div>
    </div>

    <h1>Player auction results &middot; {{ $edition->name }}</h1>
    <p class="meta">
        {{ ucfirst($auction->status) }}@if($auction->completed_at) &middot; completed {{ display_datetime($auction->completed_at, 'd M Y, h:i A') }}@endif
        &middot; purse {{ points($auction->team_purse) }} pts per team &middot; squad {{ $auction->min_squad }}&ndash;{{ $auction->max_squad }}
    </p>

    <table class="summary">
        <tr>
            <td><span class="n">{{ $counts['sold'] }}</span>Sold</td>
            <td><span class="n">{{ $counts['unsold'] }}</span>Unsold</td>
            <td><span class="n">{{ $counts['pending'] + $counts['hold'] + $counts['live'] }}</span>Still waiting</td>
            <td><span class="n">{{ points(collect($squads)->sum('spent')) }}</span>Points spent</td>
        </tr>
    </table>

    @foreach($squads as $team)
        <div class="team">
            <h2>{{ $team['name'] }} <span>{{ count($team['players']) }} players &middot; spent {{ points($team['spent']) }} &middot; left {{ points($team['left']) }}</span></h2>
            @if(count($team['players']))
                <table class="rows">
                    <thead><tr><th>#</th><th>Player</th><th>Village</th><th>Role</th><th class="r">Price (pts)</th></tr></thead>
                    <tbody>
                        @foreach($team['players'] as $player)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $player['name'] }}</td>
                                <td>{{ $player['village'] ?? '' }}</td>
                                <td>{{ $player['role'] ?? '' }}</td>
                                <td class="r">{{ $player['amount'] === null ? '-' : points($player['amount']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @else
                <p class="empty">No players yet.</p>
            @endif
        </div>
    @endforeach
</body>
</html>
