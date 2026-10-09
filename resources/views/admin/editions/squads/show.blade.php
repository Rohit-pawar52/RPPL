@extends('layouts.admin')

@section('title', __(':name squad', ['name' => $editionTeam->team->name]))

@section('content')
    @php
        // This page opens with the teams permission, so the links to other modules are drawn only for a
        // role that may open them (editions list/hub, player profiles).
        $canOpenEditions = auth()->user()->can('viewAny', \App\Models\Edition::class);
        $canOpenPlayers = auth()->user()->can('viewAny', \App\Models\Player::class);
    @endphp

    <nav aria-label="{{ __('Season') }}" class="mb-3 flex flex-wrap items-center gap-1.5 text-xs text-slate-500">
        @if($canOpenEditions)
            <a href="{{ route('admin.editions.index') }}" class="hover:text-slate-800 hover:underline">{{ __('Editions') }}</a>
        @else
            <span>{{ __('Editions') }}</span>
        @endif
        <span class="text-slate-300" aria-hidden="true">&rsaquo;</span>
        @if($canOpenEditions)
            <a href="{{ route('admin.editions.show', $edition) }}" class="hover:text-slate-800 hover:underline">{{ $edition->name }}</a>
        @else
            <span>{{ $edition->name }}</span>
        @endif
        <span class="text-slate-300" aria-hidden="true">&rsaquo;</span>
        <a href="{{ route('admin.editions.squads.index', $edition) }}" class="hover:text-slate-800 hover:underline">{{ __('Squads') }}</a>
        <span class="text-slate-300" aria-hidden="true">&rsaquo;</span>
        <span class="font-medium text-slate-800" aria-current="page">{{ $editionTeam->team->name }}</span>
    </nav>

    @php
        $roleLabels = collect($roles)->mapWithKeys(fn ($role) => [$role => __(ucwords(str_replace('_', ' ', $role)))]);
        $cardTitle = $editionTeam->team->name.' · '.$squad->count().' '.($squad->count() === 1 ? __('player') : __('players')).($soldTotal > 0 ? ' · '.__('bought for :amount', ['amount' => points($soldTotal, true)]) : '');
        $field = 'ops-input min-h-10';
    @endphp

    <div class="grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_22rem]">
        {{-- The squad: jersey, role and sold amount are edited right in the list --}}
        <form method="POST" action="{{ route('admin.editions.squads.update', [$edition, $editionTeam]) }}" novalidate>
            @csrf
            @method('PUT')
            <section class="ops-card">
                <div class="ops-card-head">
                    <h3 class="ops-title">{{ $cardTitle }}</h3>
                </div>

                @if($squad->isNotEmpty())
                    <div class="hidden grid-cols-[minmax(0,1.6fr)_5rem_minmax(0,1fr)_8rem_2.5rem] gap-x-3 border-b border-line bg-slate-50 px-4 py-2 text-[11px] font-semibold uppercase tracking-wide text-slate-400 lg:grid">
                        <span>{{ __('Player') }}</span><span>{{ __('Jersey') }}</span><span>{{ __('Role') }}</span><span>{{ __('Bought for (pts)') }}</span><span class="sr-only">{{ __('Remove') }}</span>
                    </div>
                @endif

                <div class="divide-y divide-line">
                    @forelse($squad as $teamPlayer)
                        <div class="grid grid-cols-[minmax(0,1fr)_auto] items-end gap-x-3 gap-y-2 px-3 py-3 sm:px-4 lg:grid-cols-[minmax(0,1.6fr)_5rem_minmax(0,1fr)_8rem_2.5rem] lg:items-center">
                            <div class="flex min-w-0 items-center gap-3 lg:col-auto">
                                <x-media-image :path="$teamPlayer->playerRegistration->player->photo_path" kind="user" alt="" loading="lazy" class="h-9 w-9 shrink-0 rounded-full bg-slate-100 object-cover" />
                                <p class="min-w-0 truncate">
                                    @if($canOpenPlayers)
                                        <a href="{{ route('admin.players.show', $teamPlayer->playerRegistration->player) }}" class="text-[13px] font-semibold text-slate-800 hover:underline">{{ $teamPlayer->playerRegistration->player->name }}</a>
                                    @else
                                        <span class="text-[13px] font-semibold text-slate-800">{{ $teamPlayer->playerRegistration->player->name }}</span>
                                    @endif
                                    @if($teamPlayer->playerRegistration->payment_status !== 'paid')
                                        <span class="ml-1 text-[11px] text-amber-700">{{ __($teamPlayer->playerRegistration->payment_status) }}</span>
                                    @endif
                                </p>
                            </div>

                            <div class="lg:order-last">
                                @if($teamPlayer->match_players_count > 0)
                                    <span class="text-[11px] text-slate-400" title="{{ __('Has played a match') }}">{{ __('Played') }}</span>
                                @else
                                    <button
                                        type="submit"
                                        form="remove-player-{{ $teamPlayer->id }}"
                                        title="{{ __('Remove from squad') }}"
                                        aria-label="{{ __('Remove :name from the squad', ['name' => $teamPlayer->playerRegistration->player->name]) }}"
                                        class="btn btn-ghost btn-icon hover:!bg-red-50 hover:!text-red-600"
                                    ><x-icon name="trash" class="h-4 w-4" /></button>
                                @endif
                            </div>

                            <div class="col-span-2 grid grid-cols-[4.5rem_minmax(0,1fr)_minmax(0,1fr)] gap-2 lg:contents">
                                <label class="block lg:contents">
                                    <span class="mb-0.5 block text-[10px] font-semibold uppercase tracking-wide text-slate-400 lg:hidden">{{ __('Jersey') }}</span>
                                    <input type="number" inputmode="numeric" min="1" max="999" name="players[{{ $teamPlayer->id }}][jersey_number]" value="{{ old('players.'.$teamPlayer->id.'.jersey_number', $teamPlayer->jersey_number) }}" aria-label="{{ __('Jersey number') }}" class="{{ $field }}" />
                                </label>
                                <label class="block lg:contents">
                                    <span class="mb-0.5 block text-[10px] font-semibold uppercase tracking-wide text-slate-400 lg:hidden">{{ __('Role') }}</span>
                                    <select name="players[{{ $teamPlayer->id }}][role]" aria-label="{{ __('Role') }}" class="{{ $field }}">
                                        <option value="">—</option>
                                        @foreach($roleLabels as $value => $label)
                                            <option value="{{ $value }}" @selected(old('players.'.$teamPlayer->id.'.role', $teamPlayer->role) === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="block lg:contents">
                                    <span class="mb-0.5 block text-[10px] font-semibold uppercase tracking-wide text-slate-400 lg:hidden">{{ __('Bought for (pts)') }}</span>
                                    <input type="number" inputmode="decimal" min="0" step="0.01" name="players[{{ $teamPlayer->id }}][sold_amount]" value="{{ old('players.'.$teamPlayer->id.'.sold_amount', $teamPlayer->sold_amount) }}" aria-label="{{ __('Bought for') }}" placeholder="—" class="{{ $field }}" />
                                </label>
                            </div>
                        </div>
                    @empty
                        <x-admin.empty icon="users" class="py-12">{{ __('No players in this squad yet — add them on the right.') }}</x-admin.empty>
                    @endforelse
                </div>

                @if($errors->has('players') || $errors->has('players.*'))
                    <div class="border-t border-red-100 bg-red-50 px-4 py-2 text-xs text-red-700">
                        @foreach($errors->get('players') as $message){{ $message }} @endforeach
                        @foreach($errors->get('players.*') as $messages)@foreach($messages as $message){{ $message }} @endforeach @endforeach
                    </div>
                @endif
                @if($squad->isNotEmpty())
                    <div class="flex items-center gap-3 border-t border-line px-4 py-3">
                        <x-admin.button type="submit">{{ __('Save squad') }}</x-admin.button>
                        <p class="text-xs text-slate-400">{{ __('Jersey numbers, roles and amounts are saved together.') }}</p>
                    </div>
                @endif
            </section>
        </form>

        {{-- Add players --}}
        <div class="space-y-4">
            @unless($canAdd)
                <p class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">{{ __('This season is completed, so new players cannot be added.') }}</p>
            @else
                <section class="ops-card">
                    <div class="ops-card-head"><h3 class="ops-title">{{ __('Add players (:count without a team)', ['count' => $available->count()]) }}</h3></div>
                    <div class="ops-card-body">
                    @if($available->isEmpty())
                        <p class="text-xs text-slate-500">{{ __('Every registered player of this season is already in a team.') }}</p>
                    @else
                        <form method="POST" action="{{ route('admin.editions.squads.store', [$edition, $editionTeam]) }}">
                            @csrf
                            <div class="relative">
                                <x-ops.icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                <input type="search" id="player-filter" placeholder="{{ __('Search players…') }}" aria-label="{{ __('Search players') }}" class="ops-input pl-9" />
                            </div>
                            <label class="mb-1 mt-2 flex min-h-9 items-center gap-2 border-b border-line pb-1.5 text-xs text-slate-500">
                                <input type="checkbox" id="player-select-all" class="h-4 w-4 rounded border-slate-300" />
                                {{ __('Select all shown') }}
                            </label>
                            <div class="max-h-80 overflow-y-auto">
                                @foreach($available as $registration)
                                    <div class="flex min-h-11 items-center gap-2 rounded-lg px-1 py-1 text-[13px] hover:bg-hover" data-player-option="{{ mb_strtolower($registration->player->name.' '.$registration->player->phone) }}">
                                        <label class="flex min-h-9 min-w-0 flex-1 cursor-pointer items-center gap-2 text-slate-700">
                                            <input type="checkbox" name="add[{{ $registration->id }}]" value="1" @checked(old('add.'.$registration->id)) class="h-4 w-4 shrink-0 rounded border-slate-300" data-player-box />
                                            <span class="truncate">{{ $registration->player->name }}</span>
                                            @if($registration->payment_status !== 'paid')<span class="text-[11px] text-amber-700">{{ __($registration->payment_status) }}</span>@endif
                                        </label>
                                        <input type="number" inputmode="decimal" min="0" step="0.01" name="amount[{{ $registration->id }}]" value="{{ old('amount.'.$registration->id) }}" placeholder="{{ __('Amount') }}" aria-label="{{ __('Bought for') }}" class="ops-input hidden min-h-9 w-24 text-[12px]" data-player-amount />
                                    </div>
                                @endforeach
                            </div>
                            @error('add')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            @error('amount.*')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            <x-admin.button type="submit" class="mt-3 w-full">{{ __('Add ticked players') }}</x-admin.button>
                        </form>
                    @endif
                    </div>
                </section>

                <section class="ops-card">
                    <div class="ops-card-head"><h3 class="ops-title">{{ __('Registered offline? Add a new player') }}</h3></div>
                    <div class="ops-card-body">
                    <form method="POST" action="{{ route('admin.editions.squads.store-offline', [$edition, $editionTeam]) }}" novalidate>
                        @csrf
                        @php $bag = $errors->offlinePlayer; @endphp
                        <div class="mb-3">
                            <label for="op-name" class="ops-label">{{ __('Name') }} <span class="text-red-500" aria-hidden="true">*</span></label>
                            <input id="op-name" name="name" value="{{ old('name') }}" required class="ops-input {{ $bag->has('name') ? 'border-red-400' : '' }}" />
                            @if($bag->has('name'))<p class="mt-1 text-xs text-red-600">{{ $bag->first('name') }}</p>@endif
                        </div>
                        <div class="mb-3">
                            <label for="op-phone" class="ops-label">{{ __('Mobile number') }} <span class="text-red-500" aria-hidden="true">*</span></label>
                            <input id="op-phone" name="phone" type="tel" inputmode="tel" value="{{ old('phone') }}" required class="ops-input {{ $bag->has('phone') ? 'border-red-400' : '' }}" />
                            @if($bag->has('phone'))<p class="mt-1 text-xs text-red-600">{{ $bag->first('phone') }}</p>@endif
                        </div>
                        <div class="mb-3 grid grid-cols-2 gap-2">
                            <div>
                                <label for="op-amount" class="ops-label">{{ __('Bought for') }}</label>
                                <input id="op-amount" name="sold_amount" type="number" inputmode="decimal" min="0" step="0.01" value="{{ old('sold_amount') }}" class="ops-input" />
                                @if($bag->has('sold_amount'))<p class="mt-1 text-xs text-red-600">{{ $bag->first('sold_amount') }}</p>@endif
                            </div>
                            <div>
                                <label for="op-payment" class="ops-label">{{ __('Payment') }}</label>
                                <select id="op-payment" name="payment_status" class="ops-input">
                                    <option value="paid" @selected(old('payment_status', 'paid') === 'paid')>{{ __('Paid') }}</option>
                                    <option value="pending" @selected(old('payment_status') === 'pending')>{{ __('Pending') }}</option>
                                </select>
                            </div>
                        </div>
                        <x-admin.button type="submit" variant="secondary" class="w-full">{{ __('Register and add to squad') }}</x-admin.button>
                    </form>
                    </div>
                </section>
            @endunless
        </div>
    </div>

    {{-- The remove forms sit outside the save form (forms cannot nest) and outside the grid. --}}
    @foreach($squad->where('match_players_count', 0) as $teamPlayer)
        <form id="remove-player-{{ $teamPlayer->id }}" method="POST" action="{{ route('admin.editions.squads.destroy', [$edition, $editionTeam, $teamPlayer]) }}"
            data-confirm-delete data-confirm-title="{{ __('Remove :name from this squad?', ['name' => $teamPlayer->playerRegistration->player->name]) }}" data-confirm-text="{{ __('Their registration stays; they can be added to a team again.') }}">
            @csrf
            @method('DELETE')
        </form>
    @endforeach

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
