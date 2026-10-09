@extends('layouts.admin')

@section('title', 'Photos')

@section('subtitle', number_format($photos->total()).' '.\Illuminate\Support\Str::plural('photo', $photos->total()).' in the gallery. Only Active photos appear publicly, lowest priority first. Click a status to switch it.')

@section('actions')
    <span class="max-sm:hidden"><x-admin.button :href="route('admin.photos.create')" variant="primary">+ New photo</x-admin.button></span>
@endsection

@section('content')
    @if($photos->isEmpty())
        <div class="crud-card">
            <x-admin.empty icon="camera" class="py-12!">
                No photos yet.
                <x-slot:action>
                    <x-admin.button :href="route('admin.photos.create')" size="sm">+ Add the first photo</x-admin.button>
                </x-slot:action>
            </x-admin.empty>
        </div>
    @else
        <div class="crud-tiles">
            @foreach($photos as $photo)
                <article class="crud-tile">
                    <a href="{{ route('admin.photos.edit', $photo) }}" class="crud-tile-media" aria-label="Edit {{ $photo->title }}">
                        <x-media-image :path="$photo->photo_path" kind="image" :alt="$photo->title" loading="lazy" />
                    </a>
                    <div class="crud-tile-body">
                        <div class="min-w-0">
                            <h3 class="truncate text-[13px] font-semibold text-slate-900" title="{{ $photo->title }}">{{ Illuminate\Support\Str::limit($photo->title, 60) }}</h3>
                            <p class="mt-0.5 text-xs text-slate-500">{{ display_datetime($photo->created_at, 'd M Y') }} &middot; priority {{ $photo->priority }}</p>
                        </div>
                        <div class="mt-auto flex items-center justify-between gap-1">
                            <x-status-toggle :action="route('admin.photos.toggle-status', $photo)" :status="$photo->status" noun="photo" />
                            <x-crud.row-actions
                                :edit="route('admin.photos.edit', $photo)"
                                :delete="route('admin.photos.destroy', $photo)"
                                name="photo"
                                confirm-title="Delete this photo?"
                            />
                        </div>
                    </div>
                </article>
            @endforeach
        </div>

        <div class="mt-4">
            {{ $photos->links() }}
        </div>
    @endif

    <x-crud.fab :href="route('admin.photos.create')" label="New photo" />
@endsection
