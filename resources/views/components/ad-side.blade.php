{{--
    A sponsor box for the side of the live match page (see App\View\Components\AdSide). A fixed 6:5 box, so the page
    never jumps; every ad is a slide, the next one fades in every few seconds. A picture of another shape keeps its
    proportions and the sides are filled with a blurred copy of it. A slide that cannot load removes itself; with no
    slide left the box goes too.
--}}
@once
    <style>
        .rppl-ad-side { aspect-ratio: 6 / 5; }
        .rppl-ad-side [data-ad-slide] { position: absolute; inset: 0; opacity: 0; transition: opacity .6s ease; }
        .rppl-ad-side [data-ad-slide].is-on { opacity: 1; }
        @media (prefers-reduced-motion: reduce) { .rppl-ad-side [data-ad-slide] { transition: none; } }
    </style>
@endonce

<aside data-ad-side data-rotate="{{ $rotateMs }}" {{ $attributes->class(['pub-card relative overflow-hidden bg-slate-50 shadow-card']) }} aria-label="{{ __('ads.sponsored') }}">
    <div class="rppl-ad-side relative w-full">
        @foreach($ads as $ad)
            @php $backdrop = $ad->isVideo() ? $ad->posterUrl() : $ad->mediaUrl(); @endphp
            <div data-ad="side-item" data-ad-slide class="{{ $loop->first ? 'is-on' : '' }}">
                @if($backdrop)
                    <div aria-hidden="true" style="position: absolute; inset: 0; background: url('{{ $backdrop }}') center / cover no-repeat; filter: blur(18px); transform: scale(1.2); opacity: .75;"></div>
                @endif
                <div style="position: relative; width: 100%; height: 100%;">
                    <x-ad-slot-media :ad="$ad" :eager="$loop->first" />
                </div>
            </div>
        @endforeach
    </div>
    <span class="pointer-events-none absolute bottom-2 right-2 rounded-full bg-slate-900/60 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-white backdrop-blur-sm">{{ __('ads.sponsored') }}</span>
</aside>

@once
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('[data-ad-side]').forEach(function (box) {
                var every = parseInt(box.dataset.rotate, 10) || 0;
                var current = 0;

                var slides = function () { return box.querySelectorAll('[data-ad-slide]'); };
                var show = function (index) {
                    var all = slides();
                    if (all.length === 0) { box.remove(); return; }
                    current = index % all.length;
                    all.forEach(function (slide, i) {
                        var on = i === current;
                        slide.classList.toggle('is-on', on);
                        var video = slide.querySelector('video');
                        if (!video) { return; }
                        if (on) {
                            if (!video.src && video.dataset.adSrc) { video.src = video.dataset.adSrc; }
                            var played = video.play();
                            if (played && played.catch) { played.catch(function () {}); }
                        } else {
                            video.pause();
                        }
                    });
                };

                show(0);

                if (every > 0) {
                    setInterval(function () {
                        if (document.visibilityState === 'visible' && slides().length > 1) { show(current + 1); }
                    }, every);
                }
            });
        });
    </script>
@endonce
