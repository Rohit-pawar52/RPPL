@extends('layouts.admin')

@section('title', 'Notifications')

@section('content')
    <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="max-w-xs">
            {{-- Informational only — never a subscriber list/token export;
                 see NotificationController::index()'s docblock. --}}
            <x-stat-card label="Active Subscribers" :value="$activeSubscriberCount" icon="bell" />
        </div>

        <div class="flex items-center gap-3">
            <a href="{{ route('admin.data-cleanup.index') }}" class="text-xs text-neutral-500 hover:text-neutral-700">
                Clean up old data? Go to Data Cleanup &rarr;
            </a>
            <a
                href="{{ route('admin.notifications.create') }}"
                class="inline-flex items-center justify-center gap-1.5 whitespace-nowrap rounded-md theme-button px-3 py-1.5 text-[13px] font-medium"
            >
                + New notification
            </a>
        </div>
    </div>

    <div class="overflow-x-auto rounded-lg border border-neutral-200 bg-white">
        <table class="w-full min-w-[720px] text-left text-[13px]">
            <thead class="border-b border-neutral-200 bg-neutral-50 text-[11px] uppercase tracking-wide text-neutral-400">
                <tr>
                    <th class="px-4 py-2 font-medium">Title</th>
                    <th class="hidden px-4 py-2 font-medium md:table-cell">Created By</th>
                    <th class="hidden px-4 py-2 font-medium md:table-cell">Created At</th>
                    <th class="px-4 py-2 font-medium">Sends</th>
                    <th class="px-4 py-2 font-medium">Last Sent</th>
                    <th class="px-4 py-2 text-right font-medium">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100">
                @forelse($notifications as $notification)
                    @php
                        // sends is already fully eager-loaded (see the
                        // controller) — computed here in memory, never a
                        // per-row query.
                        $latestSend = $notification->sends->sortByDesc('id')->first();
                    @endphp
                    <tr class="hover:bg-neutral-50">
                        <td class="px-4 py-2 font-medium text-neutral-800">
                            <a href="{{ route('admin.notifications.show', $notification) }}" class="hover:underline">
                                {{ $notification->title }}
                            </a>
                        </td>
                        <td class="hidden px-4 py-2 text-neutral-600 md:table-cell">
                            {{ $notification->creator?->name ?? '—' }}
                        </td>
                        <td class="hidden px-4 py-2 text-neutral-500 md:table-cell">
                            {{ $notification->created_at->format('d M Y') }}
                        </td>
                        <td class="px-4 py-2 text-neutral-600">
                            {{ $notification->sends->count() }}
                        </td>
                        <td class="px-4 py-2 text-neutral-600">
                            @if($latestSend)
                                {{ $latestSend->created_at->format('d M Y, H:i') }}
                            @else
                                <span class="text-neutral-400">Never sent</span>
                            @endif
                        </td>
                        <td class="px-4 py-2">
                            <div class="flex items-center justify-end gap-1">
                                <a
                                    href="{{ route('admin.notifications.show', $notification) }}"
                                    title="View"
                                    aria-label="View {{ $notification->title }}"
                                    class="rounded p-1.5 text-neutral-500 hover:bg-neutral-100 hover:text-neutral-700"
                                >
                                    <x-icon name="eye" class="h-4 w-4" />
                                </a>
                                <a
                                    href="{{ route('admin.notifications.edit', $notification) }}"
                                    title="Edit"
                                    aria-label="Edit {{ $notification->title }}"
                                    class="rounded p-1.5 text-neutral-500 hover:bg-neutral-100 theme-hover-primary"
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
                                        class="rounded p-1.5 text-neutral-500 hover:bg-neutral-100 theme-hover-primary"
                                    >
                                        <x-icon name="bell" class="h-4 w-4" />
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-8 text-center text-neutral-400">
                            No notifications found.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">
        {{ $notifications->links() }}
    </div>
@endsection
