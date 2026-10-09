@extends('layouts.public')

@section('title', __('directory.photos.title').' · '.$branding->shortName)

@section('content')
    <x-public.page-header :title="__('directory.photos.title')">
        @if($photos->total() > 0)
            <span class="pc-count">{{ trans_choice('ux_public_content.count.photos', $photos->total(), ['count' => $photos->total()]) }}</span>
        @endif
    </x-public.page-header>

    @if($photos->isEmpty())
        <div class="pc-empty">
            <span class="pc-empty-icon"><x-icon name="camera" class="h-7 w-7" /></span>
            <p class="pc-empty-title">{{ __('directory.photos.empty') }}</p>
            <p class="pc-empty-hint">{{ __('ux_public_content.photos.empty_hint') }}</p>
            <a href="{{ route('public.home') }}" class="btn btn-primary mt-2">{{ __('ux_public_content.news.go_home') }}</a>
        </div>
    @else
        {{-- Pictures keep their own shape and flow down the columns. --}}
        <div class="pc-masonry">
            @foreach($photos as $photo)
                @php $photoUrl = media_url($photo->photo_path); @endphp
                <figure class="pc-masonry-item">
                    {{-- A plain link to the full image; the script in the viewer
                         upgrades it to an in-page dialog, so it still works without JS. --}}
                    <a
                        href="{{ $photoUrl }}"
                        target="_blank"
                        rel="noopener"
                        class="rppl-photo-link pc-masonry-link"
                        data-title="{{ $photo->title }}"
                    >
                        <x-media-image :path="$photo->photo_path" kind="image" :alt="$photo->title" loading="lazy" class="block h-auto min-h-40 w-full object-cover" />
                        <figcaption class="pc-masonry-cap">
                            <h3 class="truncate text-[13px] font-semibold">{{ $photo->title }}</h3>
                            @if($photo->description)
                                <p class="mt-0.5 line-clamp-2 break-words text-xs text-white/80">{{ $photo->description }}</p>
                            @endif
                        </figcaption>
                    </a>
                </figure>
            @endforeach
        </div>
    @endif

    <div class="mt-6">
        {{ $photos->links() }}
    </div>

    @include('public._photo-viewer')
@endsection
