{{--
    The homepage match row — one horizontally scrolling line, like a score
    ticker: the live / next one or two matches, a sponsor card, then the
    latest results, and a final "All matches" tile. Nothing here is taller
    than a single match tile, so the page stays short. The sponsor card
    simply isn't there when no Normal sponsor is live.
    Expects $upcomingMatches, $recentMatches, $liveMatchId, $liveMatchData.
--}}
<section class="mt-4" aria-label="{{ __('home.strip.label') }}">
    <div class="-mx-4 flex snap-x items-stretch gap-3 overflow-x-auto px-4 pb-2 lg:mx-0 lg:px-0">
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
</section>
