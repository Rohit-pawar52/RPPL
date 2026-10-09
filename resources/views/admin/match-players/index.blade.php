@extends('layouts.admin')

@section('title', 'Playing XI')

@section('content')
    <div class="mb-3">
        <a href="{{ route('admin.matches.show', $match) }}" class="ops-back">
            <x-ops.icon name="arrow-left" class="h-3.5 w-3.5" />
            Back to match
        </a>
    </div>

    <header class="ops-card ops-card-body">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-lg font-bold tracking-tight text-slate-900 sm:text-xl">
                {{ $match->teamA->team->name }} vs {{ $match->teamB->team->name }} &mdash; Playing XI
            </h2>
            <x-status-badge :status="$match->match_status" />
        </div>
        <p class="mt-1 text-xs text-slate-500">
            {{ $match->edition->name }}
            &middot;
            {{ display_datetime($match->scheduled_at, 'd M Y, h:i A') }}
        </p>
        @if($canModify)
            <p class="mt-3 text-xs text-slate-500">Tick 11 players for each team and save that team. Captain and wicketkeeper are set after saving.</p>
        @endif
    </header>

    @unless($canModify)
        <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-800">
            The Playing XI for this match is locked and can no longer be changed.
        </div>
    @endunless

    <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
        @include('admin.match-players._team', [
            'match' => $match,
            'team' => $match->teamA->team,
            'editionTeamId' => $match->edition_team_a_id,
            'selected' => $teamASelected,
            'squad' => $teamASquad,
            'autoSelectIds' => $teamAAutoSelectIds,
            'canModify' => $canModify,
            'panelId' => 'team-a',
        ])

        @include('admin.match-players._team', [
            'match' => $match,
            'team' => $match->teamB->team,
            'editionTeamId' => $match->edition_team_b_id,
            'selected' => $teamBSelected,
            'squad' => $teamBSquad,
            'autoSelectIds' => $teamBAutoSelectIds,
            'canModify' => $canModify,
            'panelId' => 'team-b',
        ])
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('[data-xi-panel]').forEach((panel) => {
                const maxPlayers = 11;
                const checkboxes = () => Array.from(panel.querySelectorAll('.xi-checkbox'));
                const counterEl = panel.querySelector('[data-xi-counter]');
                const saveButton = panel.querySelector('[data-xi-save]');
                const searchInput = panel.querySelector('[data-xi-search]');
                const rows = () => Array.from(panel.querySelectorAll('[data-xi-row]'));
                const autoSelectIds = JSON.parse(panel.dataset.autoSelectIds || '[]').map(String);

                function selectedCount() {
                    return checkboxes().filter((cb) => cb.checked).length;
                }

                function refreshCounterAndSave() {
                    const count = selectedCount();

                    if (counterEl) counterEl.textContent = `${count} / ${maxPlayers} selected`;

                    if (saveButton) saveButton.disabled = count !== maxPlayers;
                }

                function notifyMax() {
                    if (window.Swal) {
                        window.Swal.fire({
                            icon: 'warning',
                            text: 'Maximum 11 players can be selected.',
                            toast: true,
                            position: 'top-end',
                            timer: 2200,
                            showConfirmButton: false,
                        });
                    } else {
                        window.alert('Maximum 11 players can be selected.');
                    }
                }

                checkboxes().forEach((checkbox) => {
                    checkbox.addEventListener('change', () => {
                        if (checkbox.checked && selectedCount() > maxPlayers) {
                            checkbox.checked = false;
                            notifyMax();

                            return;
                        }

                        refreshCounterAndSave();
                    });
                });

                const autoSelectButton = panel.querySelector('[data-xi-auto-select]');
                if (autoSelectButton) {
                    autoSelectButton.addEventListener('click', () => {
                        checkboxes().forEach((cb) => {
                            cb.checked = autoSelectIds.includes(cb.value);
                        });
                        refreshCounterAndSave();
                    });
                }

                const clearButton = panel.querySelector('[data-xi-clear]');
                if (clearButton) {
                    clearButton.addEventListener('click', () => {
                        checkboxes().forEach((cb) => { cb.checked = false; });
                        refreshCounterAndSave();
                    });
                }

                if (searchInput) {
                    searchInput.addEventListener('input', () => {
                        const term = searchInput.value.trim().toLowerCase();

                        rows().forEach((row) => {
                            const name = (row.dataset.playerName || '').toLowerCase();
                            row.hidden = term.length > 0 && !name.includes(term);
                        });
                    });
                }

                refreshCounterAndSave();
            });
        });
    </script>
@endsection
