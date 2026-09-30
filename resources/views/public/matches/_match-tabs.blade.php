{{--
    Compact per-match tab nav: Live | Scorecard | Squads | Match Info.
    Expects: $match (with innings_count loaded via loadCount('innings'))
    and $active ('live' | 'scorecard' | 'squads' | 'info').

    Live and Scorecard only appear once the match actually has scoring
    data (Innings) — both pages redirect back to Match Info otherwise,
    so showing them for an unscored match would only produce dead links.
    Squads and Match Info are always shown: a Playing XI (or its
    "not announced yet" state) and basic match info are both meaningful
    before a ball is bowled. No Commentary/Graphs/Highlights tabs — no
    public data backs them.
--}}
@php
    $isLive = $match->match_status === 'live';
    $tabs = [];

    if ($match->innings_count > 0) {
        $tabs['live'] = [
            'label' => __('matches.nav.live'),
            'url' => route('public.matches.live', $match),
            'icon' => 'chart-bar',
        ];
        $tabs['scorecard'] = [
            'label' => __('matches.nav.scorecard'),
            'url' => route('public.matches.scorecard', $match),
            'icon' => 'document-chart',
        ];
    }

    $tabs['squads'] = [
        'label' => __('matches.nav.squads'),
        'url' => route('public.matches.squads', $match),
        'icon' => 'users',
    ];
    $tabs['info'] = [
        'label' => __('matches.nav.match_info'),
        'url' => route('public.matches.show', $match),
        'icon' => 'clipboard',
    ];
@endphp

<nav class="-mx-4 mb-4 overflow-x-auto border-b border-neutral-200 px-4 sm:mx-0 sm:px-0" aria-label="{{ __('matches.nav.match_sections') }}">
    <ul class="flex min-w-max items-center gap-1">
        @foreach($tabs as $key => $tab)
            @php $current = $active === $key; @endphp
            <li>
                <a
                    href="{{ $tab['url'] }}"
                    @if($current) aria-current="page" @endif
                    class="-mb-px inline-flex items-center gap-1.5 border-b-2 px-3 py-2 text-[13px] font-medium {{ $current ? 'theme-primary-border theme-primary-text' : 'border-transparent text-neutral-500 hover:text-neutral-800' }}"
                >
                    @if($key === 'live' && $isLive)
                        <span class="relative flex h-2 w-2" aria-hidden="true">
                            <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-red-400 opacity-75"></span>
                            <span class="relative inline-flex h-2 w-2 rounded-full bg-red-500"></span>
                        </span>
                    @else
                        <x-icon :name="$tab['icon']" class="h-3.5 w-3.5" />
                    @endif
                    {{ $tab['label'] }}
                </a>
            </li>
        @endforeach
    </ul>
</nav>
