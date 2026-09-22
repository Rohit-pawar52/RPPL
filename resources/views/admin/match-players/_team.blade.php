{{-- Expects: $match, $team, $editionTeamId, $selected (Collection<MatchPlayer>), $eligible (Collection<TeamPlayer>), $canModify --}}
<div class="rounded-lg border border-neutral-200 bg-white p-4">
    <div class="mb-3 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-neutral-900">{{ $team->name }}</h3>
        <span class="text-xs text-neutral-500">Selected: {{ $selected->count() }}</span>
    </div>

    <ul class="divide-y divide-neutral-100">
        @forelse($selected as $matchPlayer)
            @php $player = $matchPlayer->teamPlayer->playerRegistration->player; @endphp
            <li class="flex items-center justify-between gap-2 py-2">
                <div class="flex min-w-0 items-center gap-2">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-full border border-neutral-200 bg-neutral-50 text-neutral-300">
                        @if($player->photo_path)
                            <img
                                src="{{ Illuminate\Support\Facades\Storage::url($player->photo_path) }}"
                                alt="{{ $player->name }}"
                                class="h-full w-full object-cover"
                            />
                        @else
                            <x-icon name="user" class="h-4 w-4" />
                        @endif
                    </div>
                    <div class="min-w-0">
                        <p class="truncate text-[13px] font-medium text-neutral-800">
                            {{ $player->name }}
                            @if($matchPlayer->teamPlayer->jersey_number)
                                <span class="text-neutral-400">#{{ $matchPlayer->teamPlayer->jersey_number }}</span>
                            @endif
                        </p>
                        <p class="text-[11px] capitalize text-neutral-400">
                            {{ $matchPlayer->teamPlayer->role ? str_replace('_', ' ', $matchPlayer->teamPlayer->role) : 'No squad role' }}
                        </p>
                    </div>
                </div>

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

                    @if($canModify)
                        <form
                            method="POST"
                            action="{{ route('admin.matches.players.destroy', [$match, $matchPlayer]) }}"
                            data-confirm-delete
                            data-confirm-title="Remove this player from the Playing XI?"
                            data-confirm-text="This cannot be undone. Players with existing scoring history cannot be removed."
                        >
                            @csrf
                            @method('DELETE')
                            <button type="submit" title="Remove" aria-label="Remove from Playing XI" class="rounded p-1 text-neutral-400 hover:bg-red-50 hover:text-red-600">
                                <x-icon name="trash" class="h-4 w-4" />
                            </button>
                        </form>
                    @endif
                </div>
            </li>
        @empty
            <li class="py-4 text-center text-xs text-neutral-400">No players selected yet.</li>
        @endforelse
    </ul>

    @if($canModify)
        <form method="POST" action="{{ route('admin.matches.players.store', $match) }}" class="mt-3 flex items-end gap-2 border-t border-neutral-100 pt-3">
            @csrf
            <div class="flex-1">
                <x-form.select
                    name="team_player_id"
                    placeholder="{{ $eligible->isEmpty() ? 'No eligible players remaining' : 'Select a player to add' }}"
                    :options="$eligible->mapWithKeys(fn ($teamPlayer) => [
                        $teamPlayer->id => $teamPlayer->playerRegistration->player->name . ($teamPlayer->jersey_number ? ' (#'.$teamPlayer->jersey_number.')' : ''),
                    ])"
                />
            </div>
            <button
                type="submit"
                class="mb-3.5 shrink-0 rounded-md theme-button px-3 py-1.5 text-[13px] font-medium disabled:cursor-not-allowed disabled:bg-neutral-300"
                @disabled($eligible->isEmpty())
            >
                Add
            </button>
        </form>
    @endif
</div>
