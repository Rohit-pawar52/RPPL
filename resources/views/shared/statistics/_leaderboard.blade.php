{{--
    Shared between admin and public Edition pages — purely presentational.

    Expects: $leaderboard (from PlayerStatisticsService::getEditionLeaderboard())
--}}
<div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
    <div class="rounded-lg border border-neutral-200 bg-white p-4">
        <h3 class="mb-3 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">{{ __('directory.leaderboard.top_run_scorers') }}</h3>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[420px] text-left text-[13px]">
                <thead class="border-b border-neutral-200 text-[11px] uppercase tracking-wide text-neutral-400">
                    <tr>
                        <th class="px-2 py-1.5 font-medium">{{ __('directory.leaderboard.player') }}</th>
                        <th class="px-2 py-1.5 text-right font-medium">{{ __('directory.leaderboard.runs') }}</th>
                        <th class="hidden px-2 py-1.5 text-right font-medium sm:table-cell">{{ __('directory.leaderboard.innings') }}</th>
                        <th class="hidden px-2 py-1.5 text-right font-medium sm:table-cell">{{ __('directory.leaderboard.average') }}</th>
                        <th class="px-2 py-1.5 text-right font-medium">{{ __('directory.leaderboard.strike_rate') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse($leaderboard['topRunScorers'] as $entry)
                        <tr>
                            <td class="px-2 py-1.5 font-medium text-neutral-800">{{ $entry['player']->name }}</td>
                            <td class="px-2 py-1.5 text-right text-neutral-800">{{ $entry['stats']['runs'] }}</td>
                            <td class="hidden px-2 py-1.5 text-right text-neutral-600 sm:table-cell">{{ $entry['stats']['innings_batted'] }}</td>
                            <td class="hidden px-2 py-1.5 text-right text-neutral-600 sm:table-cell">
                                {{ $entry['stats']['batting_average'] !== null ? number_format($entry['stats']['batting_average'], 2) : '-' }}
                            </td>
                            <td class="px-2 py-1.5 text-right text-neutral-600">{{ number_format($entry['stats']['strike_rate'], 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-2 py-4 text-center text-neutral-400">{{ __('directory.leaderboard.batting_empty') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="rounded-lg border border-neutral-200 bg-white p-4">
        <h3 class="mb-3 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">{{ __('directory.leaderboard.top_wicket_takers') }}</h3>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[420px] text-left text-[13px]">
                <thead class="border-b border-neutral-200 text-[11px] uppercase tracking-wide text-neutral-400">
                    <tr>
                        <th class="px-2 py-1.5 font-medium">{{ __('directory.leaderboard.player') }}</th>
                        <th class="px-2 py-1.5 text-right font-medium">{{ __('directory.leaderboard.wickets') }}</th>
                        <th class="hidden px-2 py-1.5 text-right font-medium sm:table-cell">{{ __('directory.leaderboard.overs') }}</th>
                        <th class="hidden px-2 py-1.5 text-right font-medium sm:table-cell">{{ __('directory.leaderboard.runs') }}</th>
                        <th class="px-2 py-1.5 text-right font-medium">{{ __('directory.leaderboard.economy') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse($leaderboard['topWicketTakers'] as $entry)
                        <tr>
                            <td class="px-2 py-1.5 font-medium text-neutral-800">{{ $entry['player']->name }}</td>
                            <td class="px-2 py-1.5 text-right text-neutral-800">{{ $entry['stats']['wickets'] }}</td>
                            <td class="hidden px-2 py-1.5 text-right text-neutral-600 sm:table-cell">{{ $entry['stats']['overs'] }}</td>
                            <td class="hidden px-2 py-1.5 text-right text-neutral-600 sm:table-cell">{{ $entry['stats']['runs_conceded'] }}</td>
                            <td class="px-2 py-1.5 text-right text-neutral-600">{{ number_format($entry['stats']['economy'], 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-2 py-4 text-center text-neutral-400">{{ __('directory.leaderboard.bowling_empty') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
