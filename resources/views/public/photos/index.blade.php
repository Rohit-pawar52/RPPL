@extends('layouts.public')

@section('title', __('directory.photos.title').' · '.$branding->shortName)

@section('content')
    <x-public.page-header :title="__('directory.photos.title')" />

    <div class="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-3">
        @forelse($photos as $photo)
            @php $photoUrl = media_url($photo->photo_path); @endphp
            <figure class="pub-card pub-card-link overflow-hidden">
                {{-- A plain link to the full image; the script in the viewer
                     upgrades it to an in-page dialog, so it still works without JS. --}}
                <a
                    href="{{ $photoUrl }}"
                    target="_blank"
                    rel="noopener"
                    class="rppl-photo-link pub-media block aspect-[4/3] w-full"
                    data-title="{{ $photo->title }}"
                >
                    <img
                        src="{{ $photoUrl }}"
                        alt="{{ $photo->title }}"
                        loading="lazy"
                        class="h-full w-full object-cover"
                    />
                </a>
                <figcaption class="p-3">
                    <h3 class="truncate text-[13px] font-semibold text-slate-900">{{ $photo->title }}</h3>
                    @if($photo->description)
                        <p class="mt-0.5 line-clamp-2 break-words text-xs text-slate-500">{{ $photo->description }}</p>
                    @endif
                </figcaption>
            </figure>
        @empty
            <x-public.card class="col-span-full">
                <x-public.empty>{{ __('directory.photos.empty') }}</x-public.empty>
            </x-public.card>
        @endforelse
    </div>

    <div class="mt-5">
        {{ $photos->links() }}
    </div>

    @include('public._photo-viewer')
@endsection
