{{--
    Current season summary: the points table and the top few of each stats
    board, each board linking to its full top-20 page. Expects $edition,
    $standings (every team) and $highlights (HIGHLIGHT_BOARDS => top rows).
    A board with nobody on it yet is left out; with none at all one short
    note stands in for them.
--}}
@php
    $boardUnit = [
        'runs' => __('home.units.runs'),
        'wickets' => __('home.units.wickets'),
        'highest-score' => __('home.units.runs'),
        'thirties' => __('home.units.innings_30'),
        'fifties' => __('home.units.innings_50'),
        'hundreds' => __('home.units.innings_100'),
        'sixes' => __('home.units.sixes'),
    ];
    $filled = collect($highlights)->filter(fn ($rows) => count($rows) > 0);

    // A long table (more than about five teams) is as tall as two boards, so
    // it spans two rows with Most runs and Most wickets stacked beside it; a
    // short one shares a single row with Most runs.
    $tallTable = $standings->count() > 5;
@endphp

<section aria-labelledby="season-summary-title">
    <div class="mb-3 flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
        <h2 id="season-summary-title" class="flex items-center gap-2 text-lg font-semibold tracking-tight text-slate-900">
            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-soft text-brand"><x-icon name="trophy" class="h-[18px] w-[18px]" /></span>
            <span>{{ $edition->name }} &middot; {{ __('home.summary.title') }}</span>
        </h2>
        <a href="{{ route('public.editions.show', $edition) }}" class="inline-flex min-h-8 items-center gap-1 text-xs font-semibold text-link transition-colors hover:text-link-hover">
            {{ __('home.summary.full_table') }}
            <x-icon name="arrow-right" class="h-3.5 w-3.5" />
        </a>
    </div>

    {{-- One grid: the points table takes two of the three columns on wide
         screens, the boards fill the rest and then follow three to a row. --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <x-public.card :title="__('home.summary.points_table')" :href="route('public.editions.show', $edition)" :link-label="__('home.summary.full_table').' →'" flush :class="$tallTable ? 'sm:col-span-2 lg:row-span-2' : 'sm:col-span-2'">
            <div class="pub-table-wrap">
                <table class="pub-table min-w-[320px]">
                    <thead>
                        <tr>
                            <th class="w-10 !pl-4 sm:!pl-5">#</th>
                            <th>{{ __('matches.table.team') }}</th>
                            <th class="text-right">{{ __('matches.table.played') }}</th>
                            <th class="text-right">{{ __('matches.table.won') }}</th>
                            <th class="text-right">{{ __('matches.table.lost') }}</th>
                            <th class="!pr-4 text-right sm:!pr-5">{{ __('matches.table.points') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($standings as $row)
                            @php $standingTeam = $row['edition_team']?->team; @endphp
                            <tr class="{{ $row['position'] <= 2 ? '[&>td]:bg-brand-soft/50' : '' }}">
                                <td class="!pl-4 tabular-nums text-slate-400 sm:!pl-5">{{ $row['position'] }}</td>
                                <td class="font-medium text-slate-800">
                                    @if($standingTeam)
                                        <a href="{{ route('public.teams.show', $standingTeam) }}" class="flex min-w-0 items-center gap-2.5 hover:underline">
                                            <x-public.team-logo :team="$standingTeam" class="size-7" />
                                            <span class="min-w-0 truncate">
                                                <span class="sm:hidden">{{ $standingTeam->short_name ?: $standingTeam->name }}</span>
                                                <span class="hidden sm:inline">{{ $standingTeam->name }}</span>
                                            </span>
                                        </a>
                                    @endif
                                </td>
                                <td class="text-right tabular-nums">{{ $row['played'] }}</td>
                                <td class="text-right tabular-nums">{{ $row['won'] }}</td>
                                <td class="text-right tabular-nums">{{ $row['lost'] }}</td>
                                <td class="!pr-4 text-right text-[15px] font-bold tabular-nums text-slate-900 sm:!pr-5">{{ $row['points'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="pub-empty">{{ __('matches.home.standings_empty') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-public.card>

        @foreach($filled as $slug => $rows)
            @include('public.home._board', ['edition' => $edition, 'slug' => $slug, 'rows' => $rows, 'unit' => $boardUnit[$slug]])
        @endforeach
    </div>

    @if($filled->isEmpty())
        <p class="mt-3 flex items-center gap-2 text-xs text-slate-500">
            <x-icon name="chart-bar" class="h-4 w-4 shrink-0 text-slate-400" />
            {{ __('home.summary.empty') }}
        </p>
    @endif
</section>
