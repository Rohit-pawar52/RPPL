@extends('layouts.public')

@section('title', __('directory.news.title').' · '.$branding->shortName)

@section('content')
    <x-public.page-header :title="__('directory.news.title')" />

    @if($newsItems->isEmpty())
        <div class="pc-empty">
            <span class="pc-empty-icon"><x-icon name="newspaper" class="h-7 w-7" /></span>
            <p class="pc-empty-title">{{ __('directory.news.empty') }}</p>
            <p class="pc-empty-hint">{{ __('ux_public_content.news.empty_hint') }}</p>
            <a href="{{ route('public.home') }}" class="btn btn-primary mt-2">{{ __('ux_public_content.news.go_home') }}</a>
        </div>
    @else
        @php
            // The newest story gets the big card, on the first page only.
            $featured = $newsItems->currentPage() === 1 ? $newsItems->first() : null;
            $rest = $featured ? $newsItems->slice(1) : $newsItems;
        @endphp

        @if($featured)
            <article class="group pc-card-link mb-4 overflow-hidden sm:mb-6 lg:grid lg:grid-cols-5">
                <a href="{{ route('public.news.show', $featured->slug) }}" class="pub-media block aspect-video w-full lg:col-span-3 lg:aspect-auto lg:min-h-80" tabindex="-1" aria-hidden="true">
                    <x-media-image :path="$featured->coverImage?->image_path" kind="image" alt="" class="h-full w-full object-cover transition duration-500 motion-safe:group-hover:scale-105" />
                </a>
                <div class="flex flex-col justify-center p-5 sm:p-7 lg:col-span-2">
                    <p class="flex items-center gap-2">
                        <span class="pub-pill bg-brand-soft text-brand">{{ __('ux_public_content.news.latest') }}</span>
                        <span class="pc-eyebrow !text-slate-400">{{ display_datetime($featured->published_at, 'd M Y') }}</span>
                    </p>
                    <h2 class="mt-3 text-xl font-bold leading-snug tracking-tight text-slate-900 sm:text-2xl">
                        <a href="{{ route('public.news.show', $featured->slug) }}" class="after:absolute after:inset-0 hover:text-brand">{{ $featured->title }}</a>
                    </h2>
                    <p class="mt-2 line-clamp-4 break-words text-sm leading-relaxed text-slate-600">{{ $featured->excerpt() }}</p>
                    <span class="mt-4 inline-flex min-h-10 items-center gap-1 text-sm font-semibold text-link">{{ __('directory.news.read_more') }} <span aria-hidden="true" class="transition motion-safe:group-hover:translate-x-0.5">&rarr;</span></span>
                </div>
            </article>
        @endif

        @if($rest->isNotEmpty())
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 sm:gap-5 lg:grid-cols-3">
                @foreach($rest as $news)
                    <article class="group pc-card-link flex flex-col overflow-hidden">
                        <a href="{{ route('public.news.show', $news->slug) }}" class="pub-media block aspect-video w-full" tabindex="-1" aria-hidden="true">
                            <x-media-image :path="$news->coverImage?->image_path" kind="image" alt="" loading="lazy" class="h-full w-full object-cover transition duration-500 motion-safe:group-hover:scale-105" />
                        </a>
                        <div class="flex flex-1 flex-col p-4 sm:p-5">
                            <p class="pc-eyebrow !text-slate-400">{{ display_datetime($news->published_at, 'd M Y') }}</p>
                            <h2 class="mt-1.5 text-[15px] font-semibold leading-snug tracking-tight text-slate-900">
                                <a href="{{ route('public.news.show', $news->slug) }}" class="after:absolute after:inset-0 hover:text-brand">{{ $news->title }}</a>
                            </h2>
                            <p class="mt-1.5 line-clamp-2 break-words text-[13px] leading-relaxed text-slate-600">{{ $news->excerpt() }}</p>
                            <span class="mt-auto inline-flex min-h-10 items-center gap-1 pt-2 text-[13px] font-semibold text-link">{{ __('directory.news.read_more') }} <span aria-hidden="true" class="transition motion-safe:group-hover:translate-x-0.5">&rarr;</span></span>
                        </div>
                    </article>
                @endforeach
            </div>
        @endif
    @endif

    <div class="mt-6">
        {{ $newsItems->links() }}
    </div>
@endsection
