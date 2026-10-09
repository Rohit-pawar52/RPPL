@extends('layouts.public')

@section('title', $team->name.' · '.$branding->shortName)

@php
    use Illuminate\Support\Facades\Lang;

    // The squad grouped by playing role, in the order a team sheet is read.
    $roleOrder = ['batter', 'wicket_keeper', 'all_rounder', 'bowler'];
    $squadByRole = $squad
        ->sortBy(fn ($teamPlayer) => $teamPlayer->jersey_number ?? 999)
        ->groupBy('role')
        ->sortBy(function ($group, $role) use ($roleOrder) {
            $position = array_search($role, $roleOrder, true);

            return $position === false ? 99 : $position;
        });

    $roleLabel = fn (?string $role) => $role === null
        ? ''
        : (Lang::has('directory.roles.'.$role) ? __('directory.roles.'.$role) : str_replace('_', ' ', ucfirst($role)));
@endphp

@section('content')
    <a href="{{ route('public.teams.index') }}" class="pc-back">
        <span aria-hidden="true">&larr;</span> {{ __('directory.teams.back') }}
    </a>

    {{-- Identity --}}
    <section class="pc-hero p-5 sm:p-8">
        <div class="flex flex-col items-center gap-4 text-center sm:flex-row sm:gap-6 sm:text-left">
            <span class="pc-avatar h-24 w-24 shrink-0 border-0 ring-4 ring-white/15 sm:h-28 sm:w-28">
                <x-media-image :path="$team->logo_path" kind="image" alt="" />
            </span>

            <div class="min-w-0">
                @if($selectedEditionTeam)
                    <p class="pc-eyebrow">{{ $selectedEditionTeam->edition->name }}</p>
                @endif
                <h1 class="mt-1 break-words text-3xl font-bold leading-tight tracking-tight sm:text-4xl">{{ $team->name }}</h1>
                <p class="mt-1 text-sm text-slate-300">{{ $team->short_name ?? __('directory.teams.no_short_name') }}</p>
            </div>
        </div>

        @if($editionTeams->isNotEmpty())
            <div class="pc-chips mt-5 justify-start border-t border-white/10 pt-4 sm:mt-6">
                @foreach($editionTeams as $editionTeam)
                    <a
                        href="{{ route('public.teams.show', ['team' => $team, 'edition_id' => $editionTeam->edition_id]) }}"
                        class="pc-chip pc-chip-dark {{ $selectedEditionTeam?->id === $editionTeam->id ? 'pc-chip-active' : '' }}"
                        @if($selectedEditionTeam?->id === $editionTeam->id) aria-current="true" @endif
                    >
                        {{ $editionTeam->edition->name }}
                    </a>
                @endforeach
            </div>
        @else
            <p class="mt-5 border-t border-white/10 pt-4 text-xs text-slate-300">{{ __('directory.teams.no_history') }}</p>
        @endif
    </section>

    {{-- Record --}}
    @if($record)
        <div class="mt-4 grid grid-cols-5 gap-2 sm:gap-3">
            @foreach(['played', 'won', 'lost', 'tied'] as $key)
                <div class="pc-stat">
                    <p class="pc-stat-label">{{ __('directory.teams.'.$key) }}</p>
                    <p class="pc-stat-value">{{ $record[$key] }}</p>
                </div>
            @endforeach
            <div class="pc-stat pc-stat-accent">
                <p class="pc-stat-label">{{ __('ux_public_content.team.points') }}</p>
                <p class="pc-stat-value">{{ $record['points'] ?? 0 }}</p>
            </div>
        </div>
    @endif

    {{-- Squad, grouped by role --}}
    <section class="mt-6 lg:mt-8">
        <h2 class="pc-h2">
            {{ __('directory.teams.squad') }} @if($selectedEditionTeam) &middot; {{ $selectedEditionTeam->edition->name }} @endif
            <span class="pc-h2-badge">{{ $squad->count() }}</span>
        </h2>

        @forelse($squadByRole as $role => $members)
            <div class="pc-panel mb-3">
                <div class="pc-panel-head bg-slate-50/60 py-2">
                    <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $roleLabel($role) }}</h3>
                    <span class="text-xs font-semibold tabular-nums text-slate-400">{{ $members->count() }}</span>
                </div>
                <div class="overflow-hidden">
                <div class="-mb-px -mr-px grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach($members as $teamPlayer)
                        @php $player = $teamPlayer->playerRegistration->player; @endphp
                        <a href="{{ route('public.players.show', $player) }}" class="flex min-h-16 items-center gap-3 border-b border-r border-line bg-white px-4 py-2.5 transition hover:bg-hover focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-brand">
                            <span class="pc-avatar h-11 w-11">
                                <x-media-image :path="$player->photo_path" kind="user" alt="" loading="lazy" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-[13px] font-semibold text-slate-900">{{ $player->name }}</span>
                                <span class="block truncate text-xs text-slate-500">
                                    @if($teamPlayer->jersey_number) #{{ $teamPlayer->jersey_number }} &middot; @endif
                                    {{ $roleLabel($teamPlayer->role) }}
                                </span>
                            </span>
                            <span class="shrink-0 text-slate-300" aria-hidden="true">&rsaquo;</span>
                        </a>
                    @endforeach
                </div>
                </div>
            </div>
        @empty
            <div class="pc-empty">
                <span class="pc-empty-icon"><x-icon name="users" class="h-7 w-7" /></span>
                <p class="pc-empty-title">{{ __('directory.teams.squad_empty') }}</p>
            </div>
        @endforelse
    </section>

    {{-- Recent matches --}}
    <section class="mt-6 lg:mt-8">
        <h2 class="pc-h2">{{ __('directory.teams.recent_matches') }}</h2>

        @if($recentMatches->isEmpty())
            <div class="pc-empty">
                <span class="pc-empty-icon"><x-icon name="calendar" class="h-7 w-7" /></span>
                <p class="pc-empty-title">{{ __('directory.teams.recent_matches_empty') }}</p>
            </div>
        @else
            <div class="pc-panel divide-y divide-line">
                @foreach($recentMatches as $match)
                    @php
                        $opponent = $match->teamA->id === $selectedEditionTeam->id ? $match->teamB : $match->teamA;
                        $decided = $match->match_status === 'completed' && $match->winner_team_id !== null;
                        $won = $decided && $match->winner_team_id === $selectedEditionTeam->id;
                    @endphp
                    <a href="{{ route('public.matches.show', $match) }}" class="flex items-center gap-3 px-4 py-3 text-[13px] transition hover:bg-hover focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-brand">
                        <span class="flex h-12 w-12 shrink-0 flex-col items-center justify-center rounded-lg bg-slate-100 leading-none">
                            <span class="text-base font-bold tabular-nums text-slate-900">{{ display_datetime($match->scheduled_at, 'd') }}</span>
                            <span class="mt-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-500">{{ display_datetime($match->scheduled_at, 'M') }}</span>
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate font-semibold text-slate-900">{{ __('directory.common.vs') }} {{ $opponent->team->name }}</span>
                            <span class="pub-meta block truncate">
                                {{ display_datetime($match->scheduled_at, 'd M Y') }}
                                @if($match->venue)
                                    &middot; {{ $match->venue->name }}
                                @endif
                            </span>
                            @if($match->match_status === 'completed' && $match->match_result)
                                <span class="mt-0.5 block text-xs text-slate-600">{{ $match->match_result }}</span>
                            @endif
                        </span>
                        <span class="flex shrink-0 flex-col items-end gap-1">
                            @if($decided)
                                <span class="pub-pill {{ $won ? 'pub-pill-success' : 'pub-pill-danger' }}">{{ $won ? __('directory.teams.won') : __('directory.teams.lost') }}</span>
                            @else
                                <x-public.status-pill :status="$match->match_status" />
                            @endif
                        </span>
                    </a>
                @endforeach
            </div>
        @endif
    </section>
@endsection
