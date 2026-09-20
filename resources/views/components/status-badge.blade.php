@props(['status'])

@php
    $styles = [
        'upcoming' => 'bg-amber-50 text-amber-700 ring-amber-200',
        'active' => 'bg-green-50 text-green-700 ring-green-200',
        'completed' => 'bg-neutral-100 text-neutral-600 ring-neutral-200',
        // Payment statuses (player_registrations.payment_status)
        'pending' => 'bg-amber-50 text-amber-700 ring-amber-200',
        'paid' => 'bg-green-50 text-green-700 ring-green-200',
        'failed' => 'bg-red-50 text-red-700 ring-red-200',
        'refunded' => 'bg-blue-50 text-blue-700 ring-blue-200',
        // Match statuses (matches.match_status) — 'completed' above is
        // reused as-is
        'scheduled' => 'bg-blue-50 text-blue-700 ring-blue-200',
        'toss' => 'bg-amber-50 text-amber-700 ring-amber-200',
        'live' => 'bg-green-50 text-green-700 ring-green-200',
        'abandoned' => 'bg-red-50 text-red-700 ring-red-200',
        'cancelled' => 'bg-red-50 text-red-700 ring-red-200',
        // Edition transaction types (edition_transactions.type)
        'income' => 'bg-green-50 text-green-700 ring-green-200',
        'expense' => 'bg-red-50 text-red-700 ring-red-200',
        // Notification send presentation (notification_sends.completed_at
        // null/populated) — never "delivered", just whether the queued
        // job has finished; 'completed' above is reused as-is.
        'queued' => 'bg-amber-50 text-amber-700 ring-amber-200',
    ];
    $style = $styles[$status] ?? 'bg-neutral-100 text-neutral-600 ring-neutral-200';
@endphp

<span class="inline-flex items-center whitespace-nowrap rounded-full px-2 py-0.5 text-[11px] font-medium capitalize ring-1 ring-inset {{ $style }}">
    {{ $status }}
</span>
