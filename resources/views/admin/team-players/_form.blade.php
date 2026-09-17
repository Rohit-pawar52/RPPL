{{-- Shared by create.blade.php and edit.blade.php. $teamPlayer is null on create.
     edition_team_id/player_registration_id are immutable once a squad
     assignment exists, so they only render as selects on create; on edit
     they show read-only. --}}
@php
    $teamPlayer = $teamPlayer ?? null;
@endphp

@if($teamPlayer)
    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <p class="mb-1 text-xs font-medium text-neutral-700">Player</p>
            <p class="rounded-md border border-neutral-200 bg-neutral-50 px-3 py-2 text-[13px] text-neutral-700">
                {{ $teamPlayer->playerRegistration->player->name }}
            </p>
        </div>
        <div>
            <p class="mb-1 text-xs font-medium text-neutral-700">Team / Edition</p>
            <p class="rounded-md border border-neutral-200 bg-neutral-50 px-3 py-2 text-[13px] text-neutral-700">
                {{ $teamPlayer->editionTeam->team->name }} &middot; {{ $teamPlayer->editionTeam->edition->name }}
            </p>
        </div>
    </div>
@else
    <x-form.select
        name="edition_team_id"
        label="Edition Team"
        placeholder="Select a team participation"
        :options="$editionTeams->mapWithKeys(fn ($editionTeam) => [$editionTeam->id => $editionTeam->edition->name.' — '.$editionTeam->team->name])"
        required
    />
    <x-form.select
        name="player_registration_id"
        label="Player Registration"
        placeholder="Select a player registration"
        :options="$registrations->mapWithKeys(fn ($registration) => [$registration->id => $registration->player->name.' — '.$registration->edition->name])"
        required
    />
@endif

<div class="grid gap-4 sm:grid-cols-2">
    <x-form.input
        name="jersey_number"
        label="Jersey number"
        type="number"
        min="1"
        :value="$teamPlayer->jersey_number ?? ''"
    />
    <x-form.select
        name="role"
        label="Squad role"
        placeholder="Not set"
        :options="collect($roles)->mapWithKeys(fn ($role) => [$role => ucwords(str_replace('_', ' ', $role))])"
        :value="$teamPlayer->role ?? ''"
    />
</div>
