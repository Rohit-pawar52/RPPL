@extends('layouts.admin')

@section('title', 'News')

@section('subtitle', "News posts for the public News page. Active, already-published posts appear, lowest priority first.")

@section('actions')
    <x-admin.button href="{{ route('admin.news.create') }}" variant="primary">+ New news</x-admin.button>
@endsection

@section('content')


    <div class="overflow-x-auto rounded-lg border border-slate-200 bg-white">
        <table class="w-full min-w-[640px] text-left text-[13px]">
            <thead class="border-b border-slate-200 bg-slate-50 text-[11px] uppercase tracking-wide text-slate-400">
                <tr>
                    <th class="px-4 py-2 font-medium">Cover</th>
                    <th class="px-4 py-2 font-medium">Heading</th>
                    <th class="hidden px-4 py-2 font-medium md:table-cell">Published</th>
                    <th class="hidden px-4 py-2 text-right font-medium sm:table-cell">Images</th>
                    <th class="px-4 py-2 font-medium">Status</th>
                    <th class="hidden px-4 py-2 text-right font-medium sm:table-cell">Priority</th>
                    <th class="px-4 py-2 text-right font-medium">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($newsItems as $news)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-2">
                            <div class="flex h-10 w-16 items-center justify-center overflow-hidden rounded border border-slate-200 bg-slate-50 text-slate-300">
                                @if($news->coverImage)
                                    <img
                                        src="{{ Illuminate\Support\Facades\Storage::url($news->coverImage->image_path) }}"
                                        alt="{{ $news->title }}"
                                        loading="lazy"
                                        class="h-full w-full object-cover"
                                    />
                                @else
                                    <x-icon name="clipboard" class="h-5 w-5" />
                                @endif
                            </div>
                        </td>
                        <td class="max-w-xs px-4 py-2 font-medium text-slate-800">
                            {{ Illuminate\Support\Str::limit($news->title, 60) }}
                        </td>
                        <td class="hidden px-4 py-2 text-slate-500 md:table-cell">
                            {{ display_datetime($news->published_at, 'd M Y, h:i A') }}
                            @if($news->status === 'active' && $news->published_at?->isFuture())
                                <span class="block text-[11px] text-amber-600">Scheduled</span>
                            @endif
                        </td>
                        <td class="hidden px-4 py-2 text-right text-slate-600 sm:table-cell">
                            {{ $news->images_count }}
                        </td>
                        <td class="px-4 py-2">
                            <x-status-toggle :action="route('admin.news.toggle-status', $news)" :status="$news->status" noun="news" />
                        </td>
                        <td class="hidden px-4 py-2 text-right text-slate-600 sm:table-cell">
                            {{ $news->priority }}
                        </td>
                        <td class="px-4 py-2">
                            <div class="flex items-center justify-end gap-1">
                                <a
                                    href="{{ route('admin.news.edit', $news) }}"
                                    title="Edit"
                                    aria-label="Edit news"
                                    class="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-green-700"
                                >
                                    <x-icon name="pencil" class="h-4 w-4" />
                                </a>
                                <form
                                    method="POST"
                                    action="{{ route('admin.news.destroy', $news) }}"
                                    data-confirm-delete
                                    data-confirm-title="Delete this news post?"
                                    data-confirm-text="Its images will be deleted too. This cannot be undone."
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button
                                        type="submit"
                                        title="Delete"
                                        aria-label="Delete news"
                                        class="rounded p-1.5 text-slate-500 hover:bg-red-50 hover:text-red-600"
                                    >
                                        <x-icon name="trash" class="h-4 w-4" />
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <x-admin.empty table colspan="7">No news yet.</x-admin.empty>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-3">
        {{ $newsItems->links() }}
    </div>
@endsection
