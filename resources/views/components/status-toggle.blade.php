{{--
    One-click Active/Inactive control for an admin table row (Videos,
    Photos). The current status is shown as the usual badge; clicking it
    PATCHes a dedicated toggle route that flips the value server-side.
    Plain form + redirect-back — no JS.
--}}
@props(['action', 'status', 'noun' => null])

@php
    $noun ??= __('item');
@endphp

<form method="POST" action="{{ $action }}" class="inline">
    @csrf
    @method('PATCH')
    <button
        type="submit"
        class="group cursor-pointer rounded-full transition hover:brightness-95 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
        title="{{ $status === 'active' ? __('Click to deactivate') : __('Click to activate') }}"
        aria-label="{{ $status === 'active' ? __('Deactivate :noun', ['noun' => $noun]) : __('Activate :noun', ['noun' => $noun]) }}"
    >
        <x-status-badge :status="$status" />
    </button>
</form>
