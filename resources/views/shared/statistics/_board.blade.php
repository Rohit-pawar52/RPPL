{{--
    A ranked player board: medal-style top 3, a card-like row on a phone and a
    column table from sm up. One markup for both, so nothing is rendered twice.
    Shared by the season leaderboard (admin + public) and the full top-20 pages.

    Expects:
      $rows         list of ['player' => Player, 'main' => value, 'extras' => [value, ...]]
                    ('extras' lines up with $extraLabels)
      $mainLabel    heading of the big number (Runs, Wkts, ...)
      $extraLabels  list of headings of the smaller numbers
      $emptyText    shown when there are no rows
      $playerLabel  heading of the player column
--}}
@php
    $extraLabels = $extraLabels ?? [];
    $playerLabel = $playerLabel ?? __('directory.leaderboard.player');
@endphp

@if(count($rows) === 0)
    <x-public.empty icon="chart-bar">{{ $emptyText }}</x-public.empty>
@else
    <div class="mx-board" style="--extras: {{ count($extraLabels) }}">
        <div class="mx-board-head" aria-hidden="true">
            <span>#</span>
            <span>{{ $playerLabel }}</span>
            <span>{{ $mainLabel }}</span>
            @foreach($extraLabels as $label)
                <span>{{ $label }}</span>
            @endforeach
        </div>

        <ol class="divide-y divide-line">
            @foreach($rows as $row)
                @php $rank = $loop->iteration; @endphp
                <li class="mx-board-row">
                    <span @class(['mx-rank', 'mx-rank-'.$rank => $rank <= 3])>{{ $rank }}</span>

                    <div class="mx-board-player">
                        <span class="mx-avatar">
                            <x-media-image :path="$row['player']->photo_path" kind="user" alt="" class="h-full w-full object-cover" loading="lazy" />
                        </span>
                        <div class="min-w-0">
                            <span class="mx-board-name">{{ $row['player']->name }}</span>
                            @if(count($extraLabels))
                                <p class="mx-board-sub">
                                    @foreach($row['extras'] as $i => $value)
                                        <span>{{ $extraLabels[$i] ?? '' }} <b>{{ $value }}</b></span>
                                    @endforeach
                                </p>
                            @endif
                        </div>
                    </div>

                    <span class="mx-board-main">{{ $row['main'] }}<small>{{ $mainLabel }}</small></span>

                    @foreach($row['extras'] as $value)
                        <span class="mx-board-extra">{{ $value }}</span>
                    @endforeach
                </li>
            @endforeach
        </ol>
    </div>
@endif
