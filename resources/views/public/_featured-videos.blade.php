{{--
    Homepage Featured Videos — up to 3 active videos, priority order,
    pre-fetched by HomeController as $featuredVideos. Renders nothing at
    all (no header, no empty card) when there are none, so the homepage
    loses no vertical space to an unused section.
--}}
@if($featuredVideos->isNotEmpty())
    <section class="mt-4 rounded-lg border border-neutral-200 bg-white p-4">
        <div class="mb-2 flex items-center justify-between gap-2">
            <h2 class="inline-flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">
                <x-icon name="play" class="h-4 w-4" />
                {{ __('directory.videos.featured') }}
            </h2>
            <a href="{{ route('public.videos.index') }}" class="text-[11px] font-medium theme-link hover:underline">{{ __('directory.videos.view_all') }} &rarr;</a>
        </div>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($featuredVideos as $video)
                @include('public._video-card', ['video' => $video])
            @endforeach
        </div>
    </section>
@endif
