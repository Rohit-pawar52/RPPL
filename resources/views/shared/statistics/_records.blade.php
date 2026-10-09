{{--
    Shared between admin and public Edition pages - purely presentational.

    Expects: $records (from PlayerStatisticsService::getEditionRecords())
--}}
@php
    $recordTiles = [
        [
            'icon' => 'star',
            'label' => __('directory.records.highest_score'),
            'value' => $records['highestScore'] ? $records['highestScore']['runs'].($records['highestScore']['notOut'] ? '*' : '') : null,
            'player' => $records['highestScore']['player']->name ?? null,
        ],
        [
            'icon' => 'glove',
            'label' => __('directory.records.best_bowling'),
            'value' => $records['bestBowling'] ? $records['bestBowling']['wickets'].'/'.$records['bestBowling']['runsConceded'] : null,
            'player' => $records['bestBowling']['player']->name ?? null,
        ],
        [
            'icon' => 'trophy',
            'label' => __('directory.records.most_sixes'),
            'value' => $records['mostSixes'] ? $records['mostSixes']['sixes'] : null,
            'player' => $records['mostSixes']['player']->name ?? null,
        ],
    ];
@endphp
<section class="pub-card overflow-hidden">
    <header class="border-b border-line px-4 py-3 sm:px-5">
        <h3 class="text-[15px] font-semibold tracking-tight text-slate-900">{{ __('directory.records.title') }}</h3>
    </header>
    <dl class="grid grid-cols-1 gap-3 p-4 sm:grid-cols-3 sm:p-5">
        @foreach($recordTiles as $tile)
            <div class="flex items-center gap-3 rounded-xl bg-slate-50 p-3.5 ring-1 ring-inset ring-line">
                <span class="mx-fact-icon size-10"><x-icon :name="$tile['icon']" class="size-5" /></span>
                <div class="min-w-0">
                    <dt class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ $tile['label'] }}</dt>
                    <dd class="mt-0.5">
                        @if($tile['value'] !== null)
                            <span class="text-xl font-bold tabular-nums text-slate-900">{{ $tile['value'] }}</span>
                            <span class="block truncate text-xs text-slate-500">{{ $tile['player'] }}</span>
                        @else
                            <span class="text-slate-400">-</span>
                        @endif
                    </dd>
                </div>
            </div>
        @endforeach
    </dl>
</section>
