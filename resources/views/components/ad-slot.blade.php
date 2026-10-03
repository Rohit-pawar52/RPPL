{{--
    Sponsor placement (see App\View\Components\AdSlot). Display only: the
    media has no link, ignores clicks and drags, and sits in a box of fixed
    proportions so the page never jumps when it loads. A failed image or
    video removes its own box instead of leaving a broken picture.
--}}
@if($tier === 'mini')
    <section data-ad="mini" class="pub-card mt-6 p-4" aria-label="{{ __('ads.our_sponsors') }}">
        <p class="pub-eyebrow">{{ __('ads.our_sponsors') }}</p>
        <ul class="mt-3 flex flex-wrap items-center gap-x-6 gap-y-3">
            @foreach($ads as $ad)
                <li class="flex h-12 items-center">
                    <img
                        src="{{ $ad->mediaUrl() }}"
                        alt="{{ $ad->title }}"
                        loading="lazy"
                        draggable="false"
                        class="pointer-events-none max-h-12 max-w-[8rem] select-none object-contain"
                        onerror="this.closest('li').remove()"
                    >
                </li>
            @endforeach
        </ul>
    </section>
@else
    @php($ad = $ads->first())
    <aside data-ad="{{ $tier }}" class="my-4" aria-label="{{ __('ads.sponsored') }}">
        <div @class([
            'pub-card relative overflow-hidden bg-slate-50',
            'aspect-[3/1] sm:aspect-[5/1]' => $tier === 'main',
            'aspect-[3/1] sm:aspect-[6/1]' => $tier !== 'main',
        ])>
            @if($ad->isVideo())
                <video
                    muted
                    loop
                    playsinline
                    preload="none"
                    disablepictureinpicture
                    disableremoteplayback
                    aria-hidden="true"
                    tabindex="-1"
                    data-ad-src="{{ $ad->mediaUrl() }}"
                    @if($ad->posterUrl()) poster="{{ $ad->posterUrl() }}" @endif
                    class="pointer-events-none h-full w-full select-none object-contain"
                    onerror="this.closest('[data-ad]').remove()"
                ></video>
            @else
                <img
                    src="{{ $ad->mediaUrl() }}"
                    alt="{{ $ad->title }}"
                    @if($tier !== 'main') loading="lazy" @endif
                    decoding="async"
                    draggable="false"
                    class="pointer-events-none h-full w-full select-none object-contain"
                    onerror="this.closest('[data-ad]').remove()"
                >
            @endif
            <span class="pointer-events-none absolute right-2 top-2 rounded bg-slate-900/60 px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide text-white">{{ __('ads.sponsored') }}</span>
        </div>
    </aside>

    @if($ad->isVideo())
        {{-- Videos only start loading once they scroll into view. --}}
        @once
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    var start = function (video) {
                        video.src = video.dataset.adSrc;
                        var played = video.play();
                        if (played && played.catch) { played.catch(function () {}); }
                    };
                    document.querySelectorAll('video[data-ad-src]').forEach(function (video) {
                        if (!('IntersectionObserver' in window)) { start(video); return; }
                        var observer = new IntersectionObserver(function (entries) {
                            entries.forEach(function (entry) {
                                if (entry.isIntersecting) { observer.disconnect(); start(video); }
                            });
                        });
                        observer.observe(video);
                    });
                });
            </script>
        @endonce
    @endif
@endif
