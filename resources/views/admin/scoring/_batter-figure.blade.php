{{-- Frozen S02 rule 56 — compact current batter figures. KEEP IN SYNC with
     renderBatterFigure() in resources/js/admin-scoring.js. --}}
@if($player)
    <span class="sc-name">{{ $player['name'] }}</span><span class="sc-fig">{{ $player['runs'] }}<i>({{ $player['balls'] }})</i></span>
@else
    <span class="sc-name">—</span><span class="sc-fig">&nbsp;</span>
@endif
