@extends('layouts.admin')

@section('title', __('Notification Details'))

@section('content')
    @php
        $hasSent = $notification->sends->isNotEmpty();
        $appName = app(\App\Services\Settings\SettingsService::class)->get('general.short_name');
    @endphp

    <x-crud.back :href="route('admin.notifications.index')">{{ __('Notifications') }}</x-crud.back>

    <div class="space-y-4 lg:space-y-5">
        <x-crud.profile :title="$notification->title" icon="bell" :subtitle="__('Created :time by :name', ['time' => display_datetime($notification->created_at, 'd M Y, h:i A'), 'name' => $notification->creator?->name ?? '—'])">
            <span class="crud-pill {{ $hasSent ? 'crud-pill-brand' : '' }}">{{ $hasSent ? ($notification->sends->count() === 1 ? __('Sent 1 time') : __('Sent :count times', ['count' => $notification->sends->count()])) : __('Never sent') }}</span>
            <span>{{ $activeSubscriberCount === 1 ? __('1 active subscriber') : __(':count active subscribers', ['count' => $activeSubscriberCount]) }}</span>

            <x-slot:actions>
                <x-admin.button :href="route('admin.notifications.edit', $notification)" variant="secondary" icon="pencil">{{ __('Edit') }}</x-admin.button>
                {{-- Send and Resend are the SAME backend action (see
                     NotificationController::send()) — only the label
                     changes, based on whether this notification has ever
                     been sent before. Editing the master content above is
                     always allowed, even after a send; the NEXT send simply
                     snapshots whatever the content is at that time. --}}
                <form
                    method="POST"
                    action="{{ route('admin.notifications.send', $notification) }}"
                    data-confirm-action
                    data-confirm-title="{{ $hasSent ? __('Resend this notification?') : __('Send this notification?') }}"
                    data-confirm-text="{{ __('This will send to ALL currently active notification subscribers (:count).', ['count' => $activeSubscriberCount]) }}"
                    data-confirm-button-text="{{ $hasSent ? __('Yes, resend') : __('Yes, send') }}"
                >
                    @csrf
                    <x-admin.button icon="bell">{{ $hasSent ? __('Resend Notification') : __('Send Notification') }}</x-admin.button>
                </form>
            </x-slot:actions>
        </x-crud.profile>

        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_21rem] lg:gap-5">
            <x-admin.card :title="__('Message')">
                <p class="whitespace-pre-line text-[13px] text-slate-700">{{ $notification->message ?: __('No message text.') }}</p>

                <dl class="crud-facts mt-5 border-t border-line pt-4">
                    <x-crud.fact :label="__('Action URL')" class="col-span-2">{{ $notification->action_url ?: '—' }}</x-crud.fact>
                    <x-crud.fact :label="__('Created By')">{{ $notification->creator?->name ?? '—' }}</x-crud.fact>
                    <x-crud.fact :label="__('Created At')">{{ display_datetime($notification->created_at, 'd M Y, h:i A') }}</x-crud.fact>
                    <x-crud.fact :label="__('Last Updated')">{{ display_datetime($notification->updated_at, 'd M Y, h:i A') }}</x-crud.fact>
                </dl>
            </x-admin.card>

            <x-admin.card :title="__('How it looks')">
                <div class="crud-push">
                    <div class="flex items-center gap-2 text-[11px] text-slate-500">
                        <span class="flex h-5 w-5 items-center justify-center rounded-md bg-navy-900 text-[10px] font-bold text-white">{{ \Illuminate\Support\Str::substr($appName, 0, 1) }}</span>
                        <span class="font-medium uppercase tracking-wide">{{ $appName }}</span>
                        <span>&middot; {{ __('now') }}</span>
                    </div>
                    <p class="mt-2 break-words text-[13px] font-semibold text-slate-900">{{ $notification->title }}</p>
                    @if($notification->message)
                        <p class="mt-0.5 line-clamp-3 break-words text-xs text-slate-600">{{ $notification->message }}</p>
                    @endif
                </div>
            </x-admin.card>
        </div>

        <x-admin.card :title="__('Send History')">
            <p class="mb-3 text-xs text-slate-500">
                {{ __('Accepted means Firebase accepted the message for delivery; it does not confirm that the visitor saw it.') }}
            </p>

            {{-- Read-only, always — a NotificationSend row is never edited,
                 deleted, or otherwise mutated once created (see
                 NotificationSend's model docblock). Editing the notification
                 above never changes any row shown here. --}}
            @forelse($notification->sends as $send)
                <div class="border-t border-line py-4 text-[13px] first:border-t-0 first:pt-0">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="font-semibold text-slate-900">
                            {{ display_datetime($send->created_at, 'd M Y, h:i A') }}
                        </span>
                        <x-status-badge :status="$send->completed_at ? 'completed' : 'queued'" />
                    </div>

                    <p class="mt-1 text-xs text-slate-500">
                        {{ __('Sent by :name', ['name' => $send->sender?->name ?? '—']) }}
                        @if($send->completed_at)
                            &middot; {{ __('Completed :time', ['time' => display_datetime($send->completed_at, 'd M Y, h:i A')]) }}
                        @endif
                    </p>

                    <div class="mt-2 rounded-lg bg-slate-50 p-3 text-slate-700">
                        <p class="font-medium">{{ $send->title_snapshot }}</p>
                        <p class="mt-0.5 whitespace-pre-line text-slate-600">{{ $send->message_snapshot }}</p>
                        @if($send->action_url_snapshot)
                            <p class="mt-0.5 text-xs text-slate-500">{{ $send->action_url_snapshot }}</p>
                        @endif
                    </div>

                    {{-- "Accepted" — Firebase confirming it accepted the
                         message for delivery is not the same as a person
                         having seen it. Never labelled "Delivered". --}}
                    <dl class="mt-3 grid grid-cols-3 gap-3 sm:max-w-md">
                        <div class="rounded-lg border border-line px-3 py-2">
                            <dt class="crud-fact-label">{{ __('Attempted') }}</dt>
                            <dd class="text-lg font-bold tabular-nums text-slate-900">{{ $send->attempted_count }}</dd>
                        </div>
                        <div class="rounded-lg border border-line px-3 py-2">
                            <dt class="crud-fact-label">{{ __('Accepted') }}</dt>
                            <dd class="text-lg font-bold tabular-nums text-green-600">{{ $send->success_count }}</dd>
                        </div>
                        <div class="rounded-lg border border-line px-3 py-2">
                            <dt class="crud-fact-label">{{ __('Failed') }}</dt>
                            <dd class="text-lg font-bold tabular-nums {{ $send->failure_count > 0 ? 'text-red-600' : 'text-slate-900' }}">{{ $send->failure_count }}</dd>
                        </div>
                    </dl>
                </div>
            @empty
                <x-admin.empty icon="send" class="py-6!">{{ __('This notification has never been sent.') }}</x-admin.empty>
            @endforelse
        </x-admin.card>
    </div>
@endsection
