{{--
    The sponsor pop-up of the player auction page (see App\View\Components\
    AdPopup). It opens by itself after `popup_first_seconds`, stays for
    `popup_visible_seconds`, closes, and opens again after
    `popup_interval_seconds` (config/ads.php). Display only: the picture
    ignores clicks and drags; the only thing to click is the close button
    (or the dimmed area, or Esc). It never opens while the tab is in the
    background, and a picture or clip that cannot be loaded removes the whole
    pop-up. It lives outside the part of the page that redraws itself, so a
    refresh of the auction never closes it.
--}}
@php
    $backdrop = $ad->isVideo() ? $ad->posterUrl() : $ad->mediaUrl();
@endphp

@once
    <style>
        .rppl-ad-popup { visibility: hidden; opacity: 0; transition: opacity .45s ease, visibility 0s linear .45s; }
        .rppl-ad-popup.is-open { visibility: visible; opacity: 1; transition: opacity .45s ease; }
        .rppl-ad-popup.is-open [data-ad-popup-card] { transform: none; }
        .rppl-ad-popup [data-ad-popup-card] { transform: translateY(1rem) scale(.97); transition: transform .45s ease; }
        @media (prefers-reduced-motion: reduce) {
            .rppl-ad-popup, .rppl-ad-popup.is-open, .rppl-ad-popup [data-ad-popup-card] { transition: none; }
        }
    </style>
@endonce

<div
    data-ad="popup"
    data-ad-popup
    data-ad-first="{{ $firstSeconds * 1000 }}"
    data-ad-visible="{{ $visibleSeconds * 1000 }}"
    data-ad-interval="{{ $intervalSeconds * 1000 }}"
    class="rppl-ad-popup fixed inset-0 z-50 flex items-center justify-center p-4"
    aria-hidden="true"
>
    <div data-ad-popup-close class="absolute inset-0 {{ $big ? 'bg-slate-950/45' : 'bg-slate-950/55' }}"></div>

    <section
        data-ad-popup-card
        role="dialog"
        aria-modal="false"
        aria-label="{{ __('ads.sponsored') }}"
        class="relative w-full overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-black/10 {{ $big ? 'max-w-[min(82vw,96rem)]' : 'max-w-3xl' }}"
    >
        <button
            type="button"
            data-ad-popup-close
            aria-label="{{ __('ads.close') }}"
            class="absolute right-2 top-2 z-10 flex items-center justify-center rounded-full bg-slate-900/70 leading-none text-white transition hover:bg-slate-900 {{ $big ? 'h-[clamp(2rem,2.6vw,3.5rem)] w-[clamp(2rem,2.6vw,3.5rem)] text-[clamp(1.25rem,1.8vw,2.5rem)]' : 'h-8 w-8 text-xl' }}"
        >&times;</button>

        <div class="relative overflow-hidden bg-slate-50" style="height: {{ $big ? 'clamp(9rem, 16vw, 22rem)' : 'clamp(7rem, 21vw, 10.5rem)' }};">
            @if($backdrop)
                <div aria-hidden="true" data-ad-backdrop style="position: absolute; inset: 0; background: url('{{ $backdrop }}') center / cover no-repeat; filter: blur(18px); transform: scale(1.2); opacity: .75;"></div>
            @endif
            <div style="position: relative; width: 100%; height: 100%;">
                <x-ad-slot-media :ad="$ad" eager />
            </div>
        </div>

        <div class="flex items-center justify-between gap-3 border-t border-line px-4 py-2">
            <p class="truncate font-semibold text-slate-800 {{ $big ? 'text-[clamp(0.9rem,1.5vw,2rem)]' : 'text-[13px]' }}">{{ $ad->title }}</p>
            <p class="shrink-0 font-medium uppercase tracking-wide text-slate-400 {{ $big ? 'text-[clamp(0.7rem,1vw,1.3rem)]' : 'text-[11px]' }}">{{ __('ads.sponsored') }}</p>
        </div>
    </section>
</div>

@once
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var popup = document.querySelector('[data-ad-popup]');
            if (!popup) { return; }

            var first = parseInt(popup.dataset.adFirst, 10);
            var visibleFor = parseInt(popup.dataset.adVisible, 10);
            var every = parseInt(popup.dataset.adInterval, 10);
            var video = popup.querySelector('video');
            var timer = null;

            var open = function () {
                // The picture could not be loaded and removed the pop-up: nothing to show.
                if (!document.body.contains(popup)) { return; }
                // Never open on a page nobody is looking at; try again shortly.
                if (document.hidden) { timer = setTimeout(open, 5000); return; }

                popup.classList.add('is-open');
                popup.setAttribute('aria-hidden', 'false');
                if (video) {
                    if (!video.src && video.dataset.adSrc) { video.src = video.dataset.adSrc; }
                    var played = video.play();
                    if (played && played.catch) { played.catch(function () {}); }
                }
                timer = setTimeout(close, visibleFor);
            };

            var close = function () {
                clearTimeout(timer);
                popup.classList.remove('is-open');
                popup.setAttribute('aria-hidden', 'true');
                if (video) { video.pause(); }
                timer = setTimeout(open, every);
            };

            popup.querySelectorAll('[data-ad-popup-close]').forEach(function (el) {
                el.addEventListener('click', close);
            });
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape' && popup.classList.contains('is-open')) { close(); }
            });

            timer = setTimeout(open, first);
        });
    </script>
@endonce
