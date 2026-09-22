@extends('layouts.public')

@section('title', $branding->shortName.' · '.$branding->applicationName)

@section('content')
    <div class="rounded-lg border border-neutral-200 bg-white p-4">
        <h1 class="text-base font-semibold text-neutral-900">{{ $branding->applicationName }}</h1>
        <p class="mt-1 text-xs {{ $branding->tagline ? 'theme-secondary-text' : 'text-neutral-500' }}">
            {{ $branding->tagline ?: 'Local cricket tournament scores, fixtures, and standings.' }}
        </p>
    </div>

    @if(! $edition)
        <div class="mt-4 rounded-lg border border-neutral-200 bg-white p-6 text-center text-xs text-neutral-400">
            No tournament editions available yet.
        </div>
    @else
        <div class="mt-4 rounded-lg border border-neutral-200 bg-white p-4">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-sm font-semibold text-neutral-900">{{ $edition->name }}</h2>
                <x-status-badge :status="$edition->status" />
            </div>
            <a href="{{ route('public.editions.show', $edition) }}" class="mt-1 inline-block text-xs theme-link hover:underline">
                View edition details &rarr;
            </a>
        </div>

        <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
            <div class="rounded-lg border border-neutral-200 bg-white p-4">
                <h3 class="mb-3 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">Upcoming &amp; Live Matches</h3>
                @forelse($upcomingMatches as $match)
                    @include('public.matches._list-row', ['match' => $match])
                @empty
                    <p class="py-4 text-center text-xs text-neutral-400">No matches scheduled yet.</p>
                @endforelse
            </div>

            <div class="rounded-lg border border-neutral-200 bg-white p-4">
                <h3 class="mb-3 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">Recent Results</h3>
                @forelse($recentMatches as $match)
                    @include('public.matches._list-row', ['match' => $match])
                @empty
                    <p class="py-4 text-center text-xs text-neutral-400">No completed matches yet.</p>
                @endforelse
            </div>
        </div>

        <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
            <div class="rounded-lg border border-neutral-200 bg-white p-4">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-[11px] font-semibold uppercase tracking-wide text-neutral-400">Points Table</h3>
                    <a href="{{ route('public.editions.show', $edition) }}" class="text-xs theme-link hover:underline">Full table &rarr;</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[320px] text-left text-[13px]">
                        <thead class="border-b border-neutral-200 text-[11px] uppercase tracking-wide text-neutral-400">
                            <tr>
                                <th class="px-2 py-1.5 font-medium">#</th>
                                <th class="px-2 py-1.5 font-medium">Team</th>
                                <th class="px-2 py-1.5 text-right font-medium">P</th>
                                <th class="px-2 py-1.5 text-right font-medium">Pts</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-100">
                            @forelse($standings as $row)
                                <tr>
                                    <td class="px-2 py-1.5 text-neutral-500">{{ $row['position'] }}</td>
                                    <td class="px-2 py-1.5 font-medium text-neutral-800">{{ $row['edition_team']->team->name }}</td>
                                    <td class="px-2 py-1.5 text-right text-neutral-700">{{ $row['played'] }}</td>
                                    <td class="px-2 py-1.5 text-right font-semibold text-neutral-900">{{ $row['points'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-2 py-4 text-center text-neutral-400">No teams yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-lg border border-neutral-200 bg-white p-4">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-[11px] font-semibold uppercase tracking-wide text-neutral-400">Top Performers</h3>
                    <a href="{{ route('public.editions.show', $edition) }}" class="text-xs theme-link hover:underline">More &rarr;</a>
                </div>

                <p class="mb-1 text-[11px] font-medium text-neutral-500">Most Runs</p>
                @forelse($topRunScorers as $entry)
                    <div class="flex items-center justify-between border-b border-neutral-100 py-1.5 text-[13px] last:border-b-0">
                        <span class="text-neutral-800">{{ $entry['player']->name }}</span>
                        <span class="font-medium text-neutral-900">{{ $entry['stats']['runs'] }}</span>
                    </div>
                @empty
                    <p class="py-2 text-xs text-neutral-400">Player statistics will appear once scoring begins.</p>
                @endforelse

                <p class="mb-1 mt-3 text-[11px] font-medium text-neutral-500">Most Wickets</p>
                @forelse($topWicketTakers as $entry)
                    <div class="flex items-center justify-between border-b border-neutral-100 py-1.5 text-[13px] last:border-b-0">
                        <span class="text-neutral-800">{{ $entry['player']->name }}</span>
                        <span class="font-medium text-neutral-900">{{ $entry['stats']['wickets'] }}</span>
                    </div>
                @empty
                    <p class="py-2 text-xs text-neutral-400">Player statistics will appear once scoring begins.</p>
                @endforelse
            </div>
        </div>
    @endif
@endsection
