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

<section class="ops-card" id="{{ $panelId }}">
    @if($canModify)
        <form
            method="POST"
            action="{{ route('admin.matches.players.sync', $match) }}"
            data-xi-panel
            data-auto-select-ids="{{ json_encode($autoSelectIds) }}"
        >
            @csrf
            <input type="hidden" name="edition_team_id" value="{{ $editionTeamId }}" />

            <div class="ops-card-head">
                <h3 class="ops-title">{{ $team->name }}</h3>
                <span data-xi-counter class="ops-pill ops-pill-slate text-xs tabular-nums">{{ __(':count / :max selected', ['count' => count($preselectIds), 'max' => 11]) }}</span>
            </div>

            <div class="p-3 sm:p-4">
                <div class="relative">
                    <x-ops.icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input
                        type="search"
                        data-xi-search
                        placeholder="{{ __('Search squad by name…') }}"
                        aria-label="{{ __('Search :team squad', ['team' => $team->name]) }}"
                        class="ops-input pl-9"
                    />
                </div>

                <ul class="mt-3 max-h-96 divide-y divide-line overflow-y-auto rounded-xl border border-line">
                    @forelse($squad as $teamPlayer)
                        @php
                            $player = $teamPlayer->playerRegistration->player;
                            $existingSelection = $selectedByTeamPlayerId->get($teamPlayer->id);
                            $checked = in_array($teamPlayer->id, $preselectIds, true);
                        @endphp
                        <li data-xi-row data-player-name="{{ $player->name }}">
                            <label class="flex min-h-14 cursor-pointer items-center gap-3 px-3 py-2 transition hover:bg-hover has-[:checked]:bg-brand-soft">
                                <input
                                    type="checkbox"
                                    name="team_player_ids[]"
                                    value="{{ $teamPlayer->id }}"
                                    class="xi-checkbox h-5 w-5 shrink-0 rounded border-slate-300"
                                    @checked($checked)
                                />
                                <x-media-image :path="$player->photo_path" kind="user" alt="" loading="lazy" class="h-9 w-9 shrink-0 rounded-full bg-slate-100 object-cover" />
                                <span class="min-w-0 flex-1">
                                    <span class="flex items-center gap-1.5 truncate text-[13px] font-semibold text-slate-800">
                                        {{ $player->name }}
                                        @if($teamPlayer->jersey_number)
                                            <span class="font-medium tabular-nums text-slate-400">#{{ $teamPlayer->jersey_number }}</span>
                                        @endif
                                        @if($existingSelection?->is_captain)
                                            <x-icon name="star" class="h-3.5 w-3.5 text-amber-500" />
                                        @endif
                                        @if($existingSelection?->is_wicket_keeper)
                                            <x-icon name="glove" class="h-3.5 w-3.5 text-sky-600" />
                                        @endif
                                        @unless($player->is_active)
                                            <span class="rounded bg-slate-100 px-1 text-[10px] font-medium text-slate-500">{{ __('Inactive') }}</span>
                                        @endunless
                                    </span>
                                    <span class="block text-[11px] capitalize text-slate-400">
                                        {{ $teamPlayer->role ? __(str_replace('_', ' ', $teamPlayer->role)) : __('No squad role') }}
                                    </span>
                                </span>
                            </label>
                        </li>
                    @empty
                        <li class="py-6 text-center text-xs text-slate-400">{{ __('This team has no squad players yet.') }}</li>
                    @endforelse
                </ul>

                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <button type="button" data-xi-auto-select class="btn btn-secondary btn-sm min-h-10">
                        {{ __('Auto Select 11') }}
                    </button>
                    <button type="button" data-xi-clear class="btn btn-ghost btn-sm min-h-10">
                        {{ __('Clear') }}
                    </button>
                    <button
                        type="submit"
                        data-xi-save
                        class="btn btn-primary ml-auto min-h-10"
                    >
                        {{ __('Save Playing XI') }}
                    </button>
                </div>
            </div>
        </form>
    @else
        <div class="ops-card-head">
            <h3 class="ops-title">{{ $team->name }}</h3>
            <span class="ops-pill ops-pill-slate text-xs tabular-nums">{{ __(':count / :max selected', ['count' => $selected->count(), 'max' => 11]) }}</span>
        </div>
    @endif

    @if($hasSavedXi)
        <div class="border-t border-line px-3 py-3 sm:px-4">
            <h4 class="ops-kicker mb-1">{{ __('Current Playing XI') }}</h4>
            <ul class="divide-y divide-line">
                @foreach($selected as $matchPlayer)
                    @php $player = $matchPlayer->teamPlayer->playerRegistration->player; @endphp
                    <li class="flex items-center justify-between gap-2 py-1.5">
                        <p class="truncate text-[13px] text-slate-700">
                            {{ $player->name }}
                            @if($matchPlayer->teamPlayer->jersey_number)
                                <span class="text-slate-400">#{{ $matchPlayer->teamPlayer->jersey_number }}</span>
                            @endif
                        </p>

                        <div class="flex shrink-0 items-center gap-1">
                            @if($matchPlayer->is_captain)
                                <span title="{{ __('Captain') }}" class="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-50 text-amber-500">
                                    <x-icon name="star" class="h-4 w-4" />
                                </span>
                            @elseif($canModify)
                                <form method="POST" action="{{ route('admin.matches.players.update', [$match, $matchPlayer]) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="designation" value="captain" />
                                    <button type="submit" title="{{ __('Make captain') }}" aria-label="{{ __('Make captain') }}" class="flex h-10 w-10 items-center justify-center rounded-lg text-slate-300 transition hover:bg-amber-50 hover:text-amber-500">
                                        <x-icon name="star" class="h-4 w-4" />
                                    </button>
                                </form>
                            @endif

                            @if($matchPlayer->is_wicket_keeper)
                                <span title="{{ __('Wicketkeeper') }}" class="flex h-10 w-10 items-center justify-center rounded-lg bg-sky-50 text-sky-600">
                                    <x-icon name="glove" class="h-4 w-4" />
                                </span>
                            @elseif($canModify)
                                <form method="POST" action="{{ route('admin.matches.players.update', [$match, $matchPlayer]) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="designation" value="wicket_keeper" />
                                    <button type="submit" title="{{ __('Make wicketkeeper') }}" aria-label="{{ __('Make wicketkeeper') }}" class="flex h-10 w-10 items-center justify-center rounded-lg text-slate-300 transition hover:bg-sky-50 hover:text-sky-600">
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
        <p class="py-6 text-center text-xs text-slate-400">{{ __('No players were selected for this team.') }}</p>
    @endif
</section>
