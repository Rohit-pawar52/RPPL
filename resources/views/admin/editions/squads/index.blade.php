@extends('layouts.admin')

@section('title', 'Squads')

@section('content')
    @include('admin.editions._crumbs', ['edition' => $edition, 'section' => 'Squads'])

    <x-admin.card :title="'Squads in '.$edition->name" flush>
        <table class="w-full text-left text-[13px]">
            <thead class="border-b border-slate-200 bg-slate-50 text-[11px] uppercase tracking-wide text-slate-400">
                <tr>
                    <th class="px-4 py-2 font-medium">Team</th>
                    <th class="px-4 py-2 text-right font-medium">Players</th>
                    <th class="px-4 py-2 text-right font-medium">Bought for</th>
                    <th class="px-4 py-2 text-right font-medium"><span class="sr-only">Open</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($editionTeams as $editionTeam)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-1.5 font-medium text-slate-800">
                            <a href="{{ route('admin.editions.squads.show', [$edition, $editionTeam]) }}" class="hover:underline">{{ $editionTeam->team->name }}</a>
                        </td>
                        <td class="px-4 py-1.5 text-right text-slate-600">{{ $editionTeam->team_players_count }}</td>
                        <td class="px-4 py-1.5 text-right text-slate-600">{{ $editionTeam->sold_total !== null ? money($editionTeam->sold_total) : '—' }}</td>
                        <td class="px-4 py-1.5 text-right">
                            <a href="{{ route('admin.editions.squads.show', [$edition, $editionTeam]) }}" class="text-[11px] font-medium text-green-700 hover:underline">Open squad &rarr;</a>
                        </td>
                    </tr>
                @empty
                    <x-admin.empty table colspan="4">
                        No teams in this season yet. <a href="{{ route('admin.editions.teams.index', $edition) }}" class="font-medium text-green-700 hover:underline">Add teams first</a>.
                    </x-admin.empty>
                @endforelse
            </tbody>
        </table>
    </x-admin.card>

    <p class="mt-3 text-xs text-slate-500">
        <span class="font-medium text-slate-700">{{ $withoutTeam }}</span> {{ \Illuminate\Support\Str::plural('player', $withoutTeam) }} of this season not in any team yet.
    </p>
@endsection
