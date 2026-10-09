{{-- Shared by create.blade.php and edit.blade.php. $teamPlayer is null on create.
     edition_team_id/player_registration_id are immutable once a squad
     assignment exists, so they only render as selects on create; on edit
     they show read-only. --}}
@php
    $teamPlayer = $teamPlayer ?? null;
@endphp

<div class="crud-grid">
    <div class="crud-main">
        <x-admin.card :title="$teamPlayer ? __('Who and where') : __('Who joins which team')">
            @if($teamPlayer)
                <div class="crud-cols">
                    <div class="mb-3.5">
                        <p class="mb-1 text-xs font-medium text-slate-700">{{ __('Player') }}</p>
                        <p class="rounded-lg border border-line bg-slate-50 px-3 py-2.5 text-[13px] font-medium text-slate-800">
                            {{ $teamPlayer->playerRegistration->player->name }}
                        </p>
                    </div>
                    <div class="mb-3.5">
                        <p class="mb-1 text-xs font-medium text-slate-700">{{ __('Team / Edition') }}</p>
                        <p class="rounded-lg border border-line bg-slate-50 px-3 py-2.5 text-[13px] font-medium text-slate-800">
                            {{ $teamPlayer->editionTeam->team->name }} &middot; {{ $teamPlayer->editionTeam->edition->name }}
                        </p>
                    </div>
                </div>
            @else
                <x-form.select
                    name="edition_team_id"
                    :label="__('Edition Team')"
                    :placeholder="__('Select a team participation')"
                    :options="$editionTeams->mapWithKeys(fn ($editionTeam) => [$editionTeam->id => $editionTeam->edition->name.' — '.$editionTeam->team->name])"
                    required
                    autofocus
                />
                <x-form.select
                    name="player_registration_id"
                    :label="__('Player Registration')"
                    :placeholder="__('Select a player registration')"
                    :options="$registrations->mapWithKeys(fn ($registration) => [$registration->id => $registration->player->name.' — '.$registration->edition->name])"
                    required
                />
            @endif
        </x-admin.card>
    </div>

    <div class="crud-aside">
        <x-admin.card :title="__('Squad details')">
            <x-form.input
                name="jersey_number"
                :label="__('Jersey number')"
                type="number"
                min="1"
                :value="$teamPlayer->jersey_number ?? ''"
            />
            <x-form.select
                name="role"
                :label="__('Squad role')"
                :placeholder="__('Not set')"
                :options="collect($roles)->mapWithKeys(fn ($role) => [$role => ucwords(__(str_replace('_', ' ', $role)))])"
                :value="$teamPlayer->role ?? ''"
            />
        </x-admin.card>
    </div>
</div>
