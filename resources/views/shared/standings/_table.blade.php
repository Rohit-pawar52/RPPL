{{--
    Shared between admin and public Edition pages. The "ignored
    matches" integrity warning is admin-only internal-data information
    — pass $ignoredMatchesCount to show it (admin); omit it entirely on
    the public page so visitors never see implementation/debug details.

    Expects: $standings (list of rows from StandingsService::getEditionStandings()['standings'])
    Optional: $ignoredMatchesCount
--}}
<div class="rounded-lg border border-neutral-200 bg-white p-4">
    <h3 class="mb-3 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">Points Table</h3>

    @isset($ignoredMatchesCount)
        @if($ignoredMatchesCount > 0)
            <p class="mb-3 rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-700">
                {{ $ignoredMatchesCount }} finalized match{{ $ignoredMatchesCount === 1 ? '' : 'es' }}
                excluded because {{ $ignoredMatchesCount === 1 ? 'its' : 'their' }} result data is inconsistent.
            </p>
        @endif
    @endisset

    <div class="overflow-x-auto">
        <table class="w-full min-w-[480px] text-left text-[13px]">
            <thead class="border-b border-neutral-200 text-[11px] uppercase tracking-wide text-neutral-400">
                <tr>
                    <th class="px-2 py-1.5 font-medium">#</th>
                    <th class="px-2 py-1.5 font-medium">Team</th>
                    <th class="px-2 py-1.5 text-right font-medium">P</th>
                    <th class="hidden px-2 py-1.5 text-right font-medium sm:table-cell">W</th>
                    <th class="hidden px-2 py-1.5 text-right font-medium sm:table-cell">L</th>
                    <th class="hidden px-2 py-1.5 text-right font-medium sm:table-cell">T</th>
                    <th class="hidden px-2 py-1.5 text-right font-medium sm:table-cell">NR</th>
                    <th class="px-2 py-1.5 text-right font-medium">Pts</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100">
                @forelse($standings as $row)
                    <tr>
                        <td class="px-2 py-1.5 text-neutral-500">{{ $row['position'] }}</td>
                        <td class="px-2 py-1.5 font-medium text-neutral-800">{{ $row['edition_team']->team->name }}</td>
                        <td class="px-2 py-1.5 text-right text-neutral-700">{{ $row['played'] }}</td>
                        <td class="hidden px-2 py-1.5 text-right text-neutral-600 sm:table-cell">{{ $row['won'] }}</td>
                        <td class="hidden px-2 py-1.5 text-right text-neutral-600 sm:table-cell">{{ $row['lost'] }}</td>
                        <td class="hidden px-2 py-1.5 text-right text-neutral-600 sm:table-cell">{{ $row['tied'] }}</td>
                        <td class="hidden px-2 py-1.5 text-right text-neutral-600 sm:table-cell">{{ $row['no_result'] }}</td>
                        <td class="px-2 py-1.5 text-right font-semibold text-neutral-900">{{ $row['points'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-2 py-4 text-center text-neutral-400">No teams in this edition yet.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
