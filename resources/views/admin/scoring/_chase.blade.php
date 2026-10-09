{{-- Frozen S02 rule 57 — target/runs-needed/balls-remaining/RRR, second innings only.
     KEEP IN SYNC with renderChase() in resources/js/admin-scoring.js. --}}
@if($chase)
    {{ __('Target :target', ['target' => $chase['target']]) }} &middot;
    {{ __('Need :runs from :balls', ['runs' => $chase['runs_needed'], 'balls' => $chase['balls_remaining']]) }} &middot;
    {{ __('RRR :rate', ['rate' => number_format($chase['required_run_rate'], 2)]) }}
@endif
