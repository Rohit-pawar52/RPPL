{{--
    Per-match tab nav: Live | Scorecard | Squads | Match Info. Sticks under the
    site header while the page scrolls, so another section is always one tap
    away. Expects: $match (with innings_count loaded via loadCount('innings'))
    and $active ('live' | 'scorecard' | 'squads' | 'info').

    Live and Scorecard only appear once the match actually has scoring
    data (Innings) - both pages redirect back to Match Info otherwise,
    so showing them for an unscored match would only produce dead links.
    Squads and Match Info are always shown. No Commentary/Graphs/
    Highlights tabs - no public data backs them (ball-by-ball commentary
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

<nav class="mx-tabs" id="mx-match-tabs" aria-label="{{ __('matches.nav.match_sections') }}">
    <div class="mx-tabs-inner">
        @foreach($tabs as $key => $tab)
            @php $current = $active === $key; @endphp
            <a
                href="{{ $tab['url'] }}"
                @if($current) aria-current="page" @endif
                class="mx-tab {{ $current ? 'is-active' : '' }}"
            >
                @if($key === 'live' && $isLive)
                    <span class="live-dot text-green-600" aria-hidden="true"></span>
                @endif
                {{ $tab['label'] }}
            </a>
        @endforeach
    </div>
</nav>

<script>
    (function () {
        // Switching tab keeps you where the tabs are, instead of back at the top of the page.
        var nav = document.getElementById('mx-match-tabs');
        if (!nav) { return; }
        var offset = function () {
            var bar = document.querySelector('[data-public-header]');
            return (bar ? bar.offsetHeight : 56) + 8;
        };
        try {
            if (sessionStorage.getItem('mx-tab-scroll') === '1') {
                sessionStorage.removeItem('mx-tab-scroll');
                window.scrollTo(0, nav.getBoundingClientRect().top + window.scrollY - offset());
            }
        } catch (e) { /* storage blocked: stay at the top */ }
        nav.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', function () {
                try {
                    var stuck = offset() + 2 >= nav.getBoundingClientRect().top;
                    sessionStorage.setItem('mx-tab-scroll', stuck ? '1' : '0');
                } catch (e) { /* ignore */ }
            });
        });
    })();
</script>
