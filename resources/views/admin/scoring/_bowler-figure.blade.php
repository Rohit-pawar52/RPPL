{{-- Frozen S02 rule 56 — compact current bowler figures. KEEP IN SYNC with
     renderBowlerFigure() in resources/js/admin-scoring.js. --}}
@if($player)
    <span class="sc-name">{{ $player['name'] }}</span><span class="sc-fig">{{ $player['overs_display'] }}-{{ $player['runs_conceded'] }}-{{ $player['wickets'] }}</span>
@else
    <span class="sc-name">—</span><span class="sc-fig">&nbsp;</span>
@endif
