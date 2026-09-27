{{-- Frozen S02 rule 56 — compact current bowler figures. --}}
@if($player)
    {{ $player['name'] }} {{ $player['overs_display'] }}-{{ $player['runs_conceded'] }}-{{ $player['wickets'] }}
@else
    —
@endif
