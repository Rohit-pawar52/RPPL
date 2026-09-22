@extends('layouts.admin')

@section('title', 'Score Innings')

@section('content')
    <div class="mb-4">
        <a href="{{ route('admin.matches.show', $match) }}" class="text-xs text-neutral-500 hover:text-neutral-700">
            &larr; Back to match
        </a>
    </div>

    <div class="rounded-lg border border-neutral-200 bg-white p-4">
        <div class="flex items-center justify-between gap-3">
            <h2 class="text-base font-semibold text-neutral-900">
                {{ $match->teamA->team->name }} vs {{ $match->teamB->team->name }}
            </h2>
            <x-status-badge :status="$innings->status" />
        </div>
        <p class="mt-1 text-xs text-neutral-500">
            {{ $match->edition->name }}
            @if($match->match_number)
                &middot; Match {{ $match->match_number }}
            @endif
            &middot; Innings {{ $innings->innings_number }}
        </p>

        <p class="mt-3 text-2xl font-semibold text-neutral-900">
            {{ $innings->total_runs }}/{{ $innings->total_wickets }}
            <span class="text-sm font-normal text-neutral-500">({{ $innings->oversDisplay() }} overs)</span>
        </p>
        <p class="mt-1 text-xs text-neutral-500">
            {{ $innings->battingTeam->team->name }} batting &middot; {{ $innings->bowlingTeam->team->name }} bowling
        </p>
    </div>

    @unless($canRecordDelivery)
        <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-2.5 text-xs text-amber-700">
            @if($isOverLimitReached)
                Over limit reached for this innings. Complete the innings from the match page, or use Undo Last Delivery to make a correction.
            @else
                This innings can no longer be scored.
            @endif
        </div>
    @endunless

    @if($canRecordDelivery)
        <div class="mt-4 rounded-lg border border-neutral-200 bg-white p-4">
            <h3 class="mb-3 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">Record Delivery</h3>

            @error('delivery')
                <p class="mb-3 rounded-md bg-red-50 px-3 py-2 text-xs text-red-600">{{ $message }}</p>
            @enderror

            <form method="POST" action="{{ route('admin.matches.innings.deliveries.store', [$match, $innings]) }}" novalidate>
                @csrf

                @php
                    $battingOptions = $battingMatchPlayers->mapWithKeys(fn ($mp) => [
                        $mp->id => $mp->teamPlayer->playerRegistration->player->name . ($mp->teamPlayer->jersey_number ? ' (#'.$mp->teamPlayer->jersey_number.')' : ''),
                    ]);
                    $bowlingOptions = $bowlingMatchPlayers->mapWithKeys(fn ($mp) => [
                        $mp->id => $mp->teamPlayer->playerRegistration->player->name . ($mp->teamPlayer->jersey_number ? ' (#'.$mp->teamPlayer->jersey_number.')' : ''),
                    ]);

                    // Server-rendered preselection only (Phase 3.33) — no
                    // JS state management. On a wicket, only the
                    // surviving batter's end can be preselected; the
                    // vacant end is left for the scorer to choose a new,
                    // not-yet-dismissed batter.
                    $expectedStrikerId = null;
                    $expectedNonStrikerId = null;

                    if (! $expectedBattingState['first_ball']) {
                        if ($expectedBattingState['requires_replacement']) {
                            if ($expectedBattingState['survivor_end'] === 'striker') {
                                $expectedStrikerId = $expectedBattingState['survivor_id'];
                            } else {
                                $expectedNonStrikerId = $expectedBattingState['survivor_id'];
                            }
                        } else {
                            $expectedStrikerId = $expectedBattingState['striker_id'];
                            $expectedNonStrikerId = $expectedBattingState['non_striker_id'];
                        }
                    }
                @endphp

                @if($expectedBattingState['requires_replacement'])
                    <p class="mb-3 rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-700">
                        A batter is out — select the new batter for the vacant end below.
                    </p>
                @endif

                <div class="grid gap-4 sm:grid-cols-3">
                    <x-form.select name="striker_match_player_id" label="Striker" placeholder="Select striker" :options="$battingOptions" :value="old('striker_match_player_id', $expectedStrikerId)" />
                    <x-form.select name="non_striker_match_player_id" label="Non-striker" placeholder="Select non-striker" :options="$battingOptions" :value="old('non_striker_match_player_id', $expectedNonStrikerId)" />
                    <x-form.select name="bowler_match_player_id" label="Bowler" placeholder="Select bowler" :options="$bowlingOptions" />
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <x-form.input name="runs_off_bat" label="Runs off bat" type="number" min="0" max="6" :value="0" />

                    <x-form.select
                        name="extra_type"
                        label="Extra"
                        placeholder="None"
                        :options="collect($extraTypes)->mapWithKeys(fn ($type) => [$type => ucwords(str_replace('_', ' ', $type))])"
                    />

                    <div id="extra-amount-wrapper" hidden>
                        <x-form.input name="extra_amount" label="Extra runs" type="number" min="1" max="7" />
                    </div>
                </div>

                <label class="mb-3.5 flex items-center gap-2 text-xs font-medium text-neutral-700">
                    <input type="checkbox" id="is_wicket" name="is_wicket" value="1" class="rounded border-neutral-300" @checked(old('is_wicket')) />
                    Wicket
                </label>

                <div id="wicket-fields" class="grid gap-4 sm:grid-cols-3" hidden>
                    <x-form.select name="dismissed_match_player_id" label="Dismissed player" placeholder="Select dismissed player" :options="$battingOptions" />
                    <x-form.select
                        name="wicket_type"
                        label="Dismissal type"
                        placeholder="Select dismissal type"
                        :options="collect($wicketTypes)->mapWithKeys(fn ($type) => [$type => ucwords(str_replace('_', ' ', $type))])"
                    />
                    <x-form.select name="fielder_match_player_id" label="Fielder (if applicable)" placeholder="None" :options="$bowlingOptions" />
                </div>

                <div class="mb-3.5">
                    <label for="commentary" class="mb-1 block text-xs font-medium text-neutral-700">Commentary</label>
                    <textarea
                        id="commentary"
                        name="commentary"
                        rows="2"
                        class="w-full rounded-md border px-3 py-2 text-[13px] focus:outline-none focus:ring-2 {{ $errors->has('commentary') ? 'border-red-400 focus:ring-red-100' : 'border-neutral-300 theme-focus-ring' }}"
                    >{{ old('commentary') }}</textarea>
                    @error('commentary')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="rounded-md theme-button px-4 py-2 text-[13px] font-medium">
                    Record Delivery
                </button>
            </form>
        </div>
    @endif

    <div class="mt-4 rounded-lg border border-neutral-200 bg-white p-4">
        <div class="mb-3 flex items-center justify-between">
            <h3 class="text-[11px] font-semibold uppercase tracking-wide text-neutral-400">Recent Deliveries</h3>

            @if($canRecordDelivery && $recentDeliveries->isNotEmpty())
                <form
                    method="POST"
                    action="{{ route('admin.matches.innings.deliveries.undo-latest', [$match, $innings]) }}"
                    onsubmit="event.preventDefault(); window.confirmAction({title: 'Undo the last delivery?', confirmButtonText: 'Yes, undo'}).then((result) => { if (result.isConfirmed) { this.submit(); } });"
                >
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-md border border-neutral-200 px-2.5 py-1.5 text-xs font-medium text-neutral-600 hover:bg-neutral-50">
                        <x-icon name="undo" class="h-3.5 w-3.5" />
                        Undo Last Delivery
                    </button>
                </form>
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[720px] text-left text-[13px]">
                <thead class="border-b border-neutral-200 text-[11px] uppercase tracking-wide text-neutral-400">
                    <tr>
                        <th class="px-2 py-1.5 font-medium">Ball</th>
                        <th class="px-2 py-1.5 font-medium">Batter</th>
                        <th class="px-2 py-1.5 font-medium">Bowler</th>
                        <th class="px-2 py-1.5 font-medium">Runs</th>
                        <th class="px-2 py-1.5 font-medium">Extra</th>
                        <th class="px-2 py-1.5 font-medium">Wicket</th>
                        <th class="px-2 py-1.5 font-medium">Commentary</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-neutral-100">
                    @forelse($recentDeliveries as $delivery)
                        <tr>
                            <td class="whitespace-nowrap px-2 py-1.5 text-neutral-500">{{ $delivery->over_number }}.{{ $delivery->ball_number }}</td>
                            <td class="px-2 py-1.5 text-neutral-700">{{ $delivery->striker->teamPlayer->playerRegistration->player->name }}</td>
                            <td class="px-2 py-1.5 text-neutral-700">{{ $delivery->bowler->teamPlayer->playerRegistration->player->name }}</td>
                            <td class="px-2 py-1.5 text-neutral-700">{{ $delivery->total_runs }}</td>
                            <td class="px-2 py-1.5 text-neutral-500">
                                @if($delivery->wide_runs)
                                    Wide
                                @elseif($delivery->no_ball_runs)
                                    No-ball
                                @elseif($delivery->bye_runs)
                                    Bye
                                @elseif($delivery->leg_bye_runs)
                                    Leg-bye
                                @else
                                    &mdash;
                                @endif
                            </td>
                            <td class="px-2 py-1.5 text-neutral-700">
                                @if($delivery->is_wicket)
                                    {{ ucwords(str_replace('_', ' ', $delivery->wicket_type)) }} ({{ $delivery->dismissedPlayer->teamPlayer->playerRegistration->player->name }})
                                @else
                                    &mdash;
                                @endif
                            </td>
                            <td class="px-2 py-1.5 text-neutral-500">{{ $delivery->commentary ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-2 py-6 text-center text-neutral-400">No deliveries recorded yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const wicketCheckbox = document.getElementById('is_wicket');
            const wicketFields = document.getElementById('wicket-fields');

            if (wicketCheckbox && wicketFields) {
                const toggleWicket = () => { wicketFields.hidden = !wicketCheckbox.checked; };
                wicketCheckbox.addEventListener('change', toggleWicket);
                toggleWicket();
            }

            const extraSelect = document.getElementById('extra_type');
            const extraAmountWrapper = document.getElementById('extra-amount-wrapper');

            if (extraSelect && extraAmountWrapper) {
                const toggleExtra = () => { extraAmountWrapper.hidden = !extraSelect.value; };
                extraSelect.addEventListener('change', toggleExtra);
                toggleExtra();
            }
        });
    </script>
@endsection
