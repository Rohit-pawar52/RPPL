@extends('layouts.admin')

@section('title', $editionTeam->team->name.' squad')

@section('content')
    <nav aria-label="Season" class="mb-3 flex flex-wrap items-center gap-1.5 text-xs text-slate-500">
        <a href="{{ route('admin.editions.index') }}" class="hover:text-slate-800 hover:underline">Editions</a>
        <span class="text-slate-300" aria-hidden="true">&rsaquo;</span>
        <a href="{{ route('admin.editions.show', $edition) }}" class="hover:text-slate-800 hover:underline">{{ $edition->name }}</a>
        <span class="text-slate-300" aria-hidden="true">&rsaquo;</span>
        <a href="{{ route('admin.editions.squads.index', $edition) }}" class="hover:text-slate-800 hover:underline">Squads</a>
        <span class="text-slate-300" aria-hidden="true">&rsaquo;</span>
        <span class="font-medium text-slate-800" aria-current="page">{{ $editionTeam->team->name }}</span>
    </nav>

    @php
        $roleLabels = collect($roles)->mapWithKeys(fn ($role) => [$role => ucwords(str_replace('_', ' ', $role))]);
        $cardTitle = $editionTeam->team->name.' · '.$squad->count().' '.\Illuminate\Support\Str::plural('player', $squad->count()).($soldTotal > 0 ? ' · bought for '.money($soldTotal) : '');
    @endphp

    <div class="grid items-start gap-4 lg:grid-cols-[1fr_22rem]">
        {{-- The squad: jersey, role and sold amount are edited right in the list --}}
        <form method="POST" action="{{ route('admin.editions.squads.update', [$edition, $editionTeam]) }}" novalidate>
            @csrf
            @method('PUT')
            <x-admin.card :title="$cardTitle" flush>
                <table class="w-full text-left text-[13px]">
                    <thead class="border-b border-slate-200 bg-slate-50 text-[11px] uppercase tracking-wide text-slate-400">
                        <tr>
                            <th class="px-4 py-2 font-medium">Player</th>
                            <th class="px-2 py-2 font-medium">Jersey</th>
                            <th class="px-2 py-2 font-medium">Role</th>
                            <th class="px-2 py-2 font-medium">Bought for</th>
                            <th class="px-4 py-2"><span class="sr-only">Remove</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($squad as $teamPlayer)
                            <tr>
                                <td class="px-4 py-1">
                                    <a href="{{ route('admin.players.show', $teamPlayer->playerRegistration->player) }}" class="font-medium text-slate-800 hover:underline">{{ $teamPlayer->playerRegistration->player->name }}</a>
                                    @if($teamPlayer->playerRegistration->payment_status !== 'paid')
                                        <span class="ml-1 text-[11px] text-amber-700">{{ $teamPlayer->playerRegistration->payment_status }}</span>
                                    @endif
                                </td>
                                <td class="px-2 py-1">
                                    <input type="number" min="1" max="999" name="players[{{ $teamPlayer->id }}][jersey_number]" value="{{ old('players.'.$teamPlayer->id.'.jersey_number', $teamPlayer->jersey_number) }}" aria-label="Jersey number" class="h-8 w-16 rounded border border-slate-300 px-2 text-[13px] focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-100" />
                                </td>
                                <td class="px-2 py-1">
                                    <select name="players[{{ $teamPlayer->id }}][role]" aria-label="Role" class="h-8 rounded border border-slate-300 bg-white px-1.5 text-[13px] focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-100">
                                        <option value="">—</option>
                                        @foreach($roleLabels as $value => $label)
                                            <option value="{{ $value }}" @selected(old('players.'.$teamPlayer->id.'.role', $teamPlayer->role) === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="px-2 py-1">
                                    <input type="number" min="0" step="0.01" name="players[{{ $teamPlayer->id }}][sold_amount]" value="{{ old('players.'.$teamPlayer->id.'.sold_amount', $teamPlayer->sold_amount) }}" aria-label="Bought for" placeholder="—" class="h-8 w-28 rounded border border-slate-300 px-2 text-[13px] focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-100" />
                                </td>
                                <td class="px-4 py-1 text-right">
                                    @if($teamPlayer->match_players_count > 0)
                                        <span class="text-[11px] text-slate-400" title="Has played a match">Played</span>
                                    @else
                                        <button
                                            type="submit"
                                            form="remove-player-{{ $teamPlayer->id }}"
                                            title="Remove from squad"
                                            aria-label="Remove {{ $teamPlayer->playerRegistration->player->name }} from the squad"
                                            class="rounded p-1.5 text-slate-500 hover:bg-red-50 hover:text-red-600"
                                        ><x-icon name="trash" class="h-4 w-4" /></button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <x-admin.empty table colspan="5">No players in this squad yet &mdash; add them on the right.</x-admin.empty>
                        @endforelse
                    </tbody>
                </table>
                @if($errors->has('players') || $errors->has('players.*'))
                    <div class="border-t border-red-100 bg-red-50 px-4 py-2 text-xs text-red-700">
                        @foreach($errors->get('players') as $message){{ $message }} @endforeach
                        @foreach($errors->get('players.*') as $messages)@foreach($messages as $message){{ $message }} @endforeach @endforeach
                    </div>
                @endif
                @if($squad->isNotEmpty())
                    <div class="border-t border-slate-100 px-4 py-2.5">
                        <x-admin.button type="submit">Save squad</x-admin.button>
                    </div>
                @endif
            </x-admin.card>
        </form>

        {{-- The remove forms live outside the save form: forms cannot nest. --}}
        @foreach($squad->where('match_players_count', 0) as $teamPlayer)
            <form id="remove-player-{{ $teamPlayer->id }}" method="POST" action="{{ route('admin.editions.squads.destroy', [$edition, $editionTeam, $teamPlayer]) }}"
                data-confirm-delete data-confirm-title="Remove {{ $teamPlayer->playerRegistration->player->name }} from this squad?" data-confirm-text="Their registration stays; they can be added to a team again.">
                @csrf
                @method('DELETE')
            </form>
        @endforeach

        {{-- Add players --}}
        <div class="space-y-4">
            @unless($canAdd)
                <p class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">This season is completed, so new players cannot be added.</p>
            @else
                <x-admin.card :title="'Add players ('.$available->count().' without a team)'">
                    @if($available->isEmpty())
                        <p class="text-xs text-slate-500">Every registered player of this season is already in a team.</p>
                    @else
                        <form method="POST" action="{{ route('admin.editions.squads.store', [$edition, $editionTeam]) }}">
                            @csrf
                            <input type="search" id="player-filter" placeholder="Search players&hellip;" class="mb-2 h-8 w-full rounded-md border border-slate-300 px-2.5 text-[13px] focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-100" />
                            <label class="mb-1 flex items-center gap-2 border-b border-slate-100 pb-1.5 text-xs text-slate-500">
                                <input type="checkbox" id="player-select-all" class="h-4 w-4 rounded border-slate-300 text-green-600" />
                                Select all shown
                            </label>
                            <div class="max-h-80 overflow-y-auto">
                                @foreach($available as $registration)
                                    <div class="flex items-center gap-2 rounded px-1 py-1 text-[13px] hover:bg-slate-50" data-player-option="{{ mb_strtolower($registration->player->name.' '.$registration->player->phone) }}">
                                        <label class="flex min-w-0 flex-1 cursor-pointer items-center gap-2 text-slate-700">
                                            <input type="checkbox" name="add[{{ $registration->id }}]" value="1" @checked(old('add.'.$registration->id)) class="h-4 w-4 shrink-0 rounded border-slate-300 text-green-600" data-player-box />
                                            <span class="truncate">{{ $registration->player->name }}</span>
                                            @if($registration->payment_status !== 'paid')<span class="text-[11px] text-amber-700">{{ $registration->payment_status }}</span>@endif
                                        </label>
                                        <input type="number" min="0" step="0.01" name="amount[{{ $registration->id }}]" value="{{ old('amount.'.$registration->id) }}" placeholder="Amount" aria-label="Bought for" class="hidden h-7 w-24 rounded border border-slate-300 px-2 text-[12px] focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-100" data-player-amount />
                                    </div>
                                @endforeach
                            </div>
                            @error('add')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            @error('amount.*')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            <x-admin.button type="submit" class="mt-3 w-full">Add ticked players</x-admin.button>
                        </form>
                    @endif
                </x-admin.card>

                <x-admin.card title="Registered offline? Add a new player">
                    <form method="POST" action="{{ route('admin.editions.squads.store-offline', [$edition, $editionTeam]) }}" novalidate>
                        @csrf
                        @php $bag = $errors->offlinePlayer; @endphp
                        <div class="mb-2.5">
                            <label for="op-name" class="mb-1 block text-xs font-medium text-slate-700">Name <span class="text-red-500" aria-hidden="true">*</span></label>
                            <input id="op-name" name="name" value="{{ old('name') }}" required class="h-9 w-full rounded-md border px-2.5 text-[13px] focus:outline-none focus:ring-2 {{ $bag->has('name') ? 'border-red-400 focus:ring-red-100' : 'border-slate-300 focus:border-green-500 focus:ring-green-100' }}" />
                            @if($bag->has('name'))<p class="mt-1 text-xs text-red-600">{{ $bag->first('name') }}</p>@endif
                        </div>
                        <div class="mb-2.5">
                            <label for="op-phone" class="mb-1 block text-xs font-medium text-slate-700">Mobile number <span class="text-red-500" aria-hidden="true">*</span></label>
                            <input id="op-phone" name="phone" type="tel" inputmode="tel" value="{{ old('phone') }}" required class="h-9 w-full rounded-md border px-2.5 text-[13px] focus:outline-none focus:ring-2 {{ $bag->has('phone') ? 'border-red-400 focus:ring-red-100' : 'border-slate-300 focus:border-green-500 focus:ring-green-100' }}" />
                            @if($bag->has('phone'))<p class="mt-1 text-xs text-red-600">{{ $bag->first('phone') }}</p>@endif
                        </div>
                        <div class="mb-3 grid grid-cols-2 gap-2">
                            <div>
                                <label for="op-amount" class="mb-1 block text-xs font-medium text-slate-700">Bought for</label>
                                <input id="op-amount" name="sold_amount" type="number" min="0" step="0.01" value="{{ old('sold_amount') }}" class="h-9 w-full rounded-md border border-slate-300 px-2.5 text-[13px] focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-100" />
                                @if($bag->has('sold_amount'))<p class="mt-1 text-xs text-red-600">{{ $bag->first('sold_amount') }}</p>@endif
                            </div>
                            <div>
                                <label for="op-payment" class="mb-1 block text-xs font-medium text-slate-700">Payment</label>
                                <select id="op-payment" name="payment_status" class="h-9 w-full rounded-md border border-slate-300 bg-white px-2 text-[13px] focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-100">
                                    <option value="paid" @selected(old('payment_status', 'paid') === 'paid')>Paid</option>
                                    <option value="pending" @selected(old('payment_status') === 'pending')>Pending</option>
                                </select>
                            </div>
                        </div>
                        <x-admin.button type="submit" variant="secondary" class="w-full">Register and add to squad</x-admin.button>
                    </form>
                </x-admin.card>
            @endunless
        </div>
    </div>

    <script>
        (function () {
            var filter = document.getElementById('player-filter');
            var all = document.getElementById('player-select-all');
            var options = Array.prototype.slice.call(document.querySelectorAll('[data-player-option]'));

            if (!filter || !all) return;

            // The amount box appears only beside a ticked player.
            function sync(option) {
                var on = option.querySelector('[data-player-box]').checked;
                option.querySelector('[data-player-amount]').classList.toggle('hidden', !on);
            }

            options.forEach(function (option) {
                sync(option);
                option.querySelector('[data-player-box]').addEventListener('change', function () { sync(option); });
            });

            filter.addEventListener('input', function () {
                var q = filter.value.trim().toLowerCase();
                options.forEach(function (o) { o.hidden = q !== '' && o.dataset.playerOption.indexOf(q) === -1; });
                all.checked = false;
            });

            all.addEventListener('change', function () {
                options.filter(function (o) { return !o.hidden; }).forEach(function (o) {
                    o.querySelector('[data-player-box]').checked = all.checked;
                    sync(o);
                });
            });
        })();
    </script>
@endsection
