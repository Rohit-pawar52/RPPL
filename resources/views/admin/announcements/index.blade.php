@extends('layouts.admin')

@section('title', 'Announcements')

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <p class="text-[13px] text-neutral-500">
            Notices shown in a looping ticker at the top of the public website. Only announcements that are Active and Enabled show there right now.
        </p>
        <a
            href="{{ route('admin.announcements.create') }}"
            class="inline-flex shrink-0 items-center justify-center gap-1.5 whitespace-nowrap rounded-md theme-button px-3 py-1.5 text-[13px] font-medium"
        >
            + New announcement
        </a>
    </div>

    <div class="overflow-x-auto rounded-lg border border-neutral-200 bg-white">
        <table class="w-full min-w-[760px] text-left text-[13px]">
            <thead class="border-b border-neutral-200 bg-neutral-50 text-[11px] uppercase tracking-wide text-neutral-400">
                <tr>
                    <th class="px-4 py-2 font-medium">Message</th>
                    <th class="px-4 py-2 font-medium">Status</th>
                    <th class="hidden px-4 py-2 font-medium md:table-cell">Starts</th>
                    <th class="hidden px-4 py-2 font-medium md:table-cell">Ends</th>
                    <th class="hidden px-4 py-2 text-right font-medium sm:table-cell">Order</th>
                    <th class="hidden px-4 py-2 font-medium lg:table-cell">Created By</th>
                    <th class="px-4 py-2 text-right font-medium">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100">
                @forelse($announcements as $announcement)
                    <tr class="hover:bg-neutral-50">
                        <td class="max-w-xs px-4 py-2 font-medium text-neutral-800">
                            {{ Illuminate\Support\Str::limit($announcement->message, 60) }}
                        </td>
                        <td class="px-4 py-2">
                            <x-status-badge :status="$announcement->computedStatus()" />
                        </td>
                        <td class="hidden px-4 py-2 text-neutral-600 md:table-cell">
                            {{ $announcement->starts_at ? display_datetime($announcement->starts_at, 'd M Y, h:i A') : '—' }}
                        </td>
                        <td class="hidden px-4 py-2 text-neutral-600 md:table-cell">
                            {{ $announcement->ends_at ? display_datetime($announcement->ends_at, 'd M Y, h:i A') : '—' }}
                        </td>
                        <td class="hidden px-4 py-2 text-right text-neutral-600 sm:table-cell">
                            {{ $announcement->sort_order }}
                        </td>
                        <td class="hidden px-4 py-2 text-neutral-600 lg:table-cell">
                            {{ $announcement->creator?->name ?? '—' }}
                        </td>
                        <td class="px-4 py-2">
                            <div class="flex items-center justify-end gap-1">
                                <a
                                    href="{{ route('admin.announcements.edit', $announcement) }}"
                                    title="Edit"
                                    aria-label="Edit announcement"
                                    class="rounded p-1.5 text-neutral-500 hover:bg-neutral-100 theme-hover-primary"
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
                                        class="rounded p-1.5 text-neutral-500 hover:bg-red-50 hover:text-red-600"
                                    >
                                        <x-icon name="trash" class="h-4 w-4" />
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="px-4 py-8 text-center text-neutral-400">
                            No announcements yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">
        {{ $announcements->links() }}
    </div>
@endsection
