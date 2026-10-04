{{--
    Sponsor placement (see App\View\Components\AdSlot). Display only: the
    media has no link, ignores clicks and drags, and sits in a box of fixed
    height so the page never jumps when it loads and a tall poster can never
    push content down. A failed image or video removes its own box instead
    of leaving a broken picture. The caller adds spacing/width classes
    (e.g. class="mt-4").

    banner (default) — a slim full-width strip, about 72 px tall on phones
                       and 88 px on larger screens;
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
    @php($ad = $ads->first())

    @once
        <style>
            .rppl-ad-banner { height: 4.5rem; }
            .rppl-ad-card-media { height: 6.5rem; }
            @media (min-width: 640px) { .rppl-ad-banner { height: 5.5rem; } }
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
        <aside data-ad="{{ $tier }}" {{ $attributes }} aria-label="{{ __('ads.sponsored') }}">
            <div class="rppl-ad-banner pub-card relative overflow-hidden bg-slate-50">
                <x-ad-slot-media :ad="$ad" :eager="$tier === 'main'" />
                <span class="pointer-events-none absolute right-2 top-2 rounded bg-slate-900/60 px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide text-white">{{ __('ads.sponsored') }}</span>
            </div>
        </aside>
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
