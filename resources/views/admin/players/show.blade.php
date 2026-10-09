@extends('layouts.admin')

@section('title', 'Player Details')

@section('content')
    @php
        $label = fn (?string $value) => $value ? ucwords(str_replace('_', ' ', $value)) : '—';
        $batting = $stats['batting'];
        $bowling = $stats['bowling'];
    @endphp

    <x-crud.back :href="route('admin.players.index')">Players</x-crud.back>

    <div class="space-y-4 lg:space-y-5">
        <x-crud.profile
            :title="$player->name"
            :path="$player->photo_path"
            kind="user"
            :status="$player->is_active ? 'active' : 'inactive'"
            :subtitle="collect([$player->primary_role ? $label($player->primary_role) : null, $player->batting_style ? $label($player->batting_style).' bat' : null, $player->bowling_style ? $label($player->bowling_style).' bowl' : null])->filter()->implode(' · ') ?: null"
        >
            <span class="inline-flex items-center gap-1.5"><x-crud.glyph name="mail" class="h-3.5 w-3.5" /> {{ $player->email ?: 'No email' }}</span>
            <span class="inline-flex items-center gap-1.5"><x-crud.glyph name="phone" class="h-3.5 w-3.5" /> {{ $player->phone ?: 'No phone' }}</span>

            <x-slot:actions>
                <x-admin.button :href="route('admin.players.edit', $player)" icon="pencil">Edit</x-admin.button>
            </x-slot:actions>
        </x-crud.profile>

        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_20rem] lg:gap-5">
            <x-admin.card title="Profile">
                <dl class="crud-facts">
                    <x-crud.fact label="Date of birth">{{ $player->date_of_birth?->format('d M Y') ?? '—' }}</x-crud.fact>
                    <x-crud.fact label="Primary role">{{ $label($player->primary_role) }}</x-crud.fact>
                    <x-crud.fact label="Batting style">{{ $label($player->batting_style) }}</x-crud.fact>
                    <x-crud.fact label="Bowling style">{{ $label($player->bowling_style) }}</x-crud.fact>
                </dl>
            </x-admin.card>

            <x-admin.card title="Tournament History">
                @forelse($player->playerRegistrations as $registration)
                    <div class="crud-list-row">
                        <span class="min-w-0 truncate font-medium text-slate-800">{{ $registration->edition->name }}</span>
                        <x-status-badge :status="$registration->payment_status" />
                    </div>
                @empty
                    <p class="text-xs text-slate-400">No edition registrations yet.</p>
                @endforelse
                <p class="mt-3 border-t border-line pt-3 text-xs text-slate-500">
                    <span class="font-semibold tabular-nums text-slate-800">{{ $player->player_registrations_count }}</span> {{ \Illuminate\Support\Str::plural('registration', $player->player_registrations_count) }} in total
                </p>
            </x-admin.card>
        </div>

        {{-- Stats: all editions together, or one edition. --}}
        <div>
            <div class="crud-chips">
                <x-crud.chip :href="route('admin.players.show', $player)" :active="! $selectedEdition">Career / All Editions</x-crud.chip>
                @foreach($player->playerRegistrations as $registration)
                    <x-crud.chip :href="route('admin.players.show', ['player' => $player, 'edition_id' => $registration->edition_id])" :active="$selectedEdition?->id === $registration->edition_id">
                        {{ $registration->edition->name }}
                    </x-crud.chip>
                @endforeach
            </div>

            <div class="grid gap-4 lg:grid-cols-2 lg:gap-5">
                <x-admin.card title="Batting">
                    <dl class="crud-facts !grid-cols-3 sm:!grid-cols-4">
                        <x-crud.fact big label="Matches">{{ $stats['matches_played'] }}</x-crud.fact>
                        <x-crud.fact big label="Innings">{{ $batting['innings_batted'] }}</x-crud.fact>
                        <x-crud.fact big label="Runs">{{ $batting['runs'] }}</x-crud.fact>
                        <x-crud.fact big label="Highest">
                            @if($batting['highest_score'] !== null)
                                {{ $batting['highest_score'] }}{{ $batting['highest_score_not_out'] ? '*' : '' }}
                            @else
                                -
                            @endif
                        </x-crud.fact>
                        <x-crud.fact big label="Average">{{ $batting['batting_average'] !== null ? number_format($batting['batting_average'], 2) : '-' }}</x-crud.fact>
                        <x-crud.fact big label="Strike Rate">{{ number_format($batting['strike_rate'], 2) }}</x-crud.fact>
                        <x-crud.fact big label="4s">{{ $batting['fours'] }}</x-crud.fact>
                        <x-crud.fact big label="6s">{{ $batting['sixes'] }}</x-crud.fact>
                    </dl>
                </x-admin.card>

                <x-admin.card title="Bowling">
                    <dl class="crud-facts !grid-cols-3 sm:!grid-cols-4">
                        <x-crud.fact big label="Overs">{{ $bowling['overs'] }}</x-crud.fact>
                        <x-crud.fact big label="Runs">{{ $bowling['runs_conceded'] }}</x-crud.fact>
                        <x-crud.fact big label="Wickets">{{ $bowling['wickets'] }}</x-crud.fact>
                        <x-crud.fact big label="Best">{{ $bowling['best_bowling'] ?? '-' }}</x-crud.fact>
                        <x-crud.fact big label="Average">{{ $bowling['bowling_average'] !== null ? number_format($bowling['bowling_average'], 2) : '-' }}</x-crud.fact>
                        <x-crud.fact big label="Economy">{{ number_format($bowling['economy'], 2) }}</x-crud.fact>
                    </dl>
                </x-admin.card>
            </div>
        </div>

        <x-admin.card title="Match History" flush>
            <table class="crud-table crud-stack">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th class="hidden md:table-cell">Edition</th>
                        <th>Match</th>
                        <th>Batting</th>
                        <th>Bowling</th>
                        <th class="hidden md:table-cell">Result</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($matchHistory as $row)
                        @php $match = $row['match']; @endphp
                        <tr class="crud-row">
                            <td class="c-sub whitespace-nowrap text-slate-500">{{ display_datetime($match->scheduled_at, 'd M Y') }}</td>
                            <td class="hidden md:table-cell">{{ $match->edition->name }}</td>
                            <td class="c-title">
                                @can('view', $match)
                                    <a href="{{ route('admin.matches.show', $match) }}" class="crud-row-link">
                                        {{ $match->teamA->team->name }} vs {{ $match->teamB->team->name }}
                                    </a>
                                @else
                                    {{ $match->teamA->team->name }} vs {{ $match->teamB->team->name }}
                                @endcan
                                <span class="crud-meta md:hidden">
                                    Bat:
                                    @if($row['batting'])
                                        {{ $row['batting']['runs'] }} ({{ $row['batting']['balls'] }}){{ $row['batting']['not_out'] ? '*' : '' }}
                                    @else
                                        &mdash;
                                    @endif
                                    &middot; Bowl:
                                    @if($row['bowling'])
                                        {{ $row['bowling']['wickets'] }}/{{ $row['bowling']['runs_conceded'] }} ({{ $row['bowling']['overs'] }})
                                    @else
                                        &mdash;
                                    @endif
                                </span>
                            </td>
                            <td class="font-semibold tabular-nums text-slate-800">
                                @if($row['batting'])
                                    {{ $row['batting']['runs'] }} ({{ $row['batting']['balls'] }}){{ $row['batting']['not_out'] ? '*' : '' }}
                                @else
                                    <span class="font-normal text-slate-300">&mdash;</span>
                                @endif
                            </td>
                            <td class="font-semibold tabular-nums text-slate-800">
                                @if($row['bowling'])
                                    {{ $row['bowling']['wickets'] }}/{{ $row['bowling']['runs_conceded'] }} ({{ $row['bowling']['overs'] }})
                                @else
                                    <span class="font-normal text-slate-300">&mdash;</span>
                                @endif
                            </td>
                            <td class="hidden text-slate-500 md:table-cell">
                                @if($match->match_status === 'completed')
                                    {{ $match->match_result }}
                                @else
                                    <x-status-badge :status="$match->match_status" />
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-admin.empty table colspan="6" icon="trophy">No match history yet.</x-admin.empty>
                    @endforelse
                </tbody>
            </table>
        </x-admin.card>

        <div>
            {{ $matchHistory->links() }}
        </div>
    </div>
@endsection
