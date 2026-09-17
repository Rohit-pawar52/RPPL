@extends('layouts.admin')

@section('title', 'Squad Player Details')

@section('content')
    <div class="mb-4 flex items-center justify-between">
        <a href="{{ route('admin.team-players.index') }}" class="text-xs text-neutral-500 hover:text-neutral-700">
            &larr; Back to squads
        </a>
        <a
            href="{{ route('admin.team-players.edit', $teamPlayer) }}"
            class="inline-flex items-center gap-1.5 rounded-md border border-neutral-200 px-2.5 py-1.5 text-xs font-medium text-neutral-600 hover:bg-neutral-50"
        >
            <x-icon name="pencil" class="h-3.5 w-3.5" />
            Edit
        </a>
    </div>

    @php
        $player = $teamPlayer->playerRegistration->player;
    @endphp

    <div class="rounded-lg border border-neutral-200 bg-white p-4">
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-base font-semibold text-neutral-900">{{ $player->name }}</h2>
            <x-status-badge :status="$teamPlayer->playerRegistration->payment_status" />
        </div>
        <p class="mt-1 text-xs text-neutral-500">
            {{ $teamPlayer->editionTeam->team->name }} &middot; {{ $teamPlayer->editionTeam->edition->name }}
        </p>

        <dl class="mt-4 grid grid-cols-2 gap-3 text-xs sm:grid-cols-4">
            <div>
                <dt class="text-neutral-400">Jersey number</dt>
                <dd class="mt-0.5 font-medium text-neutral-800">{{ $teamPlayer->jersey_number ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-neutral-400">Squad role</dt>
                <dd class="mt-0.5 font-medium capitalize text-neutral-800">
                    {{ $teamPlayer->role ? str_replace('_', ' ', $teamPlayer->role) : '—' }}
                </dd>
            </div>
            <div>
                <dt class="text-neutral-400">General role</dt>
                <dd class="mt-0.5 font-medium capitalize text-neutral-800">
                    {{ $player->primary_role ? str_replace('_', ' ', $player->primary_role) : '—' }}
                </dd>
            </div>
            <div>
                <dt class="text-neutral-400">Added</dt>
                <dd class="mt-0.5 font-medium text-neutral-800">{{ $teamPlayer->created_at->format('d M Y') }}</dd>
            </div>
        </dl>
    </div>

    <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
        <x-stat-card label="Match appearances" :value="$teamPlayer->match_players_count" icon="trophy" />
    </div>
@endsection
