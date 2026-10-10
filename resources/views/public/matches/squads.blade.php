@extends('layouts.public')

@section('title', __('matches.nav.squads').' · '.$match->teamA->team->name.' '.__('matches.common.vs').' '.$match->teamB->team->name)

{{--
    Public per-match Playing XI. Deliberately the announced XI
    (MatchPlayer rows) only - never the full squad - so a bench player
    is never shown as if they played. See MatchController::squads() for
    how the two teams' XIs are split without an N+1 query.

    On a phone the two teams are one tap apart (a pure CSS switch, no JS:
    radios + group-has); from md up both sit side by side.
--}}
@section('content')
    {{-- Same shape as the Live and Scorecard tabs: tabs first, a small score card, the squads in the wide column and the
         sponsor boxes in the narrow one. --}}
    <div class="-mt-3 lg:-mt-6">
        @include('public.matches._match-tabs', ['match' => $match, 'active' => 'squads'])
    </div>
    @include('public.matches._hero-scripts')

    <div class="mt-2 grid grid-cols-[minmax(0,1fr)] gap-3 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)] lg:items-start lg:gap-4">
    <div class="contents lg:block lg:min-w-0 lg:space-y-3">
    <div class="order-1">
        @include('public.matches._live-score', ['match' => $match, 'liveData' => $liveData])
    </div>

    <div class="group/squad order-3 min-w-0">
        <div class="mx-seg mb-3 md:hidden" role="radiogroup" aria-label="{{ __('matches.squads.playing_xi') }}">
            @foreach([[$match->teamA, 'a'], [$match->teamB, 'b']] as [$editionTeam, $key])
                <label class="mx-seg-item">
                    <input type="radio" name="squad-team" value="{{ $key }}" class="sr-only" {{ $key === 'a' ? 'checked' : '' }}>
                    <x-mx.team-logo :team="$editionTeam->team" size="xs" />
                    <span class="truncate">{{ $editionTeam->team->short_name ?: $editionTeam->team->name }}</span>
                </label>
            @endforeach
        </div>

        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
            @foreach([[$match->teamA, $teamAPlayers, 'a'], [$match->teamB, $teamBPlayers, 'b']] as [$editionTeam, $players, $key])
                @if($key === 'a')
                    <section class="pub-card overflow-hidden max-md:group-has-[input[value='b']:checked]/squad:hidden">
                @else
                    <section class="pub-card overflow-hidden max-md:hidden max-md:group-has-[input[value='b']:checked]/squad:block">
                @endif
                    <header class="flex items-center gap-3 border-b border-line bg-slate-50/70 px-4 py-3.5 sm:px-5">
                        <x-mx.team-logo :team="$editionTeam->team" size="md" />
                        <div class="min-w-0 flex-1">
                            <h2 class="truncate text-[15px] font-semibold tracking-tight text-slate-900">{{ $editionTeam->team->name }}</h2>
                            <p class="pub-eyebrow">{{ __('matches.squads.playing_xi') }}</p>
                        </div>
                        @if($players->isNotEmpty())
                            <span class="mx-seg-count">{{ $players->count() }}</span>
                        @endif
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
                        @endphp
                        <div class="mx-player">
                            <span class="mx-avatar">
                                <x-media-image :path="$player->photo_path" kind="user" alt="" class="h-full w-full object-cover" loading="lazy" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="flex flex-wrap items-center gap-x-1.5 gap-y-1 text-sm font-semibold text-slate-900">
                                    <span class="truncate">{{ $player->name }}</span>
                                    @if($matchPlayer->is_captain && $matchPlayer->is_wicket_keeper)
                                        <span class="mx-badge mx-badge-c">{{ __('matches.squads.captain_and_wicket_keeper') }}</span>
                                    @elseif($matchPlayer->is_captain)
                                        <span class="mx-badge mx-badge-c">{{ __('matches.squads.captain') }}</span>
                                    @elseif($matchPlayer->is_wicket_keeper)
                                        <span class="mx-badge mx-badge-wk">{{ __('matches.squads.wicket_keeper') }}</span>
                                    @endif
                                </p>
                                @if($roleLabel)
                                    <p class="pub-meta">{{ $roleLabel }}</p>
                                @endif
                            </div>
                            @if($matchPlayer->teamPlayer->jersey_number)
                                <span class="mx-jersey">#{{ $matchPlayer->teamPlayer->jersey_number }}</span>
                            @endif
                        </div>
                    @empty
                        <x-public.empty icon="users">{{ __('matches.squads.not_announced') }}</x-public.empty>
                    @endforelse
                </section>
            @endforeach
        </div>
    </div>
    </div>

    <aside class="contents lg:sticky lg:top-24 lg:block lg:min-w-0 lg:space-y-3">
        <x-ad-side class="order-2" :offset="0" />
        <x-ad-side class="order-4" :offset="1" />
    </aside>
    </div>
@endsection
