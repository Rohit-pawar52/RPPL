{{--
    Sponsor placement (see App\View\Components\AdSlot). Display only: the
    media has no link, ignores clicks and drags, and sits in a box of fixed
    height so the page never jumps when it loads and a tall poster can never
    push content down. A failed image or video removes its own box instead
    of leaving a broken picture. The caller adds spacing/width classes
    (e.g. class="mt-4").

    banner (default) — a slim full-width strip, about 72 px tall on phones
                       and 88 px on larger screens. An image that is not
                       as wide as the strip keeps its proportions and the
                       sides are filled with a blurred copy of it, so the
                       strip always looks full width. It folds away after a
                       few seconds and comes back later (config/ads.php);
    card             — a tile as wide as a match card, for a scrolling row.
--}}
@if($tier === 'mini')
    <section data-ad="mini" {{ $attributes->class(['pub-card p-4']) }} aria-label="{{ __('ads.our_sponsors') }}">
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
                        style="max-height: 3rem; max-width: 8rem;"
                        onerror="this.closest('li').remove()"
                    >
                </li>
            @endforeach
        </ul>
    </section>
@else
    @php $ad = $ads->first(); @endphp

    @once
        <style>
            .rppl-ad-banner { height: 4.5rem; }
            .rppl-ad-card-media { height: 6.5rem; }
            @media (min-width: 640px) { .rppl-ad-banner { height: 5.5rem; } }
            /* A banner folds away (and back) smoothly; margin goes with it. */
            [data-ad-cycle] { max-height: 8rem; transition: max-height .5s ease, margin .5s ease, opacity .4s ease; }
            [data-ad-cycle].is-folded { max-height: 0; margin-top: 0; margin-bottom: 0; opacity: 0; pointer-events: none; }
            @media (prefers-reduced-motion: reduce) { [data-ad-cycle] { transition: none; } }
        </style>
    @endonce

    @if($variant === 'card')
        <aside data-ad="{{ $tier }}" {{ $attributes->class(['flex']) }} aria-label="{{ __('ads.sponsored') }}">
            <div class="pub-card flex w-full flex-col overflow-hidden">
                <div class="rppl-ad-card-media relative bg-slate-50">
                    <x-ad-slot-media :ad="$ad" eager />
                </div>
                <div class="flex flex-1 flex-col justify-center border-t border-line px-3 py-2">
                    <p class="truncate text-[13px] font-semibold text-slate-800">{{ $ad->title }}</p>
                    <p class="pub-meta uppercase tracking-wide">{{ __('ads.sponsored') }}</p>
                </div>
            </div>
        </aside>
    @else
        @php
            $visibleSeconds = (int) config('ads.banner_visible_seconds');
            $hiddenSeconds = (int) config('ads.banner_hidden_seconds');
            $cycles = $visibleSeconds > 0 && $hiddenSeconds > 0;
            $backdrop = $ad->isVideo() ? $ad->posterUrl() : $ad->mediaUrl();
        @endphp
        <aside
            data-ad="{{ $tier }}"
            @if($cycles) data-ad-cycle data-ad-visible="{{ $visibleSeconds * 1000 }}" data-ad-hidden="{{ $hiddenSeconds * 1000 }}" @endif
            {{ $attributes }}
            aria-label="{{ __('ads.sponsored') }}"
        >
            <div class="rppl-ad-banner pub-card relative overflow-hidden bg-slate-50">
                @if($backdrop)
                    <div aria-hidden="true" data-ad-backdrop style="position: absolute; inset: 0; background: url('{{ $backdrop }}') center / cover no-repeat; filter: blur(18px); transform: scale(1.2); opacity: .75;"></div>
                @endif
                <div style="position: relative; width: 100%; height: 100%;">
                    <x-ad-slot-media :ad="$ad" :eager="$tier === 'main'" />
                </div>
                <span class="pointer-events-none absolute right-2 top-2 rounded bg-slate-900/60 px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide text-white">{{ __('ads.sponsored') }}</span>
            </div>
        </aside>

        @if($cycles)
            @once
                <script>
                    document.addEventListener('DOMContentLoaded', function () {
                        document.querySelectorAll('[data-ad-cycle]').forEach(function (banner) {
                            var visibleFor = parseInt(banner.dataset.adVisible, 10);
                            var hiddenFor = parseInt(banner.dataset.adHidden, 10);
                            var video = banner.querySelector('video');

                            var fold = function () {
                                banner.classList.add('is-folded');
                                banner.setAttribute('aria-hidden', 'true');
                                if (video) { video.pause(); }
                                setTimeout(unfold, hiddenFor);
                            };
                            var unfold = function () {
                                // Never push the page the reader is looking at: if the banner is
                                // above the part of the page on screen, wait and try again.
                                if (banner.getBoundingClientRect().bottom < 0) { setTimeout(unfold, 5000); return; }
                                banner.classList.remove('is-folded');
                                banner.removeAttribute('aria-hidden');
                                if (video && video.src) { var p = video.play(); if (p && p.catch) { p.catch(function () {}); } }
                                setTimeout(fold, visibleFor);
                            };

                            setTimeout(fold, visibleFor);
                        });
                    });
                </script>
            @endonce
        @endif
    @endif

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
