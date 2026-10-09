@extends('layouts.public')

@section('title', $news->title.' · '.$branding->shortName)

@php
    // The first picture opens the story; the others form a gallery below it.
    $lead = $news->images->first();
    $gallery = $news->images->slice(1);

    // A short strip of other stories to keep people reading (same rule as the
    // list: only visible news).
    $more = \App\Models\News::query()
        ->visible()
        ->with('coverImage')
        ->where('id', '!=', $news->id)
        ->ordered()
        ->limit(3)
        ->get();
@endphp

@section('content')
    <div class="mx-auto max-w-3xl">
        <a href="{{ route('public.news.index') }}" class="pc-back">
            <span aria-hidden="true">&larr;</span> {{ __('directory.news.back') }}
        </a>

        <article>
            <header>
                <h1 class="break-words text-3xl font-bold leading-tight tracking-tight text-slate-900 sm:text-4xl">{{ $news->title }}</h1>
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-b border-line pb-4">
                    <p class="pub-meta">
                        {{ __('directory.news.published') }}: {{ display_datetime($news->published_at, 'd M Y, h:i A') }}
                    </p>
                    <button
                        type="button"
                        class="pc-share"
                        data-share
                        data-share-title="{{ $news->title }}"
                        data-copied="{{ __('ux_public_content.news.link_copied') }}"
                    >
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="18" cy="5" r="2.5" /><circle cx="6" cy="12" r="2.5" /><circle cx="18" cy="19" r="2.5" /><path d="M8.2 10.8l7.6-4.4M8.2 13.2l7.6 4.4" /></svg>
                        <span data-share-label>{{ __('ux_public_content.news.share') }}</span>
                    </button>
                </div>
            </header>

            @if($lead)
                @php $leadUrl = media_url($lead->image_path); @endphp
                <a
                    href="{{ $leadUrl }}"
                    target="_blank"
                    rel="noopener"
                    class="rppl-photo-link pub-media mt-5 block aspect-video w-full rounded-2xl shadow-card"
                    data-title="{{ $news->title }}"
                >
                    <x-media-image :path="$lead->image_path" kind="image" :alt="$news->title" class="h-full w-full object-cover" />
                </a>
            @endif

            {{-- Escaped first ({{ }}), then line breaks are kept by CSS — the
                 admin's text can never inject markup. --}}
            <div class="pc-article mt-6 whitespace-pre-line">{{ $news->content }}</div>

            @if($gallery->isNotEmpty())
                <section class="mt-8" aria-label="{{ __('ux_public_content.news.gallery') }}">
                    <h2 class="pc-h2">{{ __('ux_public_content.news.gallery') }}</h2>
                    <div class="grid gap-2 sm:gap-3 {{ $gallery->count() === 1 ? 'grid-cols-1' : 'grid-cols-2 sm:grid-cols-3' }}">
                        @foreach($gallery as $image)
                            @php $imageUrl = media_url($image->image_path); @endphp
                            <a
                                href="{{ $imageUrl }}"
                                target="_blank"
                                rel="noopener"
                                class="rppl-photo-link pub-media block aspect-[4/3] rounded-xl shadow-card focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
                                data-title="{{ $news->title }}"
                            >
                                <x-media-image :path="$image->image_path" kind="image" :alt="$news->title" loading="lazy" class="h-full w-full object-cover transition duration-300 motion-safe:hover:scale-105" />
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif
        </article>
    </div>

    @if($more->isNotEmpty())
        <section class="mt-10 border-t border-line pt-6 lg:mt-12">
            <div class="mb-3 flex items-center justify-between gap-3">
                <h2 class="text-base font-semibold tracking-tight text-slate-900">{{ __('ux_public_content.news.more') }}</h2>
                <a href="{{ route('public.news.index') }}" class="pub-link text-xs">{{ __('ux_public_content.news.all') }} &rarr;</a>
            </div>
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3 sm:gap-4">
                @foreach($more as $other)
                    <a href="{{ route('public.news.show', $other->slug) }}" class="group pc-card-link flex items-center gap-3 p-3 sm:flex-col sm:items-stretch sm:p-0">
                        <span class="pub-media block aspect-video w-28 shrink-0 overflow-hidden rounded-lg sm:w-full sm:rounded-b-none sm:rounded-t-2xl">
                            <x-media-image :path="$other->coverImage?->image_path" kind="image" alt="" loading="lazy" class="h-full w-full object-cover" />
                        </span>
                        <span class="min-w-0 sm:p-3 sm:pt-0">
                            <span class="pc-eyebrow block !text-slate-400">{{ display_datetime($other->published_at, 'd M Y') }}</span>
                            <span class="mt-0.5 line-clamp-2 block text-[13px] font-semibold leading-snug text-slate-900 group-hover:text-brand">{{ $other->title }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    @if($news->images->isNotEmpty())
        @include('public._photo-viewer')
    @endif

    <script>
        document.querySelectorAll('[data-share]').forEach(function (button) {
            button.addEventListener('click', function () {
                var label = button.querySelector('[data-share-label]');
                var original = label.textContent;
                var url = window.location.href;

                var copied = function () {
                    label.textContent = button.dataset.copied;
                    window.setTimeout(function () { label.textContent = original; }, 1800);
                };

                if (navigator.share) {
                    navigator.share({ title: button.dataset.shareTitle, url: url }).catch(function () {});
                    return;
                }

                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(url).then(copied);
                    return;
                }

                var field = document.createElement('input');
                field.value = url;
                document.body.appendChild(field);
                field.select();
                try { document.execCommand('copy'); copied(); } catch (error) {}
                document.body.removeChild(field);
            });
        });
    </script>
@endsection
