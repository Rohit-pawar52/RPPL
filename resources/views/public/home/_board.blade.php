{{--
    One season-summary board card (e.g. "Most runs"): the top few ranked
    players and a "View more" link to the full list. The leader is called out
    with a bigger photo. Expects $edition, $slug (a PlayerStatisticsService::
    HIGHLIGHT_BOARDS slug), $rows and $unit; $class adds layout classes.
--}}
<x-public.card :title="__('home.boards.'.$slug)" :href="route('public.editions.stats', [$edition, $slug])" :link-label="__('home.summary.view_more').' →'" flush :class="$class ?? ''">
    <ol class="divide-y divide-line">
        @foreach($rows as $row)
            @php $leader = $loop->first; @endphp
            {{-- Phones show the top three; the full five from sm up. --}}
            <li @class(['items-center gap-3 px-4 text-[13px] sm:px-5', 'flex' => $loop->iteration <= 3, 'hidden sm:flex' => $loop->iteration > 3, 'bg-brand-soft/50 py-3' => $leader, 'py-2.5' => ! $leader])>
                <span @class([
                    'flex shrink-0 items-center justify-center rounded-full text-xs font-bold tabular-nums',
                    'h-6 w-6 bg-brand text-brand-fg' => $leader,
                    'h-6 w-6 text-slate-400' => ! $leader,
                ])>{{ $loop->iteration }}</span>
                @if($leader)
                    <x-media-image :path="$row['player']->photo_path" kind="user" alt="" loading="lazy" decoding="async" class="h-9 w-9 shrink-0 rounded-full bg-white object-cover ring-2 ring-white" />
                @endif
                <span @class(['min-w-0 flex-1 truncate', 'font-semibold text-slate-900' => $leader, 'font-medium text-slate-700' => ! $leader])>{{ $row['player']->name }}</span>
                <span class="shrink-0 whitespace-nowrap text-right">
                    <span @class(['font-bold tabular-nums text-slate-900', 'text-lg' => $leader, 'text-[15px]' => ! $leader])>{{ $row['value'] }}{{ ($row['not_out'] ?? false) ? '*' : '' }}</span>
                    <span class="text-[11px] text-slate-400">{{ $unit }}</span>
                </span>
            </li>
        @endforeach
    </ol>
</x-public.card>
