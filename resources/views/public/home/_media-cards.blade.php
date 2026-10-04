{{--
    Videos / News / Photos: three cards side by side (stacked on phones),
    each listing its latest few items one under another. Every item and the
    "View all" link lead to that section's own page. A section with nothing
    published is left out, and the row closes up; with none at all the whole
    block disappears.
    Expects $latestVideos, $latestNews, $latestPhotos.
--}}
@php
    $storage = fn (?string $path) => media_url($path);

    $sections = collect([
        [
            'key' => 'videos',
            'title' => __('home.media.videos'),
            'icon' => 'play',
            'all' => route('public.videos.index'),
            'items' => $latestVideos->map(fn ($video) => [
                'href' => route('public.videos.index'),
                'thumb' => $storage($video->thumbnail_path),
                'title' => $video->title,
                'date' => $video->created_at,
                'play' => true,
            ]),
        ],
        [
            'key' => 'news',
            'title' => __('home.media.news'),
            'icon' => 'newspaper',
            'all' => route('public.news.index'),
            'items' => $latestNews->map(fn ($news) => [
                'href' => route('public.news.show', $news->slug),
                'thumb' => $storage($news->coverImage?->image_path),
                'title' => $news->title,
                'date' => $news->published_at,
                'play' => false,
            ]),
        ],
        [
            'key' => 'photos',
            'title' => __('home.media.photos'),
            'icon' => 'camera',
            'all' => route('public.photos.index'),
            'items' => $latestPhotos->map(fn ($photo) => [
                'href' => route('public.photos.index'),
                'thumb' => $storage($photo->photo_path),
                'title' => $photo->title,
                'date' => $photo->created_at,
                'play' => false,
            ]),
        ],
    ])->filter(fn ($section) => $section['items']->isNotEmpty())->values();

    $columns = [1 => 'md:grid-cols-1', 2 => 'md:grid-cols-2', 3 => 'md:grid-cols-3'][$sections->count()] ?? 'md:grid-cols-3';
@endphp

@if($sections->isNotEmpty())
    <section class="mt-4 grid grid-cols-1 gap-4 {{ $columns }}">
        @foreach($sections as $section)
            <div class="pub-card overflow-hidden" data-home-section="{{ $section['key'] }}">
                <header class="pub-card-head">
                    <a href="{{ $section['all'] }}" class="pub-card-title inline-flex items-center gap-1.5 hover:text-green-700">
                        <x-icon :name="$section['icon']" class="h-4 w-4 text-green-600" />
                        {{ $section['title'] }}
                    </a>
                    <a href="{{ $section['all'] }}" class="pub-link text-xs">{{ __('home.media.view_all') }} &rarr;</a>
                </header>

                <ul class="divide-y divide-line">
                    @foreach($section['items'] as $item)
                        <li>
                            <a href="{{ $item['href'] }}" class="flex items-center gap-3 px-4 py-2.5 transition hover:bg-slate-50">
                                <span class="relative flex h-12 w-20 shrink-0 items-center justify-center overflow-hidden rounded-md bg-slate-100 text-slate-300">
                                    <img src="{{ $item['thumb'] }}" alt="" loading="lazy" data-fallback="image" class="h-full w-full object-cover" />
                                    @if($item['play'])
                                        <span class="absolute inset-0 flex items-center justify-center bg-slate-900/25 text-white"><x-icon name="play" class="h-4 w-4" /></span>
                                    @endif
                                </span>
                                <span class="min-w-0">
                                    <span class="block line-clamp-2 break-words text-[13px] font-semibold leading-snug text-slate-900">{{ $item['title'] }}</span>
                                    @if($item['date'])
                                        <span class="pub-meta">{{ display_datetime($item['date'], 'd M Y') }}</span>
                                    @endif
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </section>
@endif
