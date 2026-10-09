{{--
    Points table, shared between admin and public Edition pages. The "ignored
    matches" integrity warning is admin-only internal-data information -
    pass $ignoredMatchesCount to show it (admin); omit it entirely on the
    public page so visitors never see implementation/debug details.

    Phone: the team column stays pinned while the numbers scroll; the leading
    places are tinted with the brand colour.

    Expects: $standings (list of rows from StandingsService::getEditionStandings()['standings'])
    Optional: $ignoredMatchesCount, $highlight (how many top places to tint, default 2)
--}}
@php $highlight = $highlight ?? 2; @endphp
<section id="points" class="pub-card overflow-hidden scroll-mt-32">
    <header class="flex items-center gap-2.5 border-b border-line px-4 py-3 sm:px-5">
        <span class="grid size-7 shrink-0 place-items-center rounded-lg bg-brand-soft text-brand"><x-icon name="chart-bar" class="size-4" /></span>
        <h3 class="text-[15px] font-semibold tracking-tight text-slate-900">{{ __('directory.standings.points_table') }}</h3>
    </header>

    @isset($ignoredMatchesCount)
        @if($ignoredMatchesCount > 0)
            <p class="m-4 rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-700">
                {{ $ignoredMatchesCount }} finalized match{{ $ignoredMatchesCount === 1 ? '' : 'es' }}
                excluded because {{ $ignoredMatchesCount === 1 ? 'its' : 'their' }} result data is inconsistent.
            </p>
        @endif
    @endisset

    <div class="mx-scroll">
        <table class="mx-standings">
            <thead>
                <tr>
                    <th class="mx-team-cell text-left">{{ __('directory.standings.team') }}</th>
                    <th class="text-right">{{ __('directory.standings.played') }}</th>
                    <th class="text-right">{{ __('directory.standings.won') }}</th>
                    <th class="text-right">{{ __('directory.standings.lost') }}</th>
                    <th class="text-right">{{ __('directory.standings.tied') }}</th>
                    <th class="text-right">{{ __('directory.standings.no_result') }}</th>
                    <th class="pr-3 text-right sm:pr-5">{{ __('directory.standings.points') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($standings as $row)
                    <tr @class(['is-qualifying' => $row['played'] > 0 && $row['position'] <= $highlight])>
                        <td class="mx-team-cell">
                            <span class="flex min-w-0 items-center gap-2.5">
                                <span class="mx-rankchip">{{ $row['position'] }}</span>
                                <x-mx.team-logo :team="$row['edition_team']->team" size="sm" class="max-sm:size-6" />
                                <span class="min-w-0 truncate font-semibold text-slate-900"><span class="sm:hidden">{{ $row['edition_team']->team->short_name ?: $row['edition_team']->team->name }}</span><span class="hidden sm:inline">{{ $row['edition_team']->team->name }}</span></span>
                            </span>
                        </td>
                        <td class="text-right">{{ $row['played'] }}</td>
                        <td class="is-wins text-right">{{ $row['won'] }}</td>
                        <td class="text-right">{{ $row['lost'] }}</td>
                        <td class="text-right">{{ $row['tied'] }}</td>
                        <td class="text-right">{{ $row['no_result'] }}</td>
                        <td class="is-points pr-3 text-right sm:pr-5">{{ $row['points'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="p-0!"><x-public.empty icon="chart-bar">{{ __('directory.standings.empty') }}</x-public.empty></td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
