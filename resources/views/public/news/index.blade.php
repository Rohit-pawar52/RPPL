@extends('layouts.public')

@section('title', __('directory.news.title').' · '.$branding->shortName)

@section('content')
    <x-public.page-header :title="__('directory.news.title')" />

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse($newsItems as $news)
            <article class="pub-card pub-card-link flex flex-col overflow-hidden">
                <a href="{{ route('public.news.show', $news->slug) }}" class="pub-media block aspect-video w-full" tabindex="-1" aria-hidden="true">
                    @if($news->coverImage)
                        <img
                            src="{{ Illuminate\Support\Facades\Storage::url($news->coverImage->image_path) }}"
                            alt="{{ $news->title }}"
                            loading="lazy"
                            class="h-full w-full object-cover"
                            onerror="this.style.visibility='hidden'"
                        />
                    @endif
                </a>
                <div class="flex flex-1 flex-col p-4">
                    <p class="pub-eyebrow">{{ display_datetime($news->published_at, 'd M Y') }}</p>
                    <h2 class="mt-1 text-[15px] font-semibold leading-snug tracking-tight text-slate-900">
                        <a href="{{ route('public.news.show', $news->slug) }}" class="hover:text-green-700">{{ $news->title }}</a>
                    </h2>
                    <p class="mt-1.5 line-clamp-2 break-words text-[13px] leading-relaxed text-slate-600">{{ $news->excerpt() }}</p>
                    <a href="{{ route('public.news.show', $news->slug) }}" class="pub-link mt-auto inline-flex min-h-10 items-center pt-2 text-[13px]">{{ __('directory.news.read_more') }} &rarr;</a>
                </div>
            </article>
        @empty
            <x-public.card class="col-span-full">
                <x-public.empty>{{ __('directory.news.empty') }}</x-public.empty>
            </x-public.card>
        @endforelse
    </div>

    <div class="mt-5">
        {{ $newsItems->links() }}
    </div>
@endsection
