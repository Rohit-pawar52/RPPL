@extends('layouts.public')

@section('title', 'Squads · '.$match->teamA->team->name.' vs '.$match->teamB->team->name)

{{--
    Public per-match Playing XI. Deliberately the announced XI
    (MatchPlayer rows) only — never the full squad — so a bench player
    is never shown as if they played. See MatchController::squads() for
    how the two teams' XIs are split without an N+1 query.
--}}
@section('content')
    <div class="mb-3">
        <a href="{{ route('public.matches.index') }}" class="text-xs text-neutral-500 hover:text-neutral-700">
            &larr; All matches
        </a>
    </div>

    <div class="rounded-lg border border-neutral-200 bg-white p-3 sm:p-4">
        <p class="truncate text-[11px] text-neutral-500">{{ $match->edition->name }}</p>
        <div class="mt-0.5 flex items-center justify-between gap-3">
            <h1 class="min-w-0 text-base font-semibold text-neutral-900">
                {{ $match->teamA->team->name }} vs {{ $match->teamB->team->name }}
            </h1>
            <x-status-badge :status="$match->match_status" />
        </div>
    </div>

    @include('public.matches._match-tabs', ['match' => $match, 'active' => 'squads'])

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
        @foreach([[$match->teamA, $teamAPlayers], [$match->teamB, $teamBPlayers]] as [$editionTeam, $players])
            <section class="rounded-lg border border-neutral-200 bg-white p-3 sm:p-4">
                <div class="flex items-center gap-2 border-b border-neutral-100 pb-2">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center overflow-hidden rounded-full border border-neutral-200 bg-neutral-50 text-neutral-300">
                        @if($editionTeam->team->logo_path)
                            <img src="{{ Illuminate\Support\Facades\Storage::url($editionTeam->team->logo_path) }}" alt="{{ $editionTeam->team->name }}" class="h-full w-full object-cover" />
                        @else
                            <x-icon name="shield" class="h-3.5 w-3.5" />
                        @endif
                    </span>
                    <span class="min-w-0">
                        <span class="block truncate text-[13px] font-semibold text-neutral-900">{{ $editionTeam->team->name }}</span>
                        <span class="block text-[11px] uppercase tracking-wide text-neutral-400">Playing XI</span>
                    </span>
                </div>

                @forelse($players as $matchPlayer)
                    @php
                        $player = $matchPlayer->teamPlayer->playerRegistration->player;
                        $roleLabel = match ($matchPlayer->teamPlayer->role) {
                            'batter' => 'Batter',
                            'bowler' => 'Bowler',
                            'all_rounder' => 'All-rounder',
                            'wicket_keeper' => 'Wicketkeeper',
                            default => null,
                        };
                        $marker = match (true) {
                            $matchPlayer->is_captain && $matchPlayer->is_wicket_keeper => '(C & WK)',
                            $matchPlayer->is_captain => '(C)',
                            $matchPlayer->is_wicket_keeper => '(WK)',
                            default => null,
                        };
                    @endphp
                    <div class="flex items-center justify-between gap-2 border-b border-neutral-100 py-2 text-[13px] last:border-b-0">
                        <div class="min-w-0">
                            <p class="truncate font-medium text-neutral-800">
                                {{ $player->name }}
                                @if($marker)
                                    <span class="text-[11px] font-semibold text-neutral-500">{{ $marker }}</span>
                                @endif
                            </p>
                            @if($roleLabel)
                                <p class="text-[11px] text-neutral-500">{{ $roleLabel }}</p>
                            @endif
                        </div>
                        @if($matchPlayer->teamPlayer->jersey_number)
                            <span class="shrink-0 text-[11px] text-neutral-400">#{{ $matchPlayer->teamPlayer->jersey_number }}</span>
                        @endif
                    </div>
                @empty
                    <p class="py-4 text-center text-[11px] text-neutral-400">Playing XI not announced yet.</p>
                @endforelse
            </section>
        @endforeach
    </div>
@endsection
