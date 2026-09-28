@extends('layouts.admin')

@section('title', 'Videos')

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <p class="text-[13px] text-neutral-500">
            Short RPPL clips shown on the public website's homepage. Only Active videos appear there, lowest priority number first.
        </p>
        <a
            href="{{ route('admin.videos.create') }}"
            class="inline-flex shrink-0 items-center justify-center gap-1.5 whitespace-nowrap rounded-md theme-button px-3 py-1.5 text-[13px] font-medium"
        >
            + New video
        </a>
    </div>

    <div class="overflow-x-auto rounded-lg border border-neutral-200 bg-white">
        <table class="w-full min-w-[640px] text-left text-[13px]">
            <thead class="border-b border-neutral-200 bg-neutral-50 text-[11px] uppercase tracking-wide text-neutral-400">
                <tr>
                    <th class="px-4 py-2 font-medium">Thumbnail</th>
                    <th class="px-4 py-2 font-medium">Title</th>
                    <th class="px-4 py-2 font-medium">Status</th>
                    <th class="hidden px-4 py-2 text-right font-medium sm:table-cell">Priority</th>
                    <th class="hidden px-4 py-2 font-medium md:table-cell">Created</th>
                    <th class="px-4 py-2 text-right font-medium">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100">
                @forelse($videos as $video)
                    <tr class="hover:bg-neutral-50">
                        <td class="px-4 py-2">
                            <div class="flex h-10 w-16 items-center justify-center overflow-hidden rounded border border-neutral-200 bg-neutral-50 text-neutral-300">
                                @if($video->thumbnail_path)
                                    <img
                                        src="{{ Illuminate\Support\Facades\Storage::url($video->thumbnail_path) }}"
                                        alt="{{ $video->title }}"
                                        class="h-full w-full object-cover"
                                    />
                                @else
                                    <x-icon name="play" class="h-5 w-5" />
                                @endif
                            </div>
                        </td>
                        <td class="max-w-xs px-4 py-2 font-medium text-neutral-800">
                            {{ Illuminate\Support\Str::limit($video->title, 60) }}
                        </td>
                        <td class="px-4 py-2">
                            <x-status-badge :status="$video->status" />
                        </td>
                        <td class="hidden px-4 py-2 text-right text-neutral-600 sm:table-cell">
                            {{ $video->priority }}
                        </td>
                        <td class="hidden px-4 py-2 text-neutral-500 md:table-cell">
                            {{ display_datetime($video->created_at, 'd M Y') }}
                        </td>
                        <td class="px-4 py-2">
                            <div class="flex items-center justify-end gap-1">
                                <a
                                    href="{{ route('admin.videos.edit', $video) }}"
                                    title="Edit"
                                    aria-label="Edit video"
                                    class="rounded p-1.5 text-neutral-500 hover:bg-neutral-100 theme-hover-primary"
                                >
                                    <x-icon name="pencil" class="h-4 w-4" />
                                </a>
                                <form
                                    method="POST"
                                    action="{{ route('admin.videos.destroy', $video) }}"
                                    data-confirm-delete
                                    data-confirm-title="Delete this video?"
                                    data-confirm-text="This cannot be undone."
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button
                                        type="submit"
                                        title="Delete"
                                        aria-label="Delete video"
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
                        <td colspan="6" class="px-4 py-8 text-center text-neutral-400">
                            No videos yet.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">
        {{ $videos->links() }}
    </div>
@endsection
