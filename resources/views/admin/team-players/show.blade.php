@extends('layouts.admin')

@section('title', 'Squad Player Details')

@section('content')
    @php
        $player = $teamPlayer->playerRegistration->player;
    @endphp

    <x-crud.back :href="route('admin.team-players.index')">Squads</x-crud.back>

    <div class="space-y-4 lg:space-y-5">
        <x-crud.profile
            :title="$player->name"
            :path="$player->photo_path"
            kind="user"
            :status="$teamPlayer->playerRegistration->payment_status"
            :subtitle="$teamPlayer->editionTeam->team->name.' · '.$teamPlayer->editionTeam->edition->name"
        >
            @if($teamPlayer->jersey_number)
                <span class="crud-pill crud-pill-brand">Jersey #{{ $teamPlayer->jersey_number }}</span>
            @endif

            <x-slot:actions>
                <x-admin.button :href="route('admin.players.show', $player)" variant="secondary" icon="user">Player page</x-admin.button>
                <x-admin.button :href="route('admin.team-players.edit', $teamPlayer)" icon="pencil">Edit</x-admin.button>
            </x-slot:actions>
        </x-crud.profile>

        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_16rem] lg:gap-5">
            <x-admin.card title="Squad place">
                <dl class="crud-facts">
                    <x-crud.fact label="Jersey number">{{ $teamPlayer->jersey_number ?? '—' }}</x-crud.fact>
                    <x-crud.fact label="Squad role" class="capitalize">{{ $teamPlayer->role ? str_replace('_', ' ', $teamPlayer->role) : '—' }}</x-crud.fact>
                    <x-crud.fact label="General role" class="capitalize">{{ $player->primary_role ? str_replace('_', ' ', $player->primary_role) : '—' }}</x-crud.fact>
                    <x-crud.fact label="Added">{{ display_datetime($teamPlayer->created_at, 'd M Y') }}</x-crud.fact>
                </dl>
            </x-admin.card>

            <x-crud.kpi label="Match appearances" :value="$teamPlayer->match_players_count" icon="trophy" tone="brand" />
        </div>
    </div>
@endsection
