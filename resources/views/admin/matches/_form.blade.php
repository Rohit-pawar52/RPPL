{{-- Shared by create.blade.php and edit.blade.php. $match is null on create.
     $canChangeIdentity is always true on create; on edit it reflects
     whether MatchPlayer/Innings already exist for this match. --}}
@php
    $match = $match ?? null;
    $canChangeIdentity = $canChangeIdentity ?? true;
@endphp

@if($canChangeIdentity)
    <x-form.select
        name="edition_id"
        label="Edition"
        placeholder="Select an edition"
        :options="$editions->pluck('name', 'id')"
        :value="$match->edition_id ?? ''"
        required
    />

    <div class="grid gap-4 sm:grid-cols-2">
        <x-form.select
            name="edition_team_a_id"
            label="Team A"
            placeholder="Select Team A"
            :options="$editionTeams->mapWithKeys(fn ($editionTeam) => [$editionTeam->id => $editionTeam->edition->name.' — '.$editionTeam->team->name])"
            :value="$match->edition_team_a_id ?? ''"
            required
        />
        <x-form.select
            name="edition_team_b_id"
            label="Team B"
            placeholder="Select Team B"
            :options="$editionTeams->mapWithKeys(fn ($editionTeam) => [$editionTeam->id => $editionTeam->edition->name.' — '.$editionTeam->team->name])"
            :value="$match->edition_team_b_id ?? ''"
            required
        />
    </div>
@else
    <div class="grid gap-4 sm:grid-cols-3">
        <div>
            <p class="mb-1 text-xs font-medium text-neutral-700">Edition</p>
            <p class="rounded-md border border-neutral-200 bg-neutral-50 px-3 py-2 text-[13px] text-neutral-700">
                {{ $match->edition->name }}
            </p>
        </div>
        <div>
            <p class="mb-1 text-xs font-medium text-neutral-700">Team A</p>
            <p class="rounded-md border border-neutral-200 bg-neutral-50 px-3 py-2 text-[13px] text-neutral-700">
                {{ $match->teamA->team->name }}
            </p>
        </div>
        <div>
            <p class="mb-1 text-xs font-medium text-neutral-700">Team B</p>
            <p class="rounded-md border border-neutral-200 bg-neutral-50 px-3 py-2 text-[13px] text-neutral-700">
                {{ $match->teamB->team->name }}
            </p>
        </div>
    </div>
    <p class="mb-4 mt-1 text-[11px] text-neutral-400">
        Edition and teams cannot be changed because squad or scoring data already exists for this match.
    </p>
@endif

<div class="mt-4 grid gap-4 sm:grid-cols-2">
    <x-form.select
        name="venue_id"
        label="Venue"
        placeholder="No venue / TBD"
        :options="$venues->pluck('name', 'id')"
        :value="$match->venue_id ?? ''"
    />
    <x-form.select
        name="match_stage"
        label="Match stage"
        placeholder="Not set"
        :options="collect($stages)->mapWithKeys(fn ($stage) => [$stage => ucwords(str_replace('_', ' ', $stage))])"
        :value="$match->match_stage ?? ''"
    />
</div>

<div class="mt-4 grid gap-4 sm:grid-cols-3">
    <x-form.input
        name="match_number"
        label="Match number"
        type="number"
        min="1"
        :value="$match->match_number ?? ''"
    />
    <x-form.input
        name="overs_per_innings"
        label="Overs per innings"
        type="number"
        min="1"
        max="50"
        :value="$match->overs_per_innings ?? 20"
    />
    <x-form.input
        name="scheduled_at"
        label="Scheduled at"
        type="datetime-local"
        :value="$match?->scheduled_at?->format('Y-m-d\TH:i') ?? ''"
        required
    />
</div>
