{{-- Frozen S02 rule 57 — target/runs-needed/balls-remaining/RRR, second innings only. --}}
@if($chase)
    Target {{ $chase['target'] }} &middot;
    Need {{ $chase['runs_needed'] }} from {{ $chase['balls_remaining'] }} &middot;
    RRR {{ number_format($chase['required_run_rate'], 2) }}
@endif
