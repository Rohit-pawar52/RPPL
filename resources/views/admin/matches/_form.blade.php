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
        :value="$match?->scheduled_at ? display_datetime($match->scheduled_at, 'Y-m-d\TH:i') : old('scheduled_at', '')"
        required
    />
</div>

{{--
    Match reminder — a push notification sent shortly before the match,
    via the same Notification/NotificationSend pipeline every other push
    uses (see MatchReminderService). The due instant is always DERIVED
    as scheduled_at minus the minutes below, never stored separately, so
    rescheduling the match above automatically moves an unsent reminder
    with it.
--}}
@php
    $reminderAlreadySent = $match?->reminder_dispatched_at !== null;
    $reminderChecked = old('reminder_enabled', $match->reminder_enabled ?? false);
@endphp

<div class="mt-4 border-t border-neutral-100 pt-3.5">
    <p class="mb-2 text-xs font-medium text-neutral-700">Match Reminder</p>

    @if($reminderAlreadySent)
        <p class="rounded-md bg-neutral-50 px-3 py-2 text-[11px] text-neutral-500">
            A reminder for this match has already been sent.
        </p>
    @else
        <label class="flex items-center gap-2 text-[13px] text-neutral-700">
            <input
                type="checkbox"
                name="reminder_enabled"
                value="1"
                {{ $reminderChecked ? 'checked' : '' }}
                class="theme-focus-ring"
            />
            Send reminder before match
        </label>

        <div class="mt-2 max-w-40">
            <x-form.input
                name="reminder_minutes_before"
                label="Reminder before (minutes)"
                type="number"
                min="1"
                max="1440"
                :value="$match->reminder_minutes_before ?? old('reminder_minutes_before', 30)"
            />
        </div>
        <p class="mt-1 text-[11px] text-neutral-400">
            Only used when "Send reminder before match" is checked.
        </p>
        @error('reminder_minutes_before')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
    @endif
</div>
