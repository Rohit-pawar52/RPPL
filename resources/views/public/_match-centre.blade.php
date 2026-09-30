{{--
    Match Centre — supporting match cards under the featured block.
    Expects $liveMatch, $nextMatch, $recentMatch (any may be null) and
    $upcomingMatches (inherited from the homepage). The featured block
    already shows LIVE > NEXT > RECENT, so this strip only adds what it
    does not: while a match is live, the next scheduled match; and the
    latest result whenever the featured block is showing something else.
    Renders nothing when there is nothing to add.

    Homepage-only: it depends on HomeController's own Match Centre query.
--}}
@php
    $followUp = $liveMatch
        ? ($upcomingMatches ?? collect())->firstWhere(fn ($m) => $m->match_status !== 'live')
        : null;
    $showRecent = $recentMatch && ($liveMatch || $nextMatch);
@endphp
@if($followUp || $showRecent)
    <div class="mt-4 grid gap-4 sm:grid-cols-2">
        @if($followUp)
            <div class="flex flex-col">
                <p class="pub-eyebrow mb-1.5">{{ __('matches.centre.next_match') }}</p>
                @include('public.matches._card', ['match' => $followUp])
            </div>
        @endif
        @if($showRecent)
            <div class="flex flex-col">
                <p class="pub-eyebrow mb-1.5">{{ __('matches.centre.recent_result') }}</p>
                @include('public.matches._card', ['match' => $recentMatch])
            </div>
        @endif
    </div>
@endif
