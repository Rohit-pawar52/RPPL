@extends('layouts.public')

@section('title', $team->name.' &middot; RPPL')

@section('content')
    <a href="{{ route('public.teams.index') }}" class="mb-4 inline-block text-xs text-neutral-500 hover:text-neutral-700">
        &larr; Back to teams
    </a>

    <div class="rounded-lg border border-neutral-200 bg-white p-4">
        <div class="flex items-center gap-4">
            <div class="flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-full border border-neutral-200 bg-neutral-50 text-neutral-300">
                @if($team->logo_path)
                    <img
                        src="{{ Illuminate\Support\Facades\Storage::url($team->logo_path) }}"
                        alt="{{ $team->name }}"
                        class="h-full w-full object-cover"
                    />
                @else
                    <x-icon name="shield" class="h-6 w-6" />
                @endif
            </div>

            <div>
                <h1 class="text-base font-semibold text-neutral-900">{{ $team->name }}</h1>
                <p class="text-xs text-neutral-500">{{ $team->short_name ?? 'No short name' }}</p>
            </div>
        </div>
    </div>

    <div class="mt-4 flex flex-wrap items-center gap-2">
        @forelse($editionTeams as $editionTeam)
            <a
                href="{{ route('public.teams.show', ['team' => $team, 'edition_id' => $editionTeam->edition_id]) }}"
                class="rounded-md border px-2.5 py-1 text-xs font-medium {{ $selectedEditionTeam?->id === $editionTeam->id ? 'border-blue-200 bg-blue-50 text-blue-700' : 'border-neutral-200 text-neutral-600 hover:bg-neutral-50' }}"
            >
                {{ $editionTeam->edition->name }}
            </a>
        @empty
            <p class="text-xs text-neutral-400">No tournament history available yet.</p>
        @endforelse
    </div>

    @if($record)
        <div class="mt-3 grid grid-cols-4 gap-3 text-center text-xs">
            <div class="rounded-lg border border-neutral-200 bg-white p-3">
                <p class="text-neutral-400">Played</p>
                <p class="mt-0.5 text-sm font-semibold text-neutral-900">{{ $record['played'] }}</p>
            </div>
            <div class="rounded-lg border border-neutral-200 bg-white p-3">
                <p class="text-neutral-400">Won</p>
                <p class="mt-0.5 text-sm font-semibold text-neutral-900">{{ $record['won'] }}</p>
            </div>
            <div class="rounded-lg border border-neutral-200 bg-white p-3">
                <p class="text-neutral-400">Lost</p>
                <p class="mt-0.5 text-sm font-semibold text-neutral-900">{{ $record['lost'] }}</p>
            </div>
            <div class="rounded-lg border border-neutral-200 bg-white p-3">
                <p class="text-neutral-400">Tied</p>
                <p class="mt-0.5 text-sm font-semibold text-neutral-900">{{ $record['tied'] }}</p>
            </div>
        </div>
    @endif

    <div class="mt-4 rounded-lg border border-neutral-200 bg-white p-4">
        <h3 class="mb-3 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">
            Squad @if($selectedEditionTeam) &middot; {{ $selectedEditionTeam->edition->name }} @endif
        </h3>

        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
            @forelse($squad as $teamPlayer)
                @php $player = $teamPlayer->playerRegistration->player; @endphp
                <a
                    href="{{ route('public.players.show', $player) }}"
                    class="flex items-center gap-3 rounded-md border border-neutral-200 p-2 hover:bg-neutral-50"
                >
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-full border border-neutral-200 bg-neutral-50 text-neutral-300">
                        @if($player->photo_path)
                            <img
                                src="{{ Illuminate\Support\Facades\Storage::url($player->photo_path) }}"
                                alt="{{ $player->name }}"
                                class="h-full w-full object-cover"
                            />
                        @else
                            <x-icon name="user" class="h-3.5 w-3.5" />
                        @endif
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-[13px] font-medium text-neutral-900">{{ $player->name }}</p>
                        <p class="truncate text-[11px] text-neutral-500">
                            @if($teamPlayer->jersey_number) #{{ $teamPlayer->jersey_number }} &middot; @endif
                            {{ str_replace('_', ' ', ucfirst($teamPlayer->role)) }}
                        </p>
                    </div>
                </a>
            @empty
                <p class="col-span-full py-4 text-center text-xs text-neutral-400">No squad available for this edition yet.</p>
            @endforelse
        </div>
    </div>

    <div class="mt-4 rounded-lg border border-neutral-200 bg-white p-4">
        <h3 class="mb-3 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">Recent Matches</h3>

        @forelse($recentMatches as $match)
            @php
                $opponent = $match->teamA->id === $selectedEditionTeam->id ? $match->teamB : $match->teamA;
            @endphp
            <div class="flex items-center justify-between gap-3 border-b border-neutral-100 py-2 text-[13px] last:border-b-0">
                <div class="min-w-0">
                    <a href="{{ route('public.matches.show', $match) }}" class="font-medium text-neutral-800 hover:underline">
                        vs {{ $opponent->team->name }}
                    </a>
                    <p class="text-[11px] text-neutral-500">
                        {{ display_datetime($match->scheduled_at, 'd M Y') }}
                        @if($match->venue)
                            &middot; {{ $match->venue->name }}
                        @endif
                    </p>
                    @if($match->match_status === 'completed' && $match->match_result)
                        <p class="mt-0.5 text-[11px] text-neutral-600">{{ $match->match_result }}</p>
                    @endif
                </div>
                <x-status-badge :status="$match->match_status" />
            </div>
        @empty
            <p class="py-4 text-center text-xs text-neutral-400">No matches available for this edition yet.</p>
        @endforelse
    </div>
@endsection
