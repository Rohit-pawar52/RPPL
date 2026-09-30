@extends('layouts.public')

@section('title', __('directory.photos.title').' · '.$branding->shortName)

@section('content')
    <div class="mb-4">
        <h1 class="text-base font-semibold text-neutral-900">{{ __('directory.photos.title') }}</h1>
    </div>

    <div class="rounded-lg border border-neutral-200 bg-white p-4">
        <div class="grid grid-cols-2 gap-3 lg:grid-cols-3">
            @forelse($photos as $photo)
                @php $photoUrl = Illuminate\Support\Facades\Storage::url($photo->photo_path); @endphp
                <figure class="overflow-hidden rounded-lg border border-neutral-200 bg-white">
                    {{-- A plain link to the full image; the script below upgrades
                         it to an in-page viewer, so it still works without JS. --}}
                    <a
                        href="{{ $photoUrl }}"
                        target="_blank"
                        rel="noopener"
                        class="rppl-photo-link block aspect-video w-full bg-neutral-100"
                        data-title="{{ $photo->title }}"
                    >
                        <img
                            src="{{ $photoUrl }}"
                            alt="{{ $photo->title }}"
                            loading="lazy"
                            class="h-full w-full object-cover"
                            onerror="this.style.visibility='hidden'"
                        />
                    </a>
                    <figcaption class="p-2.5">
                        <h3 class="truncate text-[13px] font-medium text-neutral-800">{{ $photo->title }}</h3>
                        @if($photo->description)
                            <p class="mt-0.5 line-clamp-2 text-[11px] text-neutral-500">{{ $photo->description }}</p>
                        @endif
                    </figcaption>
                </figure>
            @empty
                <p class="col-span-full py-4 text-center text-xs text-neutral-400">{{ __('directory.photos.empty') }}</p>
            @endforelse
        </div>

        <div class="mt-4">
            {{ $photos->links() }}
        </div>
    </div>

    @include('public._photo-viewer')
@endsection
