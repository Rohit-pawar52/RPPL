@extends('layouts.admin')

@section('title', 'Notifications')

@section('subtitle', "Push notifications sent to subscribed visitors.")

@section('actions')
    @can('data_cleanup.manage')
        <x-admin.button href="{{ route('admin.data-cleanup.index') }}" variant="ghost">Data Cleanup</x-admin.button>
    @endcan
    <x-admin.button href="{{ route('admin.notifications.create') }}" variant="primary">+ New notification</x-admin.button>
@endsection

@section('content')
    <div class="mb-4">
        <div class="max-w-xs">
            {{-- Informational only — never a subscriber list/token export;
                 see NotificationController::index()'s docblock. --}}
            <x-stat-card label="Active Subscribers" :value="$activeSubscriberCount" icon="bell" />
        </div>

    </div>

    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="w-full min-w-[720px] text-left text-[13px]">
            <thead class="border-b border-slate-200 bg-slate-50 text-[11px] uppercase tracking-wide text-slate-400">
                <tr>
                    <th class="px-4 py-2 font-medium">Title</th>
                    <th class="hidden px-4 py-2 font-medium md:table-cell">Created By</th>
                    <th class="hidden px-4 py-2 font-medium md:table-cell">Created At</th>
                    <th class="px-4 py-2 font-medium">Sends</th>
                    <th class="px-4 py-2 font-medium">Last Sent</th>
                    <th class="px-4 py-2 text-right font-medium">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($notifications as $notification)
                    @php
                        // sends is already fully eager-loaded (see the
                        // controller) — computed here in memory, never a
                        // per-row query.
                        $latestSend = $notification->sends->sortByDesc('id')->first();
                    @endphp
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-2 font-medium text-slate-800">
                            <a href="{{ route('admin.notifications.show', $notification) }}" class="hover:underline">
                                {{ $notification->title }}
                            </a>
                        </td>
                        <td class="hidden px-4 py-2 text-slate-600 md:table-cell">
                            {{ $notification->creator?->name ?? '—' }}
                        </td>
                        <td class="hidden px-4 py-2 text-slate-500 md:table-cell">
                            {{ display_datetime($notification->created_at, 'd M Y') }}
                        </td>
                        <td class="px-4 py-2 text-slate-600">
                            {{ $notification->sends->count() }}
                        </td>
                        <td class="px-4 py-2 text-slate-600">
                            @if($latestSend)
                                {{ display_datetime($latestSend->created_at, 'd M Y, h:i A') }}
                            @else
                                <span class="text-slate-400">Never sent</span>
                            @endif
                        </td>
                        <td class="px-4 py-2">
                            <div class="flex items-center justify-end gap-1">
                                <a
                                    href="{{ route('admin.notifications.show', $notification) }}"
                                    title="View"
                                    aria-label="View {{ $notification->title }}"
                                    class="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-slate-700"
                                >
                                    <x-icon name="eye" class="h-4 w-4" />
                                </a>
                                <a
                                    href="{{ route('admin.notifications.edit', $notification) }}"
                                    title="Edit"
                                    aria-label="Edit {{ $notification->title }}"
                                    class="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-green-700"
                                >
                                    <x-icon name="pencil" class="h-4 w-4" />
                                </a>
                                <form
                                    method="POST"
                                    action="{{ route('admin.notifications.send', $notification) }}"
                                    data-confirm-action
                                    data-confirm-title="{{ $latestSend ? 'Resend' : 'Send' }} this notification?"
                                    data-confirm-text="This will send to ALL currently active notification subscribers ({{ $activeSubscriberCount }})."
                                    data-confirm-button-text="Yes, {{ $latestSend ? 'resend' : 'send' }}"
                                >
                                    @csrf
                                    <button
                                        type="submit"
                                        title="{{ $latestSend ? 'Resend' : 'Send' }}"
                                        aria-label="{{ $latestSend ? 'Resend' : 'Send' }} {{ $notification->title }}"
                                        class="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-green-700"
                                    >
                                        <x-icon name="bell" class="h-4 w-4" />
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <x-admin.empty table colspan="6">No notifications found.</x-admin.empty>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">
        {{ $notifications->links() }}
    </div>
@endsection
