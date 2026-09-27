{{-- Frozen S02 rules 43/54/55 — a compact ball-by-ball strip. Correctable
     chips (the latest 3 deliveries) are clickable buttons that open the
     quick-correction panel; older balls are plain, non-interactive spans. --}}
@if($over)
    @foreach($over['balls'] as $ball)
        @if($ball['is_correctable'])
            <button
                type="button"
                class="scorer-over-ball scorer-over-ball-correctable rounded px-1.5 py-0.5 text-[11px] font-semibold {{ $ball['is_wicket'] ? 'bg-red-50 text-red-600' : 'bg-neutral-100 text-neutral-700' }} ring-1 ring-inset ring-blue-300 hover:ring-blue-500"
                data-delivery-id="{{ $ball['id'] }}"
                title="Click to correct this delivery"
            >{{ $ball['label'] }}</button>
        @else
            <span class="rounded px-1.5 py-0.5 text-[11px] font-semibold {{ $ball['is_wicket'] ? 'bg-red-50 text-red-600' : 'bg-neutral-100 text-neutral-700' }}">{{ $ball['label'] }}</span>
        @endif
    @endforeach
@else
    <span class="text-xs text-neutral-400">No deliveries yet.</span>
@endif
