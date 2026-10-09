{{-- Frozen S02 rule 59 — compact last-wicket line, derived from Fall of Wickets.
     KEEP IN SYNC with the last-wicket line in applyState() in resources/js/admin-scoring.js. --}}
@if($lastWicket)
    Last Wicket: <span class="font-semibold text-slate-800">{{ $lastWicket['player'] }} {{ $lastWicket['runs'] }} ({{ $lastWicket['balls'] }}) &mdash; {{ $lastWicket['team_score'] }}, {{ $lastWicket['over_notation'] }} ov</span>
@else
    Last Wicket: <span class="font-semibold text-slate-800">—</span>
@endif
