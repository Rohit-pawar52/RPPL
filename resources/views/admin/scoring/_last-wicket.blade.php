{{-- Frozen S02 rule 59 — compact last-wicket line, derived from Fall of Wickets.
     KEEP IN SYNC with the last-wicket line in applyState() in resources/js/admin-scoring.js. --}}
@if($lastWicket)
    {{ __('Last Wicket:') }} <span class="font-semibold text-slate-800">{{ __(':player :runs (:balls) — :score, :over ov', ['player' => $lastWicket['player'], 'runs' => $lastWicket['runs'], 'balls' => $lastWicket['balls'], 'score' => $lastWicket['team_score'], 'over' => $lastWicket['over_notation']]) }}</span>
@else
    {{ __('Last Wicket:') }} <span class="font-semibold text-slate-800">—</span>
@endif
