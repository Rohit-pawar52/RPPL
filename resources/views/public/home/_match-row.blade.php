{{--
    The homepage match row: the live / next one or two matches, a sponsor card,
    then the latest results, and a final "All matches" tile. Every tile is one
    link (live -> Live page, finished -> Scorecard), so the thing people came
    for is one tap from the first screen. The sponsor card simply isn't there
    when no Normal sponsor is live.

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

<section aria-label="{{ __('home.strip.label') }}" data-match-row>
    <div class="mb-3 flex items-center justify-between gap-3">
        <h2 class="flex items-center gap-2 text-lg font-semibold tracking-tight text-slate-900">
            @if($liveMatchId)
                <span class="live-dot text-red-600" aria-hidden="true"></span>
            @endif
            {{ __('ux_public_shell.home.match_centre') }}
        </h2>
        <a href="{{ route('public.matches.index') }}" class="inline-flex min-h-8 items-center gap-1 text-xs font-semibold text-link transition-colors hover:text-link-hover">
            {{ __('ux_public_shell.home.see_all_matches') }}
            <x-icon name="arrow-right" class="h-3.5 w-3.5" />
        </a>
    </div>

    <div class="relative">
        <div data-match-row-track class="rppl-strip -mx-4 flex snap-x scroll-px-4 items-stretch gap-3 overflow-x-auto px-4 pb-2 pt-0.5 lg:mx-0 lg:scroll-px-0 lg:px-0">
            @foreach($upcomingMatches as $match)
                @include('public.home._match-card', [
                    'match' => $match,
                    'chase' => $match->id === $liveMatchId ? ($liveMatchData['chase'] ?? null) : null,
                ])
            @endforeach

            @if($upcomingMatches->isEmpty() && $recentMatches->isEmpty())
                <div class="pub-card flex w-[18rem] shrink-0 snap-start flex-col items-center justify-center gap-1 px-4 py-6 text-center">
                    <x-public.empty icon="calendar" class="!px-0 !py-0">{{ __('ux_public_shell.home.no_matches') }}</x-public.empty>
                    <p class="text-xs text-slate-400">{{ __('ux_public_shell.home.no_matches_hint') }}</p>
                </div>
            @endif

            <x-ad-slot tier="normal" variant="card" class="w-[18rem] shrink-0 snap-start" />

            @foreach($recentMatches as $match)
                @include('public.home._match-card', ['match' => $match])
            @endforeach

            <a
                href="{{ route('public.matches.index') }}"
                class="flex w-40 shrink-0 snap-start flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-slate-300 bg-white/60 p-3 text-center transition hover:border-brand hover:bg-hover focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
            >
                <span class="flex h-10 w-10 items-center justify-center rounded-full bg-brand-soft text-brand"><x-icon name="arrow-right" class="h-5 w-5" /></span>
                <span class="text-[13px] font-semibold text-slate-800">{{ __('home.strip.all_matches') }}</span>
                <span class="text-xs text-slate-500">{{ __('home.strip.all_matches_hint') }}</span>
            </a>
        </div>

        @foreach(['prev' => ['left-1', 'M15 19l-7-7 7-7', __('home.strip.scroll_left')], 'next' => ['right-1', 'M9 5l7 7-7 7', __('home.strip.scroll_right')]] as $direction => [$side, $path, $label])
            <button
                type="button"
                hidden
                data-match-row-{{ $direction }}
                aria-label="{{ $label }}"
                title="{{ $label }}"
                class="absolute {{ $side }} top-1/2 z-10 flex max-md:hidden h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-white text-slate-700 shadow-pop ring-1 ring-black/5 transition hover:bg-hover hover:text-brand focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand"
            >
                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $path }}" /></svg>
            </button>
        @endforeach
    </div>
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
