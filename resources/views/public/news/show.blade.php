@extends('layouts.public')

@section('title', $news->title.' · '.$branding->shortName)

@section('content')
    <div class="mb-3">
        <a href="{{ route('public.news.index') }}" class="text-xs text-neutral-500 hover:text-neutral-700">&larr; {{ __('directory.news.back') }}</a>
    </div>

    <article class="mx-auto max-w-3xl rounded-lg border border-neutral-200 bg-white p-4">
        <h1 class="break-words text-xl font-semibold leading-snug text-neutral-900">{{ $news->title }}</h1>
        <p class="mt-1 text-[12px] text-neutral-400">
            {{ __('directory.news.published') }}: {{ display_datetime($news->published_at, 'd M Y, h:i A') }}
        </p>

        {{-- Escaped first ({{ }}), then line breaks are kept by CSS — the
             admin's text can never inject markup. --}}
        <div class="mt-4 whitespace-pre-line break-words text-[14px] leading-relaxed text-neutral-700">{{ $news->content }}</div>

        @if($news->images->isNotEmpty())
            <div class="mt-5 grid gap-3 {{ $news->images->count() > 1 ? 'grid-cols-2' : 'grid-cols-1' }}">
                @foreach($news->images as $image)
                    @php $imageUrl = Illuminate\Support\Facades\Storage::url($image->image_path); @endphp
                    <a
                        href="{{ $imageUrl }}"
                        target="_blank"
                        rel="noopener"
                        class="rppl-photo-link block overflow-hidden rounded-lg border border-neutral-200 bg-neutral-100"
                        data-title="{{ $news->title }}"
                    >
                        <img
                            src="{{ $imageUrl }}"
                            alt="{{ $news->title }}"
                            loading="lazy"
                            class="w-full object-cover"
                            onerror="this.style.visibility='hidden'"
                        />
                    </a>
                @endforeach
            </div>
        @endif
    </article>

    @if($news->images->isNotEmpty())
        @include('public._photo-viewer')
    @endif
@endsection
