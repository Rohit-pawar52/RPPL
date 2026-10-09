@extends('layouts.public')

@section('title', __('matches.list.title').' · '.$branding->shortName)

@section('content')
    @php
        // Presentation-only split of the controller's single "upcoming"
        // collection (scheduled/toss/live) so live matches get their own
        // tab - no extra query, same eager-loaded models.
        $liveMatches = $upcoming->where('match_status', 'live');
        $scheduledMatches = $upcoming->where('match_status', '!=', 'live');

        // The results page only eager-loads the teams; one batched load adds
        // the two innings so each card can show the scores (never per card).
        $past->getCollection()->loadMissing(['firstInnings.battingTeam.team', 'secondInnings.battingTeam.team']);

        $counts = ['live' => $liveMatches->count(), 'upcoming' => $scheduledMatches->count(), 'results' => $past->total()];

        // Open on what people most likely want: the live match if there is
        // one, else what is next; a paginated results page stays on results.
        $initial = request()->has('page') ? 'results' : match (true) {
            $counts['live'] > 0 => 'live',
            $counts['upcoming'] > 0 => 'upcoming',
            $counts['results'] > 0 => 'results',
            default => 'upcoming',
        };

        $tabs = [
            'live' => __('matches.centre.live'),
            'upcoming' => __('matches.list.upcoming'),
            'results' => __('matches.list.results'),
        ];

        $todayKey = display_datetime(now(), 'Y-m-d');
        $tomorrowKey = display_datetime(now()->addDay(), 'Y-m-d');
        $byDay = fn ($matches) => $matches->groupBy(fn ($match) => display_datetime($match->scheduled_at, 'Y-m-d'));
        $dayTitle = function (string $key, $first) use ($todayKey, $tomorrowKey) {
            return match ($key) {
                $todayKey => __('ux_public_matches.list.today'),
                $tomorrowKey => __('ux_public_matches.list.tomorrow'),
                default => display_datetime($first->scheduled_at, 'l'),
            };
        };
    @endphp

    <x-public.page-header :title="__('matches.list.title')" :subtitle="__('matches.list.subtitle')" />

    @if($editions->isNotEmpty())
        {{-- Season: one tap, keeps the tab you are on. --}}
        <nav class="mb-4" aria-label="{{ __('ux_public_matches.list.season') }}">
            <div class="mx-chips">
                <a
                    href="{{ route('public.matches.index') }}"
                    data-mx-keep-hash
                    @class(['mx-chip', 'is-active' => ! $selectedEditionId])
                    @if(! $selectedEditionId) aria-current="true" @endif
                >{{ __('matches.list.all_editions') }}</a>
                @foreach($editions as $edition)
                    <a
                        href="{{ route('public.matches.index', ['edition_id' => $edition->id]) }}"
                        data-mx-keep-hash
                        @class(['mx-chip', 'is-active' => $selectedEditionId === $edition->id])
                        @if($selectedEditionId === $edition->id) aria-current="true" @endif
                    >{{ $edition->name }}</a>
                @endforeach
            </div>
        </nav>
    @endif

    <div id="mx-matches" class="mx-panels" data-initial="{{ $initial }}">
        <div class="sticky top-[3.75rem] z-20 -mx-4 mb-4 bg-surface px-4 py-2 lg:top-[4.5rem] lg:mx-0 lg:px-0" data-mx-bar>
            <div class="mx-seg sm:max-w-xl" role="tablist" aria-label="{{ __('ux_public_matches.list.filters') }}">
                @foreach($tabs as $key => $label)
                    <button
                        type="button"
                        role="tab"
                        id="mx-tab-{{ $key }}"
                        class="mx-seg-item"
                        aria-controls="mx-panel-{{ $key }}"
                        aria-selected="{{ $initial === $key ? 'true' : 'false' }}"
                        data-mx-tab="{{ $key }}"
                    >
                        @if($key === 'live' && $counts['live'] > 0)
                            <span class="live-dot text-green-600" aria-hidden="true"></span>
                        @endif
                        {{ $label }}
                        <span class="mx-seg-count">{{ $counts[$key] }}</span>
                    </button>
                @endforeach
            </div>
        </div>

        {{-- LIVE --}}
        <section id="mx-panel-live" class="mx-panel {{ $initial === 'live' ? 'is-active' : '' }}" role="tabpanel" aria-labelledby="mx-tab-live" data-mx-panel="live">
            <h2 class="mx-panel-title pub-h2 mb-3 flex items-center gap-2 text-green-700">
                <span class="live-dot" aria-hidden="true"></span>
                {{ __('matches.list.live_now') }}
            </h2>
            @if($liveMatches->isNotEmpty())
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($liveMatches as $match)
                        @include('public.matches._card', ['match' => $match])
                    @endforeach
                </div>
            @else
                <div class="pub-card">
                    <x-public.empty icon="play">
                        {{ __('ux_public_matches.list.no_live') }}
                        <x-slot:action>
                            <button type="button" class="btn btn-secondary btn-sm" data-mx-go="{{ $counts['upcoming'] > 0 ? 'upcoming' : 'results' }}">
                                {{ $counts['upcoming'] > 0 ? __('ux_public_matches.list.see_upcoming') : __('ux_public_matches.list.see_results') }}
                            </button>
                        </x-slot:action>
                    </x-public.empty>
                </div>
            @endif
        </section>

        {{-- UPCOMING --}}
        <section id="mx-panel-upcoming" class="mx-panel {{ $initial === 'upcoming' ? 'is-active' : '' }}" role="tabpanel" aria-labelledby="mx-tab-upcoming" data-mx-panel="upcoming">
            <h2 class="mx-panel-title pub-h2 mb-3">{{ __('matches.list.upcoming') }}</h2>
            @if($scheduledMatches->isNotEmpty())
                @foreach($byDay($scheduledMatches) as $dayKey => $dayMatches)
                    <div @class(['mx-day', 'is-today' => $dayKey === $todayKey])>
                        <b>{{ $dayTitle($dayKey, $dayMatches->first()) }}</b>
                        <i>{{ display_datetime($dayMatches->first()->scheduled_at, 'd M Y') }}</i>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach($dayMatches as $match)
                            @include('public.matches._card', ['match' => $match])
                        @endforeach
                    </div>
                @endforeach
            @else
                <div class="pub-card">
                    <x-public.empty icon="calendar">
                        {{ $liveMatches->isNotEmpty() ? __('matches.list.no_other_scheduled') : __('matches.list.no_scheduled') }}
                        <x-slot:action>
                            <p class="text-xs text-slate-400">{{ __('ux_public_matches.list.no_upcoming_hint') }}</p>
                        </x-slot:action>
                    </x-public.empty>
                </div>
            @endif
        </section>

        {{-- RESULTS --}}
        <section id="mx-panel-results" class="mx-panel {{ $initial === 'results' ? 'is-active' : '' }}" role="tabpanel" aria-labelledby="mx-tab-results" data-mx-panel="results">
            <h2 class="mx-panel-title pub-h2 mb-3">{{ __('matches.list.results') }}</h2>
            @if($past->isNotEmpty())
                @foreach($byDay($past->getCollection()) as $dayKey => $dayMatches)
                    <div class="mx-day">
                        <b>{{ $dayTitle($dayKey, $dayMatches->first()) }}</b>
                        <i>{{ display_datetime($dayMatches->first()->scheduled_at, 'd M Y') }}</i>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach($dayMatches as $match)
                            @include('public.matches._card', ['match' => $match])
                        @endforeach
                    </div>
                @endforeach

                @if($past->hasPages())
                    <div class="mt-6">
                        {{ $past->links() }}
                    </div>
                @endif
            @else
                <div class="pub-card">
                    <x-public.empty icon="trophy">
                        {{ __('matches.list.no_completed') }}
                        <x-slot:action>
                            <p class="text-xs text-slate-400">{{ __('ux_public_matches.list.no_results_hint') }}</p>
                        </x-slot:action>
                    </x-public.empty>
                </div>
            @endif
        </section>
    </div>

    <noscript>
        <style>.mx-panel { display: block !important; } .mx-panel-title { position: static !important; width: auto !important; height: auto !important; clip: auto !important; }</style>
    </noscript>

    <script>
        (function () {
            var root = document.getElementById('mx-matches');
            if (!root) { return; }
            var tabs = root.querySelectorAll('[data-mx-tab]');
            var panels = root.querySelectorAll('[data-mx-panel]');
            var keys = Array.prototype.map.call(tabs, function (tab) { return tab.dataset.mxTab; });

            root.classList.add('is-enhanced');

            function show(key, updateHash) {
                tabs.forEach(function (tab) { tab.setAttribute('aria-selected', tab.dataset.mxTab === key ? 'true' : 'false'); });
                panels.forEach(function (panel) { panel.classList.toggle('is-active', panel.dataset.mxPanel === key); });
                if (updateHash && window.history && history.replaceState) {
                    history.replaceState(null, '', location.pathname + location.search + '#' + key);
                }
            }

            var fromHash = location.hash.replace('#', '');
            show(keys.indexOf(fromHash) !== -1 && !new URLSearchParams(location.search).has('page') ? fromHash : root.dataset.initial, false);

            tabs.forEach(function (tab) {
                tab.addEventListener('click', function () { show(tab.dataset.mxTab, true); });
                tab.addEventListener('keydown', function (event) {
                    var i = keys.indexOf(tab.dataset.mxTab);
                    var next = event.key === 'ArrowRight' ? i + 1 : event.key === 'ArrowLeft' ? i - 1 : null;
                    if (next === null) { return; }
                    event.preventDefault();
                    var target = tabs[(next + tabs.length) % tabs.length];
                    target.focus();
                    show(target.dataset.mxTab, true);
                });
            });

            document.querySelectorAll('[data-mx-go]').forEach(function (button) {
                button.addEventListener('click', function () { show(button.dataset.mxGo, true); });
            });

            // Changing the season keeps the tab you are on.
            document.querySelectorAll('[data-mx-keep-hash]').forEach(function (link) {
                link.addEventListener('click', function () {
                    var active = root.querySelector('[data-mx-tab][aria-selected="true"]');
                    if (active) { link.hash = active.dataset.mxTab; }
                });
            });
        })();
    </script>
@endsection
