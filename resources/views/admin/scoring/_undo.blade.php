{{-- Universal Undo (frozen rule 42) — reverses whichever reversible action is
     chronologically latest, not only the latest Delivery. admin-scoring.js
     intercepts this form's submit for the AJAX path; the plain POST below is
     the fallback if JS is unavailable.
     Expects: $match, $innings, $liveState, $inPad (true = a key of the run pad). --}}
<form
    id="scorer-undo-form"
    method="POST"
    action="{{ route('admin.matches.innings.deliveries.undo-latest', [$match, $innings]) }}"
    @class(['contents' => $inPad])
    onsubmit="event.preventDefault(); window.confirmAction({title: 'Undo the last action?', confirmButtonText: 'Yes, undo'}).then((result) => { if (result.isConfirmed) { this.submit(); } });"
>
    @csrf
    @method('DELETE')
    <button
        id="scorer-undo-button"
        type="submit"
        aria-label="Undo Last Action"
        title="Undo Last Action"
        @class(['sc-key sc-key-undo' => $inPad, 'btn btn-secondary btn-sm' => ! $inPad])
        @disabled(! $liveState['can_undo'])
    >
        <x-icon name="undo" class="h-5 w-5" />
        <span>Undo</span>
    </button>
</form>
