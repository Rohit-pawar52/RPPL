@extends('layouts.admin')

@section('title', __('Notifications'))

@section('subtitle', __('Push notifications sent to subscribed visitors.'))

@section('actions')
    @can('data_cleanup.manage')
        <x-admin.button :href="route('admin.data-cleanup.index')" variant="ghost">{{ __('Data Cleanup') }}</x-admin.button>
    @endcan
    <span class="max-sm:hidden"><x-admin.button :href="route('admin.notifications.create')" variant="primary">+ {{ __('New notification') }}</x-admin.button></span>
@endsection

@section('content')
    {{-- Informational only — never a subscriber list/token export;
         see NotificationController::index()'s docblock. --}}
    <div class="crud-kpis">
        <x-crud.kpi :label="__('Active Subscribers')" :value="number_format($activeSubscriberCount)" icon="bell" tone="brand" :sub="__('Reached by every send')" />
        <x-crud.kpi :label="__('Notifications')" :value="number_format($notifications->total())" icon="megaphone" :sub="__('Saved messages')" />
    </div>

    <div class="crud-table-wrap">
        <div class="crud-table-scroll">
            <table class="crud-table crud-stack">
                <thead>
                    <tr>
                        <th>{{ __('Title') }}</th>
                        <th class="hidden md:table-cell">{{ __('Created By') }}</th>
                        <th class="hidden md:table-cell">{{ __('Created At') }}</th>
                        <th>{{ __('Sends') }}</th>
                        <th>{{ __('Last Sent') }}</th>
                        <th class="text-right">{{ __('Actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($notifications as $notification)
                        @php
                            // sends is already fully eager-loaded (see the
                            // controller) — computed here in memory, never a
                            // per-row query.
                            $latestSend = $notification->sends->sortByDesc('id')->first();
                        @endphp
                        <tr class="crud-row">
                            <td class="c-title max-w-md">
                                <a href="{{ route('admin.notifications.show', $notification) }}" class="crud-row-link">{{ $notification->title }}</a>
                                @if($notification->message)
                                    <span class="crud-meta max-w-md truncate">{{ $notification->message }}</span>
                                @endif
                            </td>
                            <td class="hidden md:table-cell">{{ $notification->creator?->name ?? '—' }}</td>
                            <td class="hidden whitespace-nowrap text-slate-500 md:table-cell">{{ display_datetime($notification->created_at, 'd M Y') }}</td>
                            <td class="c-sub">
                                <span class="crud-pill {{ $latestSend ? 'crud-pill-brand' : '' }}">
                                    @if($latestSend)
                                        {{ __('Sent :count× · :date', ['count' => $notification->sends->count(), 'date' => display_datetime($latestSend->created_at, 'd M Y')]) }}
                                    @else
                                        {{ __('Never sent') }}
                                    @endif
                                </span>
                            </td>
                            <td class="whitespace-nowrap max-md:hidden">
                                @if($latestSend)
                                    {{ display_datetime($latestSend->created_at, 'd M Y, h:i A') }}
                                @else
                                    <span class="text-slate-400">{{ __('Never sent') }}</span>
                                @endif
                            </td>
                            <td class="c-actions">
                                <x-crud.row-actions
                                    :view="route('admin.notifications.show', $notification)"
                                    :edit="route('admin.notifications.edit', $notification)"
                                    :name="$notification->title"
                                >
                                    <form
                                        method="POST"
                                        action="{{ route('admin.notifications.send', $notification) }}"
                                        class="inline"
                                        data-confirm-action
                                        data-confirm-title="{{ $latestSend ? __('Resend this notification?') : __('Send this notification?') }}"
                                        data-confirm-text="{{ __('This will send to ALL currently active notification subscribers (:count).', ['count' => $activeSubscriberCount]) }}"
                                        data-confirm-button-text="{{ $latestSend ? __('Yes, resend') : __('Yes, send') }}"
                                    >
                                        @csrf
                                        <button
                                            type="submit"
                                            title="{{ $latestSend ? __('Resend') : __('Send') }}"
                                            aria-label="{{ $latestSend ? __('Resend :name', ['name' => $notification->title]) : __('Send :name', ['name' => $notification->title]) }}"
                                            class="crud-icon-btn crud-icon-btn-brand"
                                        >
                                            <x-icon name="bell" class="h-4 w-4" />
                                        </button>
                                    </form>
                                </x-crud.row-actions>
                            </td>
                        </tr>
                    @empty
                        <x-admin.empty table colspan="6" icon="bell">
                            {{ __('No notifications found.') }}
                            <x-slot:action>
                                <x-admin.button :href="route('admin.notifications.create')" size="sm">+ {{ __('Write a notification') }}</x-admin.button>
                            </x-slot:action>
                        </x-admin.empty>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-4">
        {{ $notifications->links() }}
    </div>

    <x-crud.fab :href="route('admin.notifications.create')" :label="__('New notification')" />
@endsection
