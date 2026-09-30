{{--
    One-click Active/Inactive control for an admin table row (Videos,
    Photos). The current status is shown as the usual badge; clicking it
    PATCHes a dedicated toggle route that flips the value server-side.
    Plain form + redirect-back — no JS.
--}}
@props(['action', 'status', 'noun' => 'item'])

<form method="POST" action="{{ $action }}" class="inline">
    @csrf
    @method('PATCH')
    <button
        type="submit"
        class="cursor-pointer"
        title="{{ $status === 'active' ? 'Click to deactivate' : 'Click to activate' }}"
        aria-label="{{ $status === 'active' ? 'Deactivate' : 'Activate' }} {{ $noun }}"
    >
        <x-status-badge :status="$status" />
    </button>
</form>
