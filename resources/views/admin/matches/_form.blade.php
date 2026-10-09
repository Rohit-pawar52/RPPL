{{-- Shared by create.blade.php, edit.blade.php and editions/matches/create.blade.php.
     $match is null on create. $canChangeIdentity is always true on create; on edit it
     reflects whether MatchPlayer/Innings already exist for this match. --}}
@php
    $match = $match ?? null;
    $canChangeIdentity = $canChangeIdentity ?? true;
    // Starting values for a NEW match (see GameMatchController::newMatchDefaults()).
    $defaults = $defaults ?? [];
    // Set when the form is opened from inside a season (SeasonMatchController):
    // the season is fixed and $editionTeams holds only that season's teams.
    $fixedEdition = $fixedEdition ?? null;

    // A new match is suggested for the day after the season's latest one, at the same time.
    $suggestedAt = '';
    $seasonId = $fixedEdition?->id ?? ($defaults['edition_id'] ?? null);
    if (! $match && $seasonId) {
        $latestAt = \App\Models\GameMatch::where('edition_id', $seasonId)->max('scheduled_at');
        if ($latestAt) {
            $suggestedAt = display_datetime(\Illuminate\Support\Carbon::parse($latestAt)->addDay(), 'Y-m-d\TH:i') ?? '';
        }
    }
@endphp

<div class="space-y-4" data-match-form>
    {{-- Who plays --}}
    <section class="ops-card">
        <div class="ops-card-head"><h3 class="ops-title">Who is playing</h3></div>
        <div class="ops-card-body">
            @if($fixedEdition)
                <p class="mb-4 text-[13px] text-slate-600">Season: <span class="font-semibold text-slate-800">{{ $fixedEdition->name }}</span></p>

                <div class="grid gap-x-4 sm:grid-cols-2">
                    <x-form.select
                        name="edition_team_a_id"
                        label="Team A"
                        placeholder="Select Team A"
                        :options="$editionTeams->mapWithKeys(fn ($editionTeam) => [$editionTeam->id => $editionTeam->team->name])"
                        :value="''"
                        required
                    />
                    <x-form.select
                        name="edition_team_b_id"
                        label="Team B"
                        placeholder="Select Team B"
                        :options="$editionTeams->mapWithKeys(fn ($editionTeam) => [$editionTeam->id => $editionTeam->team->name])"
                        :value="''"
                        required
                    />
                </div>
            @elseif($canChangeIdentity)
                <x-form.select
                    name="edition_id"
                    label="Edition"
                    placeholder="Select an edition"
                    :options="$editions->pluck('name', 'id')"
                    :value="$match->edition_id ?? ($defaults['edition_id'] ?? '')"
                    required
                />

                <div class="grid gap-x-4 sm:grid-cols-2">
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
                <p class="-mt-1 text-[11px] text-slate-400">Choosing an edition shortens the team lists to that season's teams.</p>
            @else
                <div class="grid gap-3 sm:grid-cols-3">
                    <div>
                        <p class="ops-kicker mb-1">Edition</p>
                        <p class="rounded-lg border border-line bg-slate-50 px-3 py-2.5 text-[13px] text-slate-700">{{ $match->edition->name }}</p>
                    </div>
                    <div>
                        <p class="ops-kicker mb-1">Team A</p>
                        <p class="rounded-lg border border-line bg-slate-50 px-3 py-2.5 text-[13px] text-slate-700">{{ $match->teamA->team->name }}</p>
                    </div>
                    <div>
                        <p class="ops-kicker mb-1">Team B</p>
                        <p class="rounded-lg border border-line bg-slate-50 px-3 py-2.5 text-[13px] text-slate-700">{{ $match->teamB->team->name }}</p>
                    </div>
                </div>
                <p class="mt-2 flex items-start gap-1.5 text-[11px] text-slate-400">
                    <x-ops.icon name="lock" class="mt-0.5 h-3.5 w-3.5" />
                    Edition and teams cannot be changed because squad or scoring data already exists for this match.
                </p>
            @endif
        </div>
    </section>

    {{-- When and where --}}
    <section class="ops-card">
        <div class="ops-card-head"><h3 class="ops-title">When and where</h3></div>
        <div class="ops-card-body">
            <div class="grid gap-x-4 sm:grid-cols-2">
                <x-form.input
                    name="scheduled_at"
                    label="Scheduled at"
                    type="datetime-local"
                    :value="$match?->scheduled_at ? display_datetime($match->scheduled_at, 'Y-m-d\TH:i') : old('scheduled_at', $suggestedAt)"
                    :help="$suggestedAt ? 'Suggested: the day after the season\'s latest match. Change it if needed.' : null"
                    required
                />
                <x-form.select
                    name="venue_id"
                    label="Venue"
                    placeholder="No venue / TBD"
                    :options="$venues->pluck('name', 'id')"
                    :value="$match->venue_id ?? ($defaults['venue_id'] ?? '')"
                />
            </div>
        </div>
    </section>

    {{-- Format --}}
    <section class="ops-card">
        <div class="ops-card-head"><h3 class="ops-title">Format</h3></div>
        <div class="ops-card-body">
            <div class="grid gap-x-4 sm:grid-cols-3">
                <x-form.input
                    name="match_number"
                    label="Match number"
                    type="number"
                    min="1"
                    :value="$match->match_number ?? ($defaults['match_number'] ?? '')"
                />
                <div>
                    <x-form.input
                        name="overs_per_innings"
                        label="Overs per innings"
                        type="number"
                        min="1"
                        max="50"
                        :value="$match->overs_per_innings ?? ($defaults['overs_per_innings'] ?? 20)"
                    />
                    {{-- Overs differ from match to match (6, 8 or 10 most often): one tap fills the box. --}}
                    <div class="-mt-2 mb-3.5 flex flex-wrap items-center gap-1.5" data-overs-picks>
                        <span class="text-[11px] text-slate-400">Quick pick:</span>
                        @foreach([6, 8, 10, 12, 15, 20] as $overs)
                            <button type="button" data-overs="{{ $overs }}" class="inline-flex min-h-9 min-w-9 items-center justify-center rounded-lg border border-slate-300 bg-white px-2 text-[12px] font-semibold text-slate-600 transition hover:border-brand hover:text-brand">{{ $overs }}</button>
                        @endforeach
                    </div>
                </div>
                <x-form.select
                    name="match_stage"
                    label="Match stage"
                    placeholder="Not set"
                    :options="collect($stages)->mapWithKeys(fn ($stage) => [$stage => ucwords(str_replace('_', ' ', $stage))])"
                    :value="$match->match_stage ?? ''"
                />
            </div>
        </div>
    </section>

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

    <section class="ops-card">
        <div class="ops-card-head"><h3 class="ops-title">Match Reminder</h3></div>
        <div class="ops-card-body">
            @if($reminderAlreadySent)
                <p class="rounded-lg bg-slate-50 px-3 py-2 text-[12px] text-slate-500">
                    A reminder for this match has already been sent.
                </p>
            @else
                <label class="flex min-h-10 items-center gap-2 text-[13px] font-medium text-slate-700">
                    <input
                        type="checkbox"
                        name="reminder_enabled"
                        value="1"
                        {{ $reminderChecked ? 'checked' : '' }}
                        class="h-4 w-4 rounded border-slate-300"
                    />
                    Send reminder before match
                </label>

                <div class="mt-2 max-w-48">
                    <x-form.input
                        name="reminder_minutes_before"
                        label="Reminder before (minutes)"
                        type="number"
                        min="1"
                        max="1440"
                        :value="$match->reminder_minutes_before ?? old('reminder_minutes_before', 30)"
                    />
                </div>
                <p class="-mt-2 text-[11px] text-slate-400">
                    Only used when "Send reminder before match" is checked.
                </p>
                @error('reminder_minutes_before')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            @endif
        </div>
    </section>
</div>

<script>
    (function () {
        document.querySelectorAll('[data-overs-picks] [data-overs]').forEach(function (button) {
            button.addEventListener('click', function () {
                document.getElementById('overs_per_innings').value = button.dataset.overs;
            });
        });

        // Choosing the edition narrows the two team lists to that season's teams
        // (their labels start with "<edition> — "). Nothing is sent that was not already allowed.
        var edition = document.getElementById('edition_id');
        var teamSelects = ['edition_team_a_id', 'edition_team_b_id'].map(function (id) { return document.getElementById(id); }).filter(Boolean);
        if (!edition || edition.tagName !== 'SELECT' || !teamSelects.length) { return; }

        var all = teamSelects.map(function (select) {
            return Array.prototype.map.call(select.options, function (option) { return option.cloneNode(true); });
        });

        function narrow() {
            var name = edition.options[edition.selectedIndex] && edition.value ? edition.options[edition.selectedIndex].text.trim() : '';
            teamSelects.forEach(function (select, index) {
                var keep = select.value;
                select.innerHTML = '';
                all[index].forEach(function (option) {
                    if (!option.value || !name || option.text.indexOf(name + ' — ') === 0) {
                        select.appendChild(option.cloneNode(true));
                    }
                });
                select.value = keep;
                if (select.value !== keep) { select.value = ''; }
            });
        }

        edition.addEventListener('change', narrow);
        narrow();
    })();
</script>
