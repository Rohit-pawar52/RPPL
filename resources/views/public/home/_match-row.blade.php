{{--
    The homepage match row — one line, like a score ticker: the live / next
    one or two matches, a sponsor card, then the latest results, and a final
    "All matches" tile. Nothing here is taller than a single match tile, so
    the page stays short. The sponsor card simply isn't there when no Normal
    sponsor is live.

    No scrollbar: when the row is wider than the screen, round arrow buttons
    appear at its edges (only the directions that still have more to show),
    and touch / trackpad swiping keeps working. Without JavaScript the row
    just scrolls by swiping.
    Expects $upcomingMatches, $recentMatches, $liveMatchId, $liveMatchData.
--}}
@once
    <style>
        .rppl-strip { scrollbar-width: none; -ms-overflow-style: none; }
        .rppl-strip::-webkit-scrollbar { display: none; }
    </style>
@endonce

<section class="relative mt-4" aria-label="{{ __('home.strip.label') }}" data-match-row>
    <div data-match-row-track class="rppl-strip -mx-4 flex snap-x items-stretch gap-3 overflow-x-auto px-4 pb-1 lg:mx-0 lg:px-0">
        @foreach($upcomingMatches as $match)
            @include('public.home._match-card', [
                'match' => $match,
                'chase' => $match->id === $liveMatchId ? ($liveMatchData['chase'] ?? null) : null,
            ])
        @endforeach

        <x-ad-slot tier="normal" variant="card" class="w-[17rem] shrink-0 snap-start" />

        @foreach($recentMatches as $match)
            @include('public.home._match-card', ['match' => $match])
        @endforeach

        <a
            href="{{ route('public.matches.index') }}"
            class="pub-card flex w-40 shrink-0 snap-start flex-col items-center justify-center gap-1 p-3 text-center transition hover:border-green-300"
        >
            <span class="text-[13px] font-semibold text-green-700">{{ __('home.strip.all_matches') }} &rarr;</span>
            <span class="pub-meta">{{ __('home.strip.all_matches_hint') }}</span>
        </a>
    </div>

    @foreach(['prev' => ['left-1', 'M15 19l-7-7 7-7', __('home.strip.scroll_left')], 'next' => ['right-1', 'M9 5l7 7-7 7', __('home.strip.scroll_right')]] as $direction => [$side, $path, $label])
        <button
            type="button"
            hidden
            data-match-row-{{ $direction }}
            aria-label="{{ $label }}"
            title="{{ $label }}"
            class="absolute {{ $side }} top-1/2 z-10 flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-full bg-green-600 text-white shadow-md ring-2 ring-white transition hover:bg-green-700 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-green-700"
        >
            <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $path }}" /></svg>
        </button>
    @endforeach
</section>

@once
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('[data-match-row]').forEach(function (row) {
                var track = row.querySelector('[data-match-row-track]');
                var prev = row.querySelector('[data-match-row-prev]');
                var next = row.querySelector('[data-match-row-next]');

                // Show an arrow only while there is more to see in that direction.
                var update = function () {
                    var max = track.scrollWidth - track.clientWidth;
                    prev.hidden = track.scrollLeft <= 4;
                    next.hidden = max <= 4 || track.scrollLeft >= max - 4;
                };
                var step = function (direction) {
                    track.scrollBy({ left: direction * Math.max(track.clientWidth * 0.8, 200), behavior: 'smooth' });
                };

                prev.addEventListener('click', function () { step(-1); });
                next.addEventListener('click', function () { step(1); });
                track.addEventListener('scroll', update, { passive: true });
                window.addEventListener('resize', update);
                update();
            });
        });
    </script>
@endonce
