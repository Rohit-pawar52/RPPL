{{--
    Expects: $match, $team, $editionTeamId, $selected (Collection<MatchPlayer>,
    keyed by nothing in particular), $squad (Collection<TeamPlayer>, full
    squad in jersey_number order), $autoSelectIds (list<int>), $canModify,
    $panelId (a short, unique-per-panel string for DOM ids/data attrs).
--}}
@php
    $selectedByTeamPlayerId = $selected->keyBy('team_player_id');
    $selectedIds = $selectedByTeamPlayerId->keys()->all();
    $hasSavedXi = $selected->isNotEmpty();
    $preselectIds = $hasSavedXi ? $selectedIds : $autoSelectIds;
@endphp

<div class="rounded-lg border border-neutral-200 bg-white p-4">
    <div class="mb-3 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-neutral-900">{{ $team->name }}</h3>
        @if($canModify)
            <span data-xi-counter class="text-xs font-medium text-neutral-500">{{ count($preselectIds) }} / 11 selected</span>
        @else
            <span class="text-xs text-neutral-500">{{ $selected->count() }} / 11 selected</span>
        @endif
    </div>

    @if($canModify)
        <form
            method="POST"
            action="{{ route('admin.matches.players.sync', $match) }}"
            data-xi-panel
            data-auto-select-ids="{{ json_encode($autoSelectIds) }}"
        >
            @csrf
            <input type="hidden" name="edition_team_id" value="{{ $editionTeamId }}" />

            <input
                type="search"
                data-xi-search
                placeholder="Search squad by name&hellip;"
                class="mb-2 w-full rounded-md border border-neutral-300 px-2.5 py-1.5 text-[13px] focus:outline-none focus:ring-2 theme-focus-ring"
            />

            <ul class="max-h-80 divide-y divide-neutral-100 overflow-y-auto rounded-md border border-neutral-100">
                @forelse($squad as $teamPlayer)
                    @php
                        $player = $teamPlayer->playerRegistration->player;
                        $existingSelection = $selectedByTeamPlayerId->get($teamPlayer->id);
                        $checked = in_array($teamPlayer->id, $preselectIds, true);
                    @endphp
                    <li data-xi-row data-player-name="{{ $player->name }}">
                        <label class="flex min-h-11 cursor-pointer items-center gap-2.5 px-2 py-1.5 hover:bg-neutral-50">
                            <input
                                type="checkbox"
                                name="team_player_ids[]"
                                value="{{ $teamPlayer->id }}"
                                class="xi-checkbox h-4 w-4 shrink-0 rounded border-neutral-300"
                                @checked($checked)
                            />
                            <span class="min-w-0 flex-1">
                                <span class="flex items-center gap-1 truncate text-[13px] font-medium text-neutral-800">
                                    {{ $player->name }}
                                    @if($teamPlayer->jersey_number)
                                        <span class="text-neutral-400">#{{ $teamPlayer->jersey_number }}</span>
                                    @endif
                                    @if($existingSelection?->is_captain)
                                        <x-icon name="star" class="h-3 w-3 text-amber-500" />
                                    @endif
                                    @if($existingSelection?->is_wicket_keeper)
                                        <x-icon name="glove" class="h-3 w-3 text-blue-500" />
                                    @endif
                                    @unless($player->is_active)
                                        <span class="rounded bg-neutral-100 px-1 text-[10px] font-medium text-neutral-500">Inactive</span>
                                    @endunless
                                </span>
                                <span class="block text-[11px] capitalize text-neutral-400">
                                    {{ $teamPlayer->role ? str_replace('_', ' ', $teamPlayer->role) : 'No squad role' }}
                                </span>
                            </span>
                        </label>
                    </li>
                @empty
                    <li class="py-4 text-center text-xs text-neutral-400">This team has no squad players yet.</li>
                @endforelse
            </ul>

            <div class="mt-3 flex flex-wrap items-center gap-2">
                <button type="button" data-xi-auto-select class="rounded-md border border-neutral-200 px-2.5 py-1.5 text-xs font-medium text-neutral-600 hover:bg-neutral-50">
                    Auto Select 11
                </button>
                <button type="button" data-xi-clear class="rounded-md border border-neutral-200 px-2.5 py-1.5 text-xs font-medium text-neutral-600 hover:bg-neutral-50">
                    Clear
                </button>
                <button
                    type="submit"
                    data-xi-save
                    class="ml-auto rounded-md theme-button px-3 py-1.5 text-[13px] font-medium disabled:cursor-not-allowed disabled:opacity-40"
                >
                    Save Playing XI
                </button>
            </div>
        </form>
    @endif

    @if($hasSavedXi)
        <div class="mt-4 border-t border-neutral-100 pt-3">
            <h4 class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">Current Playing XI</h4>
            <ul class="divide-y divide-neutral-100">
                @foreach($selected as $matchPlayer)
                    @php $player = $matchPlayer->teamPlayer->playerRegistration->player; @endphp
                    <li class="flex items-center justify-between gap-2 py-1.5">
                        <p class="truncate text-[13px] text-neutral-700">
                            {{ $player->name }}
                            @if($matchPlayer->teamPlayer->jersey_number)
                                <span class="text-neutral-400">#{{ $matchPlayer->teamPlayer->jersey_number }}</span>
                            @endif
                        </p>

                        <div class="flex shrink-0 items-center gap-1">
                            @if($matchPlayer->is_captain)
                                <span title="Captain" class="rounded p-1 text-amber-500">
                                    <x-icon name="star" class="h-4 w-4" />
                                </span>
                            @elseif($canModify)
                                <form method="POST" action="{{ route('admin.matches.players.update', [$match, $matchPlayer]) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="designation" value="captain" />
                                    <button type="submit" title="Make captain" aria-label="Make captain" class="rounded p-1 text-neutral-300 hover:bg-neutral-100 hover:text-amber-500">
                                        <x-icon name="star" class="h-4 w-4" />
                                    </button>
                                </form>
                            @endif

                            @if($matchPlayer->is_wicket_keeper)
                                <span title="Wicketkeeper" class="rounded p-1 text-blue-500">
                                    <x-icon name="glove" class="h-4 w-4" />
                                </span>
                            @elseif($canModify)
                                <form method="POST" action="{{ route('admin.matches.players.update', [$match, $matchPlayer]) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="designation" value="wicket_keeper" />
                                    <button type="submit" title="Make wicketkeeper" aria-label="Make wicketkeeper" class="rounded p-1 text-neutral-300 hover:bg-neutral-100 hover:text-blue-500">
                                        <x-icon name="glove" class="h-4 w-4" />
                                    </button>
                                </form>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    @elseif(! $canModify)
        <p class="py-4 text-center text-xs text-neutral-400">No players were selected for this team.</p>
    @endif
</div>
