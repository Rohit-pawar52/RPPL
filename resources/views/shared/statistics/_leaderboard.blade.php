{{--
    Shared between admin and public Edition pages - purely presentational.

    Expects: $leaderboard (from PlayerStatisticsService::getEditionLeaderboard())
    Optional: $edition + $showMore = true adds a "see all top 20" link under each
    board (the public season page does; the admin page does not).
--}}
@php
    $showMore = ($showMore ?? false) && isset($edition);

    $battingRows = collect($leaderboard['topRunScorers'])->map(fn ($entry) => [
        'player' => $entry['player'],
        'main' => $entry['stats']['runs'],
        'extras' => [
            $entry['stats']['innings_batted'],
            $entry['stats']['batting_average'] !== null ? number_format($entry['stats']['batting_average'], 2) : '-',
            number_format($entry['stats']['strike_rate'], 2),
        ],
    ])->all();

    $bowlingRows = collect($leaderboard['topWicketTakers'])->map(fn ($entry) => [
        'player' => $entry['player'],
        'main' => $entry['stats']['wickets'],
        'extras' => [
            $entry['stats']['overs'],
            $entry['stats']['runs_conceded'],
            number_format($entry['stats']['economy'], 2),
        ],
    ])->all();
@endphp
<div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
    <section class="pub-card overflow-hidden">
        <header class="flex items-center justify-between gap-3 border-b border-line px-4 py-3 sm:px-5">
            <h3 class="text-[15px] font-semibold tracking-tight text-slate-900">{{ __('directory.leaderboard.top_run_scorers') }}</h3>
            @if($showMore)
                <a href="{{ route('public.editions.stats', [$edition, 'runs']) }}" class="shrink-0 text-xs font-semibold text-link hover:text-link-hover hover:underline">{{ __('ux_public_matches.stats.view_all') }} &rarr;</a>
            @endif
        </header>
        @include('shared.statistics._board', [
            'rows' => $battingRows,
            'mainLabel' => __('directory.leaderboard.runs'),
            'extraLabels' => [__('directory.leaderboard.innings'), __('directory.leaderboard.average'), __('directory.leaderboard.strike_rate')],
            'emptyText' => __('directory.leaderboard.batting_empty'),
        ])
    </section>

    <section class="pub-card overflow-hidden">
        <header class="flex items-center justify-between gap-3 border-b border-line px-4 py-3 sm:px-5">
            <h3 class="text-[15px] font-semibold tracking-tight text-slate-900">{{ __('directory.leaderboard.top_wicket_takers') }}</h3>
            @if($showMore)
                <a href="{{ route('public.editions.stats', [$edition, 'wickets']) }}" class="shrink-0 text-xs font-semibold text-link hover:text-link-hover hover:underline">{{ __('ux_public_matches.stats.view_all') }} &rarr;</a>
            @endif
        </header>
        @include('shared.statistics._board', [
            'rows' => $bowlingRows,
            'mainLabel' => __('directory.leaderboard.wickets'),
            'extraLabels' => [__('directory.leaderboard.overs'), __('directory.leaderboard.runs'), __('directory.leaderboard.economy')],
            'emptyText' => __('directory.leaderboard.bowling_empty'),
        ])
    </section>
</div>
