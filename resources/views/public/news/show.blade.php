@extends('layouts.public')

@section('title', $news->title.' · '.$branding->shortName)

@section('content')
    <div class="mx-auto max-w-3xl">
        <a href="{{ route('public.news.index') }}" class="mb-2 inline-flex items-center gap-1 text-xs font-medium text-slate-500 hover:text-slate-900">
            <span aria-hidden="true">&larr;</span> {{ __('directory.news.back') }}
        </a>

        <article class="pub-card p-5 sm:p-8">
            <h1 class="break-words text-2xl font-bold leading-tight tracking-tight text-slate-900 sm:text-3xl">{{ $news->title }}</h1>
            <p class="pub-meta mt-2 border-b border-line pb-4">
                {{ __('directory.news.published') }}: {{ display_datetime($news->published_at, 'd M Y, h:i A') }}
            </p>

            {{-- Escaped first ({{ }}), then line breaks are kept by CSS — the
                 admin's text can never inject markup. --}}
            <div class="mt-5 max-w-[68ch] whitespace-pre-line break-words text-[16px] leading-[1.75] text-slate-700">{{ $news->content }}</div>

            @if($news->images->isNotEmpty())
                <div class="mt-7 grid gap-3 {{ $news->images->count() > 1 ? 'grid-cols-2' : 'grid-cols-1' }}">
                    @foreach($news->images as $image)
                        @php $imageUrl = Illuminate\Support\Facades\Storage::url($image->image_path); @endphp
                        <a
                            href="{{ $imageUrl }}"
                            target="_blank"
                            rel="noopener"
                            class="rppl-photo-link pub-media block min-h-24 rounded-lg border border-line"
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
    </div>

    @if($news->images->isNotEmpty())
        @include('public._photo-viewer')
    @endif
@endsection
