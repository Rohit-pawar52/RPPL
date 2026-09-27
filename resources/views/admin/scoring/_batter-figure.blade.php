{{-- Frozen S02 rule 56 — compact current batter figures. --}}
@if($player)
    {{ $player['name'] }} {{ $player['runs'] }} ({{ $player['balls'] }})
@else
    —
@endif
