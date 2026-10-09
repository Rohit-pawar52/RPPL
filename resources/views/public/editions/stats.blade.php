@extends('layouts.public')

@section('title', __('home.boards.'.$board).' · '.$edition->name.' · '.$branding->shortName)

@php
    // Columns per board: [label, value for a row]. The first one is the
    // board's own number (the big figure); the rest are the smaller figures.
    $number = fn ($value) => number_format((float) $value, 2);
    $columns = match ($board) {
        'runs' => [
            [__('home.stats.runs'), fn ($r) => $r['value']],
            [__('home.stats.innings'), fn ($r) => $r['innings']],
            [__('home.stats.balls'), fn ($r) => $r['balls']],
            [__('home.stats.strike_rate'), fn ($r) => $number($r['strike_rate'])],
            [__('home.stats.fours'), fn ($r) => $r['fours']],
            [__('home.stats.sixes'), fn ($r) => $r['sixes']],
        ],
        'wickets' => [
            [__('home.stats.wickets'), fn ($r) => $r['value']],
            [__('home.stats.innings'), fn ($r) => $r['innings']],
            [__('home.stats.overs'), fn ($r) => $r['overs']],
            [__('home.stats.runs_conceded'), fn ($r) => $r['runs_conceded']],
            [__('home.stats.economy'), fn ($r) => $number($r['economy'])],
            [__('home.stats.best'), fn ($r) => $r['best_bowling'] ?? '-'],
        ],
        'highest-score' => [
            [__('home.stats.runs'), fn ($r) => $r['value'].($r['not_out'] ? '*' : '')],
            [__('home.stats.balls'), fn ($r) => $r['balls']],
        ],
        'sixes' => [
            [__('home.stats.sixes'), fn ($r) => $r['value']],
            [__('home.stats.innings'), fn ($r) => $r['innings']],
            [__('home.stats.runs'), fn ($r) => $r['runs']],
        ],
        default => [ // thirties / fifties / hundreds
            [__('home.stats.count'), fn ($r) => $r['value']],
            [__('home.stats.innings'), fn ($r) => $r['innings']],
            [__('home.stats.runs'), fn ($r) => $r['runs']],
        ],
    };

    $mainLabel = $columns[0][0];
    $extraColumns = array_slice($columns, 1);
    $boardRows = collect($rows)->map(fn ($row) => [
        'player' => $row['player'],
        'main' => $columns[0][1]($row),
        'extras' => array_map(fn ($column) => $column[1]($row), $extraColumns),
    ])->all();

    $note = match ($board) {
        'thirties' => __('home.stats.note_thirties'),
        'fifties' => __('home.stats.note_fifties'),
        'hundreds' => __('home.stats.note_hundreds'),
        'highest-score' => __('home.stats.note_highest'),
        default => null,
    };
@endphp

@section('content')
    <x-public.page-header
        :title="__('home.boards.'.$board)"
        :subtitle="__('home.stats.top_count', ['count' => $limit, 'edition' => $edition->name])"
        :back="route('public.editions.show', $edition)"
        :back-label="$edition->name"
    />

    {{-- The other boards of the same season, one tap away. --}}
    <nav class="mb-4" aria-label="{{ __('home.stats.title') }}">
        <div class="mx-chips">
            @foreach(\App\Services\Statistics\PlayerStatisticsService::HIGHLIGHT_BOARDS as $slug)
                <a
                    href="{{ route('public.editions.stats', [$edition, $slug]) }}"
                    @class(['mx-chip', 'is-active' => $slug === $board])
                    @if($slug === $board) aria-current="page" @endif
                >{{ __('home.boards.'.$slug) }}</a>
            @endforeach
        </div>
    </nav>

    <x-public.card flush>
        @include('shared.statistics._board', [
            'rows' => $boardRows,
            'mainLabel' => $mainLabel,
            'extraLabels' => array_map(fn ($column) => $column[0], $extraColumns),
            'emptyText' => __('home.stats.empty'),
            'playerLabel' => __('home.stats.player'),
        ])
    </x-public.card>

    @if($note)
        <p class="pub-meta mt-3">{{ $note }}</p>
    @endif
@endsection
