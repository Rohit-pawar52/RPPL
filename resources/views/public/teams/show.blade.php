@extends('layouts.public')

@section('title', $team->name.' · '.$branding->shortName)

@section('content')
    <a href="{{ route('public.teams.index') }}" class="mb-2 inline-flex items-center gap-1 text-xs font-medium text-slate-500 hover:text-slate-900">
        <span aria-hidden="true">&larr;</span> {{ __('directory.teams.back') }}
    </a>

    <div class="pub-card p-4 sm:p-5">
        <div class="flex items-center gap-4">
            <div class="pub-media flex h-16 w-16 shrink-0 items-center justify-center rounded-full border border-line bg-slate-100 text-xl font-bold text-slate-500 sm:h-20 sm:w-20 sm:text-2xl">
                <x-media-image :path="$team->logo_path" kind="image" alt="" class="absolute inset-0 h-full w-full bg-white object-cover" />
            </div>

            <div class="min-w-0">
                <h1 class="pub-h1 break-words">{{ $team->name }}</h1>
                <p class="pub-meta mt-0.5">{{ $team->short_name ?? __('directory.teams.no_short_name') }}</p>
            </div>
        </div>

        @if($editionTeams->isNotEmpty())
            <div class="mt-4 flex gap-2 overflow-x-auto border-t border-line pt-3">
                @foreach($editionTeams as $editionTeam)
                    <a
                        href="{{ route('public.teams.show', ['team' => $team, 'edition_id' => $editionTeam->edition_id]) }}"
                        class="inline-flex min-h-9 shrink-0 items-center rounded-full border px-3 text-xs font-semibold {{ $selectedEditionTeam?->id === $editionTeam->id ? 'border-green-600 bg-green-50 text-green-700' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50' }}"
                    >
                        {{ $editionTeam->edition->name }}
                    </a>
                @endforeach
            </div>
        @else
            <p class="pub-meta mt-4 border-t border-line pt-3">{{ __('directory.teams.no_history') }}</p>
        @endif
    </div>

    @if($record)
        <div class="mt-3 grid grid-cols-4 gap-2 text-center sm:gap-3">
            @foreach(['played', 'won', 'lost', 'tied'] as $key)
                <div class="pub-card px-2 py-3">
                    <p class="pub-eyebrow">{{ __('directory.teams.'.$key) }}</p>
                    <p class="mt-1 text-xl font-bold tabular-nums text-slate-900">{{ $record[$key] }}</p>
                </div>
            @endforeach
        </div>
    @endif

    <x-public.card class="mt-4" flush>
        <header class="pub-card-head">
            <h2 class="pub-card-title">{{ __('directory.teams.squad') }} @if($selectedEditionTeam) &middot; {{ $selectedEditionTeam->edition->name }} @endif</h2>
        </header>
        <div class="grid grid-cols-1 sm:grid-cols-2">
            @forelse($squad as $teamPlayer)
                @php $player = $teamPlayer->playerRegistration->player; @endphp
                <a href="{{ route('public.players.show', $player) }}" class="flex min-h-14 items-center gap-3 border-b border-line px-4 py-2.5 hover:bg-slate-50 sm:odd:border-r">
                    <div class="pub-media flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-line text-xs font-bold text-slate-500">
                        <x-media-image :path="$player->photo_path" kind="user" alt="" class="absolute inset-0 h-full w-full bg-white object-cover" />
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-[13px] font-semibold text-slate-900">{{ $player->name }}</p>
                        <p class="pub-meta truncate">
                            @if($teamPlayer->jersey_number) #{{ $teamPlayer->jersey_number }} &middot; @endif
                            {{ \Illuminate\Support\Facades\Lang::has('directory.roles.'.$teamPlayer->role) ? __('directory.roles.'.$teamPlayer->role) : str_replace('_', ' ', ucfirst($teamPlayer->role)) }}
                        </p>
                    </div>
                </a>
            @empty
                <x-public.empty class="col-span-full">{{ __('directory.teams.squad_empty') }}</x-public.empty>
            @endforelse
        </div>
    </x-public.card>

    <x-public.card class="mt-4" flush :title="__('directory.teams.recent_matches')">
        @forelse($recentMatches as $match)
            @php
                $opponent = $match->teamA->id === $selectedEditionTeam->id ? $match->teamB : $match->teamA;
            @endphp
            <div class="flex items-center justify-between gap-3 border-b border-line px-4 py-3 text-[13px] last:border-b-0">
                <div class="min-w-0">
                    <a href="{{ route('public.matches.show', $match) }}" class="font-semibold text-slate-900 hover:text-green-700">
                        {{ __('directory.common.vs') }} {{ $opponent->team->name }}
                    </a>
                    <p class="pub-meta">
                        {{ display_datetime($match->scheduled_at, 'd M Y') }}
                        @if($match->venue)
                            &middot; {{ $match->venue->name }}
                        @endif
                    </p>
                    @if($match->match_status === 'completed' && $match->match_result)
                        <p class="mt-0.5 text-xs text-slate-600">{{ $match->match_result }}</p>
                    @endif
                </div>
                <x-public.status-pill :status="$match->match_status" />
            </div>
        @empty
            <x-public.empty>{{ __('directory.teams.recent_matches_empty') }}</x-public.empty>
        @endforelse
    </x-public.card>
@endsection
