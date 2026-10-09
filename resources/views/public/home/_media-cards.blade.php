{{--
    Videos / News / Photos: three cards side by side (stacked on phones). In
    each, the first item is shown big (a wide picture with its title) and the
    next ones as compact rows; photos are a small picture grid. Every item and
    the "View all" link lead to that section's own page. A section with
    nothing published is left out and the row closes up; with none at all the
    whole block disappears.
    Expects $latestVideos, $latestNews, $latestPhotos.
--}}
@php
    $sections = collect([
        [
            'key' => 'videos',
            'title' => __('home.media.videos'),
            'icon' => 'play',
            'all' => route('public.videos.index'),
            'items' => $latestVideos->map(fn ($video) => [
                'href' => route('public.videos.index'),
                'thumb' => $video->thumbnail_path,
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
                'thumb' => $news->coverImage?->image_path,
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
            'grid' => true,
            'items' => $latestPhotos->map(fn ($photo) => [
                'href' => route('public.photos.index'),
                'thumb' => $photo->photo_path,
                'title' => $photo->title,
                'date' => $photo->created_at,
                'play' => false,
            ]),
        ],
    ])->filter(fn ($section) => $section['items']->isNotEmpty())->values();

    $columns = [1 => 'md:grid-cols-1', 2 => 'md:grid-cols-2', 3 => 'md:grid-cols-3'][$sections->count()] ?? 'md:grid-cols-3';
@endphp

@if($sections->isNotEmpty())
    <section aria-label="{{ __('ux_public_shell.home.latest') }}">
        <h2 class="mb-3 flex items-center gap-2 text-lg font-semibold tracking-tight text-slate-900">{{ __('ux_public_shell.home.latest') }}</h2>

        <div class="grid grid-cols-1 gap-4 {{ $columns }}">
            @foreach($sections as $section)
                <div class="pub-card flex flex-col overflow-hidden" data-home-section="{{ $section['key'] }}">
                    <header class="flex items-center justify-between gap-3 border-b border-line px-4 py-3">
                        <a href="{{ $section['all'] }}" class="flex min-w-0 items-center gap-2 text-[15px] font-semibold tracking-tight text-slate-900 transition-colors hover:text-brand">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-brand-soft text-brand"><x-icon :name="$section['icon']" class="h-4 w-4" /></span>
                            {{ $section['title'] }}
                        </a>
                        <a href="{{ $section['all'] }}" class="shrink-0 text-xs font-semibold text-link transition-colors hover:text-link-hover hover:underline">{{ __('home.media.view_all') }} &rarr;</a>
                    </header>

                    @if(! empty($section['grid']))
                        {{-- Photos: the first one big, the rest under it, each with its title over the picture. --}}
                        <ul class="grid flex-1 grid-cols-2 content-start gap-1.5 p-3">
                            @foreach($section['items'] as $item)
                                <li @class(['min-w-0', 'col-span-2' => $loop->first])>
                                    <a href="{{ $item['href'] }}" class="group relative block overflow-hidden rounded-lg bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand {{ $loop->first ? 'aspect-video' : 'aspect-[4/3]' }}">
                                        <x-media-image :path="$item['thumb']" kind="image" alt="" loading="lazy" decoding="async" class="h-full w-full object-cover transition-transform duration-300 motion-safe:group-hover:scale-105" />
                                        <span class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-slate-950/80 to-transparent px-2.5 pb-2 pt-8 text-xs font-medium leading-tight text-white">
                                            <span class="line-clamp-2 break-words">{{ $item['title'] }}</span>
                                        </span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <ul class="flex-1 divide-y divide-line">
                            @foreach($section['items'] as $item)
                                @if($loop->first)
                                    <li>
                                        <a href="{{ $item['href'] }}" class="group block transition-colors hover:bg-hover">
                                            <span class="relative block aspect-video max-h-64 w-full overflow-hidden bg-slate-100">
                                                <x-media-image :path="$item['thumb']" kind="image" alt="" loading="lazy" decoding="async" class="h-full w-full object-cover transition-transform duration-300 motion-safe:group-hover:scale-105" />
                                                @if($item['play'])
                                                    <span class="absolute inset-0 flex items-center justify-center bg-slate-900/25">
                                                        <span class="flex h-12 w-12 items-center justify-center rounded-full bg-white/90 text-brand shadow-pop"><x-icon name="play" class="h-6 w-6" /></span>
                                                    </span>
                                                @endif
                                            </span>
                                            <span class="block px-4 pb-3.5 pt-3">
                                                <span class="line-clamp-2 break-words text-[15px] font-semibold leading-snug text-slate-900">{{ $item['title'] }}</span>
                                                @if($item['date'])
                                                    <span class="mt-1 block text-xs text-slate-500">{{ display_datetime($item['date'], 'd M Y') }}</span>
                                                @endif
                                            </span>
                                        </a>
                                    </li>
                                @else
                                    <li>
                                        <a href="{{ $item['href'] }}" class="flex items-center gap-3 px-4 py-2.5 transition-colors hover:bg-hover">
                                            <span class="relative flex h-14 w-20 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-slate-100">
                                                <x-media-image :path="$item['thumb']" kind="image" alt="" loading="lazy" decoding="async" class="h-full w-full object-cover" />
                                                @if($item['play'])
                                                    <span class="absolute inset-0 flex items-center justify-center bg-slate-900/25 text-white"><x-icon name="play" class="h-5 w-5" /></span>
                                                @endif
                                            </span>
                                            <span class="min-w-0">
                                                <span class="line-clamp-2 break-words text-[13px] font-semibold leading-snug text-slate-900">{{ $item['title'] }}</span>
                                                @if($item['date'])
                                                    <span class="mt-0.5 block text-xs text-slate-500">{{ display_datetime($item['date'], 'd M Y') }}</span>
                                                @endif
                                            </span>
                                        </a>
                                    </li>
                                @endif
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endforeach
        </div>
    </section>
@endif
