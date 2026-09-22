@extends('layouts.public')

@section('title', $player->name.' · '.$branding->shortName)

@section('content')
    <a href="{{ route('public.players.index') }}" class="mb-4 inline-block text-xs text-neutral-500 hover:text-neutral-700">
        &larr; Back to players
    </a>

    <div class="rounded-lg border border-neutral-200 bg-white p-4">
        <div class="flex items-center gap-4">
            <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-full border border-neutral-200 bg-neutral-50 text-neutral-300">
                @if($player->photo_path)
                    <img
                        src="{{ Illuminate\Support\Facades\Storage::url($player->photo_path) }}"
                        alt="{{ $player->name }}"
                        class="h-full w-full object-cover"
                    />
                @else
                    <x-icon name="user" class="h-6 w-6" />
                @endif
            </div>

            <div>
                <h1 class="text-base font-semibold text-neutral-900">{{ $player->name }}</h1>
                <p class="text-xs text-neutral-500">
                    {{ $currentTeam?->name ?? 'No current team' }}
                    @if($player->primary_role)
                        &middot; {{ str_replace('_', ' ', ucfirst($player->primary_role)) }}
                    @endif
                </p>
            </div>
        </div>
    </div>

    <div class="mt-4 flex flex-wrap items-center gap-2">
        <a
            href="{{ route('public.players.show', $player) }}"
            class="rounded-md border px-2.5 py-1 text-xs font-medium {{ ! $selectedEdition ? 'theme-primary-border theme-primary-soft-bg theme-primary-text' : 'border-neutral-200 text-neutral-600 hover:bg-neutral-50' }}"
        >
            Career / All Editions
        </a>
        @foreach($player->playerRegistrations as $registration)
            <a
                href="{{ route('public.players.show', ['player' => $player, 'edition_id' => $registration->edition_id]) }}"
                class="rounded-md border px-2.5 py-1 text-xs font-medium {{ $selectedEdition?->id === $registration->edition_id ? 'theme-primary-border theme-primary-soft-bg theme-primary-text' : 'border-neutral-200 text-neutral-600 hover:bg-neutral-50' }}"
            >
                {{ $registration->edition->name }}
            </a>
        @endforeach
    </div>

    <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div class="rounded-lg border border-neutral-200 bg-white p-4">
            <h3 class="mb-3 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">Batting</h3>
            <dl class="grid grid-cols-3 gap-3 text-xs sm:grid-cols-4">
                <div>
                    <dt class="text-neutral-400">Matches</dt>
                    <dd class="mt-0.5 font-medium text-neutral-800">{{ $stats['matches_played'] }}</dd>
                </div>
                <div>
                    <dt class="text-neutral-400">Innings</dt>
                    <dd class="mt-0.5 font-medium text-neutral-800">{{ $stats['batting']['innings_batted'] }}</dd>
                </div>
                <div>
                    <dt class="text-neutral-400">Runs</dt>
                    <dd class="mt-0.5 font-medium text-neutral-800">{{ $stats['batting']['runs'] }}</dd>
                </div>
                <div>
                    <dt class="text-neutral-400">Highest</dt>
                    <dd class="mt-0.5 font-medium text-neutral-800">
                        @if($stats['batting']['highest_score'] !== null)
                            {{ $stats['batting']['highest_score'] }}{{ $stats['batting']['highest_score_not_out'] ? '*' : '' }}
                        @else
                            -
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-neutral-400">Average</dt>
                    <dd class="mt-0.5 font-medium text-neutral-800">
                        {{ $stats['batting']['batting_average'] !== null ? number_format($stats['batting']['batting_average'], 2) : '-' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-neutral-400">Strike Rate</dt>
                    <dd class="mt-0.5 font-medium text-neutral-800">{{ number_format($stats['batting']['strike_rate'], 2) }}</dd>
                </div>
                <div>
                    <dt class="text-neutral-400">4s</dt>
                    <dd class="mt-0.5 font-medium text-neutral-800">{{ $stats['batting']['fours'] }}</dd>
                </div>
                <div>
                    <dt class="text-neutral-400">6s</dt>
                    <dd class="mt-0.5 font-medium text-neutral-800">{{ $stats['batting']['sixes'] }}</dd>
                </div>
            </dl>
        </div>

        <div class="rounded-lg border border-neutral-200 bg-white p-4">
            <h3 class="mb-3 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">Bowling</h3>
            <dl class="grid grid-cols-3 gap-3 text-xs sm:grid-cols-4">
                <div>
                    <dt class="text-neutral-400">Overs</dt>
                    <dd class="mt-0.5 font-medium text-neutral-800">{{ $stats['bowling']['overs'] }}</dd>
                </div>
                <div>
                    <dt class="text-neutral-400">Runs</dt>
                    <dd class="mt-0.5 font-medium text-neutral-800">{{ $stats['bowling']['runs_conceded'] }}</dd>
                </div>
                <div>
                    <dt class="text-neutral-400">Wickets</dt>
                    <dd class="mt-0.5 font-medium text-neutral-800">{{ $stats['bowling']['wickets'] }}</dd>
                </div>
                <div>
                    <dt class="text-neutral-400">Best</dt>
                    <dd class="mt-0.5 font-medium text-neutral-800">{{ $stats['bowling']['best_bowling'] ?? '-' }}</dd>
                </div>
                <div>
                    <dt class="text-neutral-400">Average</dt>
                    <dd class="mt-0.5 font-medium text-neutral-800">
                        {{ $stats['bowling']['bowling_average'] !== null ? number_format($stats['bowling']['bowling_average'], 2) : '-' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-neutral-400">Economy</dt>
                    <dd class="mt-0.5 font-medium text-neutral-800">{{ number_format($stats['bowling']['economy'], 2) }}</dd>
                </div>
            </dl>
        </div>
    </div>

    <div class="mt-4 rounded-lg border border-neutral-200 bg-white p-4">
        <h3 class="mb-3 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">Match History</h3>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[560px] text-left text-[13px]">
                <thead class="border-b border-neutral-200 text-[11px] uppercase tracking-wide text-neutral-400">
                    <tr>
                        <th class="px-2 py-1.5 font-medium">Date</th>
                        <th class="px-2 py-1.5 font-medium">Match</th>
                        <th class="px-2 py-1.5 font-medium">Batting</th>
                        <th class="px-2 py-1.5 font-medium">Bowling</th>
                        <th class="hidden px-2 py-1.5 font-medium md:table-cell">Result</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse($matchHistory as $row)
                        @php $match = $row['match']; @endphp
                        <tr>
                            <td class="whitespace-nowrap px-2 py-1.5 text-neutral-500">{{ display_datetime($match->scheduled_at, 'd M Y') }}</td>
                            <td class="px-2 py-1.5 text-neutral-800">
                                <a href="{{ route('public.matches.show', $match) }}" class="hover:underline">
                                    {{ $match->teamA->team->name }} vs {{ $match->teamB->team->name }}
                                </a>
                            </td>
                            <td class="px-2 py-1.5 text-neutral-700">
                                @if($row['batting'])
                                    {{ $row['batting']['runs'] }} ({{ $row['batting']['balls'] }}){{ $row['batting']['not_out'] ? '*' : '' }}
                                @else
                                    &mdash;
                                @endif
                            </td>
                            <td class="px-2 py-1.5 text-neutral-700">
                                @if($row['bowling'])
                                    {{ $row['bowling']['wickets'] }}/{{ $row['bowling']['runs_conceded'] }} ({{ $row['bowling']['overs'] }})
                                @else
                                    &mdash;
                                @endif
                            </td>
                            <td class="hidden px-2 py-1.5 text-neutral-500 md:table-cell">
                                @if($match->match_status === 'completed')
                                    {{ $match->match_result }}
                                @else
                                    <x-status-badge :status="$match->match_status" />
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-2 py-6 text-center text-neutral-400">No match history available yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            {{ $matchHistory->links() }}
        </div>
    </div>
@endsection
