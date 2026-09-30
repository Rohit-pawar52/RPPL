{{--
    Public status indicator. LIVE gets the solid green pill with a pulsing
    dot; everything else is a quiet tinted pill. The label is translated
    the same way <x-status-badge> does (only when a key exists), so the DB
    value itself is never changed.
        <x-public.status-pill :status="$match->match_status" />
--}}
@props(['status'])

@php
    $variant = match ($status) {
        'live' => 'live',
        'scheduled', 'upcoming' => 'scheduled',
        'completed' => 'completed',
        'toss', 'pending' => 'warn',
        'cancelled', 'abandoned', 'failed' => 'danger',
        'active', 'paid' => 'success',
        default => 'neutral',
    };

    $label = app()->getLocale() !== 'en' && \Illuminate\Support\Facades\Lang::has('public.status.'.$status)
        ? __('public.status.'.$status)
        : $status;
@endphp

<span {{ $attributes->class(['pub-pill', 'pub-pill-'.$variant]) }}>
    @if($variant === 'live')
        <span class="live-dot" aria-hidden="true"></span>
    @endif
    {{ $label }}
</span>
