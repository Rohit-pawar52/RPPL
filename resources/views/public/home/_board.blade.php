{{--
    One season-summary board card (e.g. "Most runs"): the top few ranked
    rows and a "View more" link to the full list. Expects $edition, $slug
    (a PlayerStatisticsService::HIGHLIGHT_BOARDS slug), $rows and $unit.
--}}
<x-public.card :title="__('home.boards.'.$slug)" :href="route('public.editions.stats', [$edition, $slug])" :link-label="__('home.summary.view_more').' →'" flush>
    <ol class="divide-y divide-line">
        @foreach($rows as $row)
            <li class="flex items-center gap-3 px-4 py-2 text-[13px]">
                <span class="w-4 shrink-0 text-center text-xs tabular-nums text-slate-400">{{ $loop->iteration }}</span>
                <span class="min-w-0 flex-1 truncate font-medium text-slate-800">{{ $row['player']->name }}</span>
                <span class="shrink-0 whitespace-nowrap">
                    <span class="font-bold tabular-nums text-slate-900">{{ $row['value'] }}{{ ($row['not_out'] ?? false) ? '*' : '' }}</span>
                    <span class="text-[11px] text-slate-400">{{ $unit }}</span>
                </span>
            </li>
        @endforeach
    </ol>
</x-public.card>
