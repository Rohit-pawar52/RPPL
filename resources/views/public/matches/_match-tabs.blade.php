{{--
    Compact per-match tab nav: Live | Scorecard | Match Info.
    Expects: $match, $active ('live' | 'scorecard' | 'info').
    Only include it when the match has scoring data (Innings) — the Live
    and Scorecard pages both redirect away otherwise, so rendering these
    tabs for an unscored match would only produce dead links. No Squads/
    Commentary tabs: there is no public per-match view for either.
--}}
@php
    $isLive = $match->match_status === 'live';
    $tabs = [
        'live' => [
            'label' => $isLive ? 'Live' : 'Ball-by-Ball',
            'url' => route('public.matches.live', $match),
            'icon' => 'chart-bar',
        ],
        'scorecard' => [
            'label' => 'Scorecard',
            'url' => route('public.matches.scorecard', $match),
            'icon' => 'document-chart',
        ],
        'info' => [
            'label' => 'Match Info',
            'url' => route('public.matches.show', $match),
            'icon' => 'clipboard',
        ],
    ];
@endphp

<nav class="-mx-4 mb-4 overflow-x-auto border-b border-neutral-200 px-4 sm:mx-0 sm:px-0" aria-label="Match sections">
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
