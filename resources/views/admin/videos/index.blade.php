@extends('layouts.admin')

@section('title', 'Videos')

@section('subtitle', number_format($videos->total()).' '.\Illuminate\Support\Str::plural('video', $videos->total()).' for the public homepage. Only Active videos appear, lowest priority first. Click a status to switch it.')

@section('actions')
    <span class="max-sm:hidden"><x-admin.button :href="route('admin.videos.create')" variant="primary">+ New video</x-admin.button></span>
@endsection

@section('content')
    @if($videos->isEmpty())
        <div class="crud-card">
            <x-admin.empty icon="play" class="py-12!">
                No videos yet.
                <x-slot:action>
                    <x-admin.button :href="route('admin.videos.create')" size="sm">+ Add the first video</x-admin.button>
                </x-slot:action>
            </x-admin.empty>
        </div>
    @else
        <div class="crud-tiles">
            @foreach($videos as $video)
                <article class="crud-tile">
                    <a href="{{ route('admin.videos.edit', $video) }}" class="crud-tile-media" aria-label="Edit {{ $video->title }}">
                        <x-media-image :path="$video->thumbnail_path" kind="image" :alt="$video->title" loading="lazy" />
                        <span class="pointer-events-none absolute inset-0 flex items-center justify-center" aria-hidden="true">
                            <span class="flex h-11 w-11 items-center justify-center rounded-full bg-navy-900/70 text-white shadow-raised"><x-icon name="play" class="h-6 w-6" /></span>
                        </span>
                    </a>
                    <div class="crud-tile-body">
                        <div class="min-w-0">
                            <h3 class="truncate text-[13px] font-semibold text-slate-900" title="{{ $video->title }}">{{ Illuminate\Support\Str::limit($video->title, 60) }}</h3>
                            <p class="mt-0.5 text-xs text-slate-500">{{ display_datetime($video->created_at, 'd M Y') }} &middot; priority {{ $video->priority }}</p>
                        </div>
                        <div class="mt-auto flex items-center justify-between gap-1">
                            <x-status-toggle :action="route('admin.videos.toggle-status', $video)" :status="$video->status" noun="video" />
                            <x-crud.row-actions
                                :edit="route('admin.videos.edit', $video)"
                                :delete="route('admin.videos.destroy', $video)"
                                name="video"
                                confirm-title="Delete this video?"
                            />
                        </div>
                    </div>
                </article>
            @endforeach
        </div>

        <div class="mt-4">
            {{ $videos->links() }}
        </div>
    @endif

    <x-crud.fab :href="route('admin.videos.create')" label="New video" />
@endsection
