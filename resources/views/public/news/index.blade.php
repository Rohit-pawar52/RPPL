@extends('layouts.public')

@section('title', __('directory.news.title').' · '.$branding->shortName)

@section('content')
    <div class="mb-4">
        <h1 class="text-base font-semibold text-neutral-900">{{ __('directory.news.title') }}</h1>
    </div>

    <div class="rounded-lg border border-neutral-200 bg-white p-4">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @forelse($newsItems as $news)
                <article class="overflow-hidden rounded-lg border border-neutral-200 bg-white">
                    @if($news->coverImage)
                        <a href="{{ route('public.news.show', $news->slug) }}" class="block aspect-video w-full bg-neutral-100">
                            <img
                                src="{{ Illuminate\Support\Facades\Storage::url($news->coverImage->image_path) }}"
                                alt="{{ $news->title }}"
                                loading="lazy"
                                class="h-full w-full object-cover"
                                onerror="this.style.visibility='hidden'"
                            />
                        </a>
                    @endif
                    <div class="p-3">
                        <p class="text-[11px] text-neutral-400">{{ display_datetime($news->published_at, 'd M Y') }}</p>
                        <h2 class="mt-0.5 text-[14px] font-semibold leading-snug text-neutral-900">
                            <a href="{{ route('public.news.show', $news->slug) }}" class="hover:underline">{{ $news->title }}</a>
                        </h2>
                        <p class="mt-1 line-clamp-2 break-words text-[12px] text-neutral-600">{{ $news->excerpt() }}</p>
                        <a href="{{ route('public.news.show', $news->slug) }}" class="mt-2 inline-block text-[12px] font-medium theme-link hover:underline">{{ __('directory.news.read_more') }}</a>
                    </div>
                </article>
            @empty
                <p class="col-span-full py-4 text-center text-xs text-neutral-400">{{ __('directory.news.empty') }}</p>
            @endforelse
        </div>

        <div class="mt-4">
            {{ $newsItems->links() }}
        </div>
    </div>
@endsection
