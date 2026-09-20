@extends('layouts.admin')

@section('title', 'Notification Details')

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <a href="{{ route('admin.notifications.index') }}" class="text-xs text-neutral-500 hover:text-neutral-700">
            &larr; Back to notifications
        </a>
        <a
            href="{{ route('admin.notifications.edit', $notification) }}"
            class="inline-flex items-center gap-1.5 rounded-md border border-neutral-200 px-2.5 py-1.5 text-xs font-medium text-neutral-600 hover:bg-neutral-50"
        >
            <x-icon name="pencil" class="h-3.5 w-3.5" />
            Edit
        </a>
    </div>

    {{-- No Send/Resend control here — actual sending does not exist yet
         (a later phase); this page only manages the notification's
         content, so it must never imply sending is currently
         functional. --}}
    <div class="rounded-lg border border-neutral-200 bg-white p-4">
        <h2 class="text-base font-semibold text-neutral-900">{{ $notification->title }}</h2>
        <p class="mt-2 whitespace-pre-line text-[13px] text-neutral-700">{{ $notification->message }}</p>

        <dl class="mt-4 grid grid-cols-1 gap-3 text-[13px] sm:grid-cols-2">
            <div>
                <dt class="text-[11px] uppercase tracking-wide text-neutral-400">Action URL</dt>
                <dd class="text-neutral-700">{{ $notification->action_url ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-[11px] uppercase tracking-wide text-neutral-400">Created By</dt>
                <dd class="text-neutral-700">{{ $notification->creator?->name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-[11px] uppercase tracking-wide text-neutral-400">Created At</dt>
                <dd class="text-neutral-700">{{ $notification->created_at->format('d M Y, H:i') }}</dd>
            </div>
            <div>
                <dt class="text-[11px] uppercase tracking-wide text-neutral-400">Last Updated</dt>
                <dd class="text-neutral-700">{{ $notification->updated_at->format('d M Y, H:i') }}</dd>
            </div>
        </dl>
    </div>

    <div class="mt-4 rounded-lg border border-neutral-200 bg-white p-4">
        <h3 class="mb-3 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">Send History</h3>

        {{-- Read-only, always — a NotificationSend row is never edited,
             deleted, or otherwise mutated once created (see
             NotificationSend's model docblock). Editing the notification
             above never changes any row shown here. --}}
        @forelse($notification->sends as $send)
            <div class="border-b border-neutral-100 py-3 text-[13px] last:border-b-0">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="font-medium text-neutral-800">
                        {{ $send->created_at->format('d M Y, H:i') }}
                    </span>
                    <x-status-badge :status="$send->completed_at ? 'completed' : 'queued'" />
                </div>

                <p class="mt-1 text-neutral-500">
                    Sent by {{ $send->sender?->name ?? '—' }}
                    @if($send->completed_at)
                        &middot; Completed {{ $send->completed_at->format('d M Y, H:i') }}
                    @endif
                </p>

                <div class="mt-2 rounded-md bg-neutral-50 p-2.5 text-neutral-700">
                    <p class="font-medium">{{ $send->title_snapshot }}</p>
                    <p class="mt-0.5 whitespace-pre-line text-neutral-600">{{ $send->message_snapshot }}</p>
                    @if($send->action_url_snapshot)
                        <p class="mt-0.5 text-neutral-500">{{ $send->action_url_snapshot }}</p>
                    @endif
                </div>

                {{-- "Accepted" — Firebase confirming it accepted the
                     message for delivery is not the same as a person
                     having seen it. Never labelled "Delivered". --}}
                <div class="mt-2 flex flex-wrap gap-4 text-[12px] text-neutral-500">
                    <span>Attempted: <span class="font-medium text-neutral-700">{{ $send->attempted_count }}</span></span>
                    <span>Accepted: <span class="font-medium text-neutral-700">{{ $send->success_count }}</span></span>
                    <span>Failed: <span class="font-medium text-neutral-700">{{ $send->failure_count }}</span></span>
                </div>
            </div>
        @empty
            <p class="text-xs text-neutral-400">This notification has never been sent.</p>
        @endforelse
    </div>
@endsection
