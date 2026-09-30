{{--
    Shared between admin and public Edition pages — purely presentational.

    Expects: $records (from PlayerStatisticsService::getEditionRecords())
--}}
<div class="rounded-lg border border-neutral-200 bg-white p-4">
    <h3 class="mb-3 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">{{ __('directory.records.title') }}</h3>
    <dl class="grid grid-cols-1 gap-3 text-xs sm:grid-cols-3">
        <div>
            <dt class="text-neutral-400">{{ __('directory.records.highest_score') }}</dt>
            <dd class="mt-0.5 font-medium text-neutral-800">
                @if($records['highestScore'])
                    {{ $records['highestScore']['runs'] }}{{ $records['highestScore']['notOut'] ? '*' : '' }}
                    &mdash; {{ $records['highestScore']['player']->name }}
                @else
                    -
                @endif
            </dd>
        </div>
        <div>
            <dt class="text-neutral-400">{{ __('directory.records.best_bowling') }}</dt>
            <dd class="mt-0.5 font-medium text-neutral-800">
                @if($records['bestBowling'])
                    {{ $records['bestBowling']['wickets'] }}/{{ $records['bestBowling']['runsConceded'] }}
                    &mdash; {{ $records['bestBowling']['player']->name }}
                @else
                    -
                @endif
            </dd>
        </div>
        <div>
            <dt class="text-neutral-400">{{ __('directory.records.most_sixes') }}</dt>
            <dd class="mt-0.5 font-medium text-neutral-800">
                @if($records['mostSixes'])
                    {{ $records['mostSixes']['sixes'] }} &mdash; {{ $records['mostSixes']['player']->name }}
                @else
                    -
                @endif
            </dd>
        </div>
    </dl>
</div>
