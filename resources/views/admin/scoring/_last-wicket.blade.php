{{-- Frozen S02 rule 59 — compact last-wicket line, derived from Fall of Wickets. --}}
@if($lastWicket)
    Last Wicket: <span class="font-medium text-neutral-800">{{ $lastWicket['player'] }} {{ $lastWicket['runs'] }} ({{ $lastWicket['balls'] }}) &mdash; {{ $lastWicket['team_score'] }}, {{ $lastWicket['over_notation'] }} ov</span>
@else
    Last Wicket: <span class="font-medium text-neutral-800">—</span>
@endif
