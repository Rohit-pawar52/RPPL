@extends('layouts.admin')

@section('title', 'Announcements')

@section('subtitle', "Notices in the public ticker. Only Active and Enabled announcements show right now.")

@section('actions')
    <x-admin.button href="{{ route('admin.announcements.create') }}" variant="primary">+ New announcement</x-admin.button>
@endsection

@section('content')


    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="w-full min-w-[760px] text-left text-[13px]">
            <thead class="border-b border-slate-200 bg-slate-50 text-[11px] uppercase tracking-wide text-slate-400">
                <tr>
                    <th class="px-4 py-2 font-medium">Message</th>
                    <th class="px-4 py-2 font-medium">Status</th>
                    <th class="px-4 py-2 font-medium">Push</th>
                    <th class="hidden px-4 py-2 font-medium md:table-cell">Starts</th>
                    <th class="hidden px-4 py-2 font-medium md:table-cell">Ends</th>
                    <th class="hidden px-4 py-2 text-right font-medium sm:table-cell">Order</th>
                    <th class="hidden px-4 py-2 font-medium lg:table-cell">Created By</th>
                    <th class="px-4 py-2 text-right font-medium">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($announcements as $announcement)
                    <tr class="hover:bg-slate-50">
                        <td class="max-w-xs px-4 py-2 font-medium text-slate-800">
                            {{ Illuminate\Support\Str::limit($announcement->message, 60) }}
                        </td>
                        <td class="px-4 py-2">
                            <x-status-badge :status="$announcement->computedStatus()" />
                        </td>
                        <td class="px-4 py-2">
                            @php $pushStatus = $announcement->notificationStatusLabel(); @endphp
                            @if($pushStatus === 'not_scheduled')
                                <span class="text-slate-400">—</span>
                            @else
                                <x-status-badge :status="match($pushStatus) { 'scheduled' => 'scheduled', 'queued' => 'queued', default => 'completed' }" />
                                @if($pushStatus === 'scheduled' && $announcement->notification_scheduled_at)
                                    <span class="mt-0.5 block text-[11px] text-slate-400">{{ display_datetime($announcement->notification_scheduled_at, 'd M, h:i A') }}</span>
                                @endif
                            @endif
                        </td>
                        <td class="hidden px-4 py-2 text-slate-600 md:table-cell">
                            {{ $announcement->starts_at ? display_datetime($announcement->starts_at, 'd M Y, h:i A') : '—' }}
                        </td>
                        <td class="hidden px-4 py-2 text-slate-600 md:table-cell">
                            {{ $announcement->ends_at ? display_datetime($announcement->ends_at, 'd M Y, h:i A') : '—' }}
                        </td>
                        <td class="hidden px-4 py-2 text-right text-slate-600 sm:table-cell">
                            {{ $announcement->sort_order }}
                        </td>
                        <td class="hidden px-4 py-2 text-slate-600 lg:table-cell">
                            {{ $announcement->creator?->name ?? '—' }}
                        </td>
                        <td class="px-4 py-2">
                            <div class="flex items-center justify-end gap-1">
                                <a
                                    href="{{ route('admin.announcements.edit', $announcement) }}"
                                    title="Edit"
                                    aria-label="Edit announcement"
                                    class="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-green-700"
                                >
                                    <x-icon name="pencil" class="h-4 w-4" />
                                </a>
                                <form
                                    method="POST"
                                    action="{{ route('admin.announcements.destroy', $announcement) }}"
                                    data-confirm-delete
                                    data-confirm-title="Delete this announcement?"
                                    data-confirm-text="This cannot be undone."
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button
                                        type="submit"
                                        title="Delete"
                                        aria-label="Delete announcement"
                                        class="rounded p-1.5 text-slate-500 hover:bg-red-50 hover:text-red-600"
                                    >
                                        <x-icon name="trash" class="h-4 w-4" />
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <x-admin.empty table colspan="8">No announcements yet.</x-admin.empty>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">
        {{ $announcements->links() }}
    </div>
@endsection
