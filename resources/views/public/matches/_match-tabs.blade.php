{{--
    Per-match tab nav: Live | Scorecard | Squads | Match Info.
    Expects: $match (with innings_count loaded via loadCount('innings'))
    and $active ('live' | 'scorecard' | 'squads' | 'info').

    Live and Scorecard only appear once the match actually has scoring
    data (Innings) — both pages redirect back to Match Info otherwise,
    so showing them for an unscored match would only produce dead links.
    Squads and Match Info are always shown. No Commentary/Graphs/
    Highlights tabs — no public data backs them (ball-by-ball commentary
    lives inside the Live tab).
--}}
@php
    $isLive = $match->match_status === 'live';
    $tabs = [];

    if ($match->innings_count > 0) {
        $tabs['live'] = ['label' => __('matches.nav.live'), 'url' => route('public.matches.live', $match)];
        $tabs['scorecard'] = ['label' => __('matches.nav.scorecard'), 'url' => route('public.matches.scorecard', $match)];
    }

    $tabs['squads'] = ['label' => __('matches.nav.squads'), 'url' => route('public.matches.squads', $match)];
    $tabs['info'] = ['label' => __('matches.nav.match_info'), 'url' => route('public.matches.show', $match)];
@endphp

<nav class="pub-tabs px-2 sm:px-3" aria-label="{{ __('matches.nav.match_sections') }}">
    @foreach($tabs as $key => $tab)
        @php $current = $active === $key; @endphp
        <a
            href="{{ $tab['url'] }}"
            @if($current) aria-current="page" @endif
            class="pub-tab inline-flex min-h-10 items-center gap-1.5 px-3.5 {{ $current ? 'pub-tab-active' : '' }}"
        >
            @if($key === 'live' && $isLive)
                <span class="live-dot text-green-600" aria-hidden="true"></span>
            @endif
            {{ $tab['label'] }}
        </a>
    @endforeach
</nav>
