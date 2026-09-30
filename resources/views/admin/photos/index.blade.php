@extends('layouts.admin')

@section('title', 'Photos')

@section('subtitle', "Gallery photos. Only Active photos appear publicly, lowest priority first. Click a status to switch it.")

@section('actions')
    <x-admin.button href="{{ route('admin.photos.create') }}" variant="primary">+ New photo</x-admin.button>
@endsection

@section('content')


    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="w-full min-w-[640px] text-left text-[13px]">
            <thead class="border-b border-slate-200 bg-slate-50 text-[11px] uppercase tracking-wide text-slate-400">
                <tr>
                    <th class="px-4 py-2 font-medium">Photo</th>
                    <th class="px-4 py-2 font-medium">Title</th>
                    <th class="px-4 py-2 font-medium">Status</th>
                    <th class="hidden px-4 py-2 text-right font-medium sm:table-cell">Priority</th>
                    <th class="hidden px-4 py-2 font-medium md:table-cell">Created</th>
                    <th class="px-4 py-2 text-right font-medium">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($photos as $photo)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-2">
                            <div class="flex h-10 w-16 items-center justify-center overflow-hidden rounded border border-slate-200 bg-slate-50 text-slate-300">
                                <img
                                    src="{{ Illuminate\Support\Facades\Storage::url($photo->photo_path) }}"
                                    alt="{{ $photo->title }}"
                                    loading="lazy"
                                    class="h-full w-full object-cover"
                                />
                            </div>
                        </td>
                        <td class="max-w-xs px-4 py-2 font-medium text-slate-800">
                            {{ Illuminate\Support\Str::limit($photo->title, 60) }}
                        </td>
                        <td class="px-4 py-2">
                            <x-status-toggle :action="route('admin.photos.toggle-status', $photo)" :status="$photo->status" noun="photo" />
                        </td>
                        <td class="hidden px-4 py-2 text-right text-slate-600 sm:table-cell">
                            {{ $photo->priority }}
                        </td>
                        <td class="hidden px-4 py-2 text-slate-500 md:table-cell">
                            {{ display_datetime($photo->created_at, 'd M Y') }}
                        </td>
                        <td class="px-4 py-2">
                            <div class="flex items-center justify-end gap-1">
                                <a
                                    href="{{ route('admin.photos.edit', $photo) }}"
                                    title="Edit"
                                    aria-label="Edit photo"
                                    class="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-green-700"
                                >
                                    <x-icon name="pencil" class="h-4 w-4" />
                                </a>
                                <form
                                    method="POST"
                                    action="{{ route('admin.photos.destroy', $photo) }}"
                                    data-confirm-delete
                                    data-confirm-title="Delete this photo?"
                                    data-confirm-text="This cannot be undone."
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button
                                        type="submit"
                                        title="Delete"
                                        aria-label="Delete photo"
                                        class="rounded p-1.5 text-slate-500 hover:bg-red-50 hover:text-red-600"
                                    >
                                        <x-icon name="trash" class="h-4 w-4" />
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <x-admin.empty table colspan="6">No photos yet.</x-admin.empty>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">
        {{ $photos->links() }}
    </div>
@endsection
