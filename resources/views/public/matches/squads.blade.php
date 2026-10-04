@extends('layouts.public')

@section('title', __('matches.nav.squads').' · '.$match->teamA->team->name.' '.__('matches.common.vs').' '.$match->teamB->team->name)

{{--
    Public per-match Playing XI. Deliberately the announced XI
    (MatchPlayer rows) only — never the full squad — so a bench player
    is never shown as if they played. See MatchController::squads() for
    how the two teams' XIs are split without an N+1 query.
--}}
@section('content')
    @include('public.matches._header', ['match' => $match, 'active' => 'squads'])

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
        @foreach([[$match->teamA, $teamAPlayers], [$match->teamB, $teamBPlayers]] as [$editionTeam, $players])
            <section class="pub-card overflow-hidden">
                <header class="pub-card-head justify-start">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-full border border-line bg-slate-50 text-slate-300">
                        <x-media-image :path="$editionTeam->team->logo_path" kind="image" alt="" class="h-full w-full object-cover" />
                    </span>
                    <span class="min-w-0">
                        <h2 class="pub-card-title truncate">{{ $editionTeam->team->name }}</h2>
                        <span class="pub-eyebrow block">{{ __('matches.squads.playing_xi') }}</span>
                    </span>
                </header>

                @forelse($players as $matchPlayer)
                    @php
                        $player = $matchPlayer->teamPlayer->playerRegistration->player;
                        $roleLabel = match ($matchPlayer->teamPlayer->role) {
                            'batter' => __('matches.squads.role.batter'),
                            'bowler' => __('matches.squads.role.bowler'),
                            'all_rounder' => __('matches.squads.role.all_rounder'),
                            'wicket_keeper' => __('matches.squads.role.wicket_keeper'),
                            default => null,
                        };
                        $marker = match (true) {
                            $matchPlayer->is_captain && $matchPlayer->is_wicket_keeper => __('matches.squads.captain_and_wicket_keeper'),
                            $matchPlayer->is_captain => __('matches.squads.captain'),
                            $matchPlayer->is_wicket_keeper => __('matches.squads.wicket_keeper'),
                            default => null,
                        };
                    @endphp
                    <div class="flex min-h-12 items-center justify-between gap-3 border-b border-line px-4 py-2.5 text-[13px] last:border-b-0">
                        <div class="min-w-0">
                            <p class="truncate font-medium text-slate-800">
                                {{ $player->name }}
                                @if($marker)
                                    <span class="text-[11px] font-semibold text-green-700">{{ $marker }}</span>
                                @endif
                            </p>
                            @if($roleLabel)
                                <p class="pub-meta">{{ $roleLabel }}</p>
                            @endif
                        </div>
                        @if($matchPlayer->teamPlayer->jersey_number)
                            <span class="shrink-0 rounded-md bg-slate-100 px-1.5 py-0.5 text-[11px] font-semibold tabular-nums text-slate-500">#{{ $matchPlayer->teamPlayer->jersey_number }}</span>
                        @endif
                    </div>
                @empty
                    <p class="pub-empty">{{ __('matches.squads.not_announced') }}</p>
                @endforelse
            </section>
        @endforeach
    </div>
@endsection
