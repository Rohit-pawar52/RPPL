@extends('layouts.public')

@section('title', __('home.boards.'.$board).' · '.$edition->name.' · '.$branding->shortName)

@php
    // Columns per board: [label, value for a row, extra classes for the cell].
    // The first value column is the board's own number and is shown bold;
    // the rest hide on the smallest screens.
    $number = fn ($value) => number_format((float) $value, 2);
    $columns = match ($board) {
        'runs' => [
            [__('home.stats.runs'), fn ($r) => $r['value'], 'bold'],
            [__('home.stats.innings'), fn ($r) => $r['innings'], 'wide'],
            [__('home.stats.balls'), fn ($r) => $r['balls'], 'wide'],
            [__('home.stats.strike_rate'), fn ($r) => $number($r['strike_rate']), ''],
            [__('home.stats.fours'), fn ($r) => $r['fours'], 'wide'],
            [__('home.stats.sixes'), fn ($r) => $r['sixes'], 'wide'],
        ],
        'wickets' => [
            [__('home.stats.wickets'), fn ($r) => $r['value'], 'bold'],
            [__('home.stats.innings'), fn ($r) => $r['innings'], 'wide'],
            [__('home.stats.overs'), fn ($r) => $r['overs'], 'wide'],
            [__('home.stats.runs_conceded'), fn ($r) => $r['runs_conceded'], 'wide'],
            [__('home.stats.economy'), fn ($r) => $number($r['economy']), ''],
            [__('home.stats.best'), fn ($r) => $r['best_bowling'] ?? '-', 'wide'],
        ],
        'highest-score' => [
            [__('home.stats.runs'), fn ($r) => $r['value'].($r['not_out'] ? '*' : ''), 'bold'],
            [__('home.stats.balls'), fn ($r) => $r['balls'], ''],
        ],
        'sixes' => [
            [__('home.stats.sixes'), fn ($r) => $r['value'], 'bold'],
            [__('home.stats.innings'), fn ($r) => $r['innings'], 'wide'],
            [__('home.stats.runs'), fn ($r) => $r['runs'], ''],
        ],
        default => [ // thirties / fifties
            [__('home.stats.count'), fn ($r) => $r['value'], 'bold'],
            [__('home.stats.innings'), fn ($r) => $r['innings'], 'wide'],
            [__('home.stats.runs'), fn ($r) => $r['runs'], ''],
        ],
    };

    $note = match ($board) {
        'thirties' => __('home.stats.note_thirties'),
        'fifties' => __('home.stats.note_fifties'),
        'highest-score' => __('home.stats.note_highest'),
        default => null,
    };
@endphp

@section('content')
    <x-public.page-header
        :title="__('home.boards.'.$board)"
        :subtitle="__('home.stats.top_count', ['count' => $limit, 'edition' => $edition->name])"
        :back="route('public.home')"
        :back-label="__('home.stats.back')"
    />

    {{-- The other boards of the same season, one tap away. --}}
    <nav class="pub-tabs mb-4" aria-label="{{ __('home.stats.title') }}">
        @foreach(\App\Services\Statistics\PlayerStatisticsService::HIGHLIGHT_BOARDS as $slug)
            <a
                href="{{ route('public.editions.stats', [$edition, $slug]) }}"
                class="pub-tab {{ $slug === $board ? 'pub-tab-active' : '' }}"
                @if($slug === $board) aria-current="page" @endif
            >{{ __('home.boards.'.$slug) }}</a>
        @endforeach
    </nav>

    <x-public.card flush>
        <div class="pub-table-wrap">
            <table class="pub-table min-w-[300px]">
                <thead>
                    <tr>
                        <th class="w-10">{{ __('home.stats.rank') }}</th>
                        <th>{{ __('home.stats.player') }}</th>
                        @foreach($columns as [$label, , $style])
                            <th class="text-right {{ $style === 'wide' ? 'hidden sm:table-cell' : '' }}">{{ $label }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td class="tabular-nums text-slate-400">{{ $loop->iteration }}</td>
                            <td class="font-semibold text-slate-900">{{ $row['player']->name }}</td>
                            @foreach($columns as [, $value, $style])
                                <td class="text-right tabular-nums {{ $style === 'bold' ? 'font-bold text-slate-900' : '' }} {{ $style === 'wide' ? 'hidden sm:table-cell' : '' }}">{{ $value($row) }}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ 2 + count($columns) }}"><x-public.empty>{{ __('home.stats.empty') }}</x-public.empty></td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-public.card>

    @if($note)
        <p class="pub-meta mt-2">{{ $note }}</p>
    @endif
@endsection
