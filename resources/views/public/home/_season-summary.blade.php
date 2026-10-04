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
        'sixes' => __('home.units.sixes'),
    ];
    $filled = collect($highlights)->filter(fn ($rows) => count($rows) > 0);

    // A long table (more than about five teams) is as tall as two boards, so
    // it spans two rows with Most runs and Most wickets stacked beside it; a
    // short one shares a single row with Most runs.
    $tallTable = $standings->count() > 5;
@endphp

<section class="mt-4" aria-labelledby="season-summary-title">
    <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
        <h2 id="season-summary-title" class="pub-h2 inline-flex items-center gap-2">
            <x-icon name="trophy" class="h-4 w-4 text-green-600" />
            {{ $edition->name }} &middot; {{ __('home.summary.title') }}
        </h2>
        <x-public.status-pill :status="$edition->status" />
    </div>

    {{-- One grid: the points table takes two of the three columns on wide
         screens, the boards fill the rest and then follow three to a row. --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <x-public.card :title="__('home.summary.points_table')" :href="route('public.editions.show', $edition)" :link-label="__('home.summary.full_table').' →'" flush :class="$tallTable ? 'sm:col-span-2 lg:row-span-2' : 'sm:col-span-2'">
            <div class="pub-table-wrap">
                <table class="pub-table min-w-[320px]">
                    <thead>
                        <tr>
                            <th class="w-10">#</th>
                            <th>{{ __('matches.table.team') }}</th>
                            <th class="text-right">{{ __('matches.table.played') }}</th>
                            <th class="text-right">{{ __('matches.table.won') }}</th>
                            <th class="text-right">{{ __('matches.table.lost') }}</th>
                            <th class="text-right">{{ __('matches.table.points') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($standings as $row)
                            @php $standingTeam = $row['edition_team']?->team; @endphp
                            <tr class="{{ $row['position'] <= 2 ? '[&>td]:bg-green-50/60' : '' }}">
                                <td class="text-slate-400">{{ $row['position'] }}</td>
                                <td class="font-medium text-slate-800">
                                    @if($standingTeam)
                                        <a href="{{ route('public.teams.show', $standingTeam) }}" class="hover:underline">
                                            <span class="sm:hidden">{{ $standingTeam->short_name ?: $standingTeam->name }}</span>
                                            <span class="hidden sm:inline">{{ $standingTeam->name }}</span>
                                        </a>
                                    @endif
                                </td>
                                <td class="text-right tabular-nums">{{ $row['played'] }}</td>
                                <td class="text-right tabular-nums">{{ $row['won'] }}</td>
                                <td class="text-right tabular-nums">{{ $row['lost'] }}</td>
                                <td class="text-right font-bold tabular-nums text-slate-900">{{ $row['points'] }}</td>
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
        <p class="pub-meta mt-3">{{ __('home.summary.empty') }}</p>
    @endif
</section>
