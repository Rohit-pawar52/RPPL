{{-- Frozen S02 rules 43/54/55 — a compact ball-by-ball strip. Correctable
     chips (the latest 3 deliveries) are clickable buttons that open the
     quick-correction panel; older balls are plain, non-interactive spans.
     KEEP IN SYNC with renderOverStrip() in resources/js/admin-scoring.js. --}}
@if($over)
    @foreach($over['balls'] as $ball)
        @php
            $label = (string) $ball['label'];
            $kind = match (true) {
                (bool) $ball['is_wicket'] => 'wicket',
                $label === '6' => 'six',
                $label === '4' => 'four',
                $label === '0' => 'dot',
                ! ctype_digit($label) => 'extra',
                default => 'run',
            };
        @endphp
        @if($ball['is_correctable'])
            <button
                type="button"
                class="scorer-over-ball scorer-over-ball-correctable sc-ball sc-ball-correctable sc-ball-{{ $kind }}"
                data-delivery-id="{{ $ball['id'] }}"
                title="Tap to correct this delivery"
            >{{ $label }}</button>
        @else
            <span class="sc-ball sc-ball-{{ $kind }}">{{ $label }}</span>
        @endif
    @endforeach
@else
    <span class="text-xs text-white/60">No deliveries yet.</span>
@endif
