@extends('layouts.admin')

@section('title', __('Auction console').' · '.$edition->name)

@section('subtitle', __('Call a player, take the bids, sell or hold. Every amount is in points.'))

@section('actions')
    <x-admin.button :href="route('admin.auctions.show', $edition)" variant="secondary" icon="cog">{{ __('Set-up and rules') }}</x-admin.button>
@endsection

@section('content')
    {{-- The whole console draws itself from one state array (see
         AuctionStateService) and redraws from the fresh state every action
         returns, and every few seconds so a second console stays in step.
         Only the parts that keep typed text — the amount, the search box and
         the walk-in form — are static here. --}}
    <script type="application/json" id="auction-console-data">{!! json_encode([
        'state' => $state,
        'urls' => [
            'state' => route('admin.auctions.console.state', $edition),
            'random' => route('admin.auctions.console.random', $edition),
            'call' => route('admin.auctions.console.call', $edition),
            'bid' => route('admin.auctions.console.bid', $edition),
            'undo' => route('admin.auctions.console.undo', $edition),
            'sell' => route('admin.auctions.console.sell', $edition),
            'hold' => route('admin.auctions.console.hold', $edition),
            'release' => route('admin.auctions.console.release', $edition),
            'reopen' => route('admin.auctions.console.reopen', $edition),
            'take-back' => route('admin.auctions.console.take-back', $edition),
            'next-round' => route('admin.auctions.console.next-round', $edition),
            'live-bids' => route('admin.auctions.console.live-bids', $edition),
            'pause' => route('admin.auctions.console.pause', $edition),
            'resume' => route('admin.auctions.console.resume', $edition),
            'pool' => route('admin.auctions.console.pool', $edition),
            'walk-in' => route('admin.auctions.console.walk-in', $edition),
            'player' => route('admin.auctions.console.player', $edition),
            'fix-bid' => route('admin.auctions.console.fix-bid', $edition),
            'price' => route('admin.auctions.console.price', $edition),
            'setup' => route('admin.auctions.show', $edition),
        ],
    ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) !!}</script>

    {{-- Room under the fixed Sell / Hold bar on a phone or tablet. --}}
    <div id="auction-console" class="space-y-4 pb-28 xl:pb-0">
        <div id="ac-toolbar" class="ac-card flex flex-wrap items-center justify-between gap-x-4 gap-y-2"></div>

        <div id="ac-pool"></div>

        {{-- No connection: shown when the screen cannot reach the server, so nobody taps into the void. --}}
        <div id="ac-offline" class="hidden rounded-xl border-2 border-red-300 bg-red-50 px-4 py-3 text-sm font-semibold text-red-800" role="alert">
            {{ __('No connection. Your taps are not reaching the server: check the wifi. Nothing has been lost.') }}
        </div>

        {{-- Right after SOLD: ten seconds to take it back with one tap. --}}
        <div id="ac-undo-sale" class="hidden" role="status" aria-live="polite"></div>

        <div id="ac-notice" class="hidden" role="status" aria-live="polite"></div>

        <div class="grid items-start gap-4 xl:grid-cols-[minmax(0,1fr)_23rem]">
            <div class="min-w-0 space-y-4">
                <section id="ac-lot" aria-label="{{ __('Player on the block') }}"></section>

                <section class="ac-card" aria-label="{{ __('Bid amount') }}">
                    <div class="flex flex-wrap items-center gap-2">
                        <label for="ac-amount" class="text-sm font-semibold text-slate-800">{{ __('Bid this amount') }}</label>
                        <input
                            id="ac-amount"
                            type="number"
                            inputmode="numeric"
                            min="1"
                            step="500"
                            placeholder="{{ __('next step') }}"
                            class="ops-input min-h-11 w-36 text-base tabular-nums"
                        />
                        <button type="button" data-action="clear-amount" class="min-h-10 rounded-lg px-3 text-xs font-medium text-slate-500 hover:bg-slate-100">{{ __('Clear') }}</button>
                        <span id="ac-chips" class="flex flex-wrap items-center gap-2"></span>
                    </div>
                    <p class="mt-2 text-[11px] text-slate-400">
                        {{ __("Leave it empty and a team's button bids the next step. To jump, type an amount or tap a chip, then tap the team.") }}
                    </p>
                </section>

                <section id="ac-teams" aria-label="{{ __('Teams') }}"></section>
            </div>

            <aside class="min-w-0 space-y-4">
                <section class="ac-card" aria-label="{{ __('Call a player') }}">
                    <div class="mb-3 flex items-center gap-2">
                        <label for="ac-role" class="shrink-0 text-xs font-semibold text-slate-600">{{ __('Random call from') }}</label>
                        <select id="ac-role" class="ops-input min-h-10 flex-1">
                            <option value="">{{ __('Any role') }}</option>
                            @foreach(\App\Models\Player::PRIMARY_ROLE_LABELS as $key => $label)<option value="{{ $key }}">{{ __($label) }}</option>@endforeach
                        </select>
                    </div>
                    <label for="ac-search" class="text-sm font-semibold text-slate-800">{{ __('Find a waiting or hold player') }}</label>
                    <div class="relative mt-1.5">
                        <x-ops.icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input
                            id="ac-search"
                            type="search"
                            autocomplete="off"
                            placeholder="{{ __('Type a name or village…') }}"
                            class="ops-input min-h-11 pl-9"
                        />
                    </div>
                    <div id="ac-results" class="mt-2"></div>
                </section>

                <section id="ac-bids" class="ac-card" aria-label="{{ __('Bids on this player') }}"></section>
                <section class="ac-card" aria-label="{{ __('Sold players') }}">
                    <label for="ac-sold-search" class="text-sm font-semibold text-slate-800">{{ __('Sold players') }} <span id="ac-sold-count" class="font-normal text-slate-400"></span></label>
                    <div class="relative mt-1.5">
                        <x-ops.icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input
                            id="ac-sold-search"
                            type="search"
                            autocomplete="off"
                            placeholder="{{ __('Find a sale by player, team or village…') }}"
                            class="ops-input min-h-11 pl-9"
                        />
                    </div>
                    <div id="ac-sold" class="mt-2"></div>
                </section>

                <details class="ac-card">
                    <summary class="flex min-h-10 cursor-pointer items-center text-sm font-semibold text-slate-800">{{ __('Someone turned up on the day? Add a player') }}</summary>
                    <form id="ac-walkin" class="mt-2 space-y-2" novalidate>
                        <input name="name" required maxlength="255" placeholder="{{ __('Full name') }}" aria-label="{{ __('Full name') }}" class="ops-input min-h-11" />
                        <input name="phone" required maxlength="20" inputmode="tel" placeholder="{{ __('Mobile number') }}" aria-label="{{ __('Mobile number') }}" class="ops-input min-h-11" />
                        <input name="village" maxlength="255" placeholder="{{ __('Village (optional)') }}" aria-label="{{ __('Village (optional)') }}" class="ops-input min-h-11" />
                        <select name="role" aria-label="{{ __('Role (optional)') }}" class="ops-input min-h-11">
                            <option value="">{{ __('Role (optional)') }}</option>
                            @foreach(\App\Models\Player::PRIMARY_ROLE_LABELS as $key => $label)<option value="{{ $key }}">{{ __($label) }}</option>@endforeach
                        </select>
                        <button type="submit" class="btn btn-secondary w-full">{{ __('Register and add to the waiting players') }}</button>
                        <p class="text-[11px] text-slate-400">{{ __('They are registered for this season as paid and wait with the others — call them like anyone else.') }}</p>
                    </form>
                </details>
            </aside>
        </div>
    </div>

    {{-- Correct a player's details without leaving the console. --}}
    <dialog id="ac-player-dialog" class="w-[min(28rem,calc(100vw-2rem))] rounded-2xl p-0 shadow-pop backdrop:bg-black/50">
        <form id="ac-player-form" class="space-y-3 p-5" novalidate>
            <h2 class="text-base font-bold text-slate-900">{{ __('Correct the player\'s details') }}</h2>
            <label class="block text-xs font-semibold text-slate-600">{{ __('Name') }}
                <input name="name" required maxlength="255" class="ops-input mt-1 min-h-11" />
            </label>
            <label class="block text-xs font-semibold text-slate-600">{{ __('Village') }}
                <input name="village" maxlength="255" class="ops-input mt-1 min-h-11" />
            </label>
            <label class="block text-xs font-semibold text-slate-600">{{ __('Role') }}
                <select name="primary_role" class="ops-input mt-1 min-h-11">
                    <option value="">{{ __('Not set') }}</option>
                    @foreach(\App\Models\Player::PRIMARY_ROLE_LABELS as $key => $label)<option value="{{ $key }}">{{ __($label) }}</option>@endforeach
                </select>
            </label>
            <div class="flex justify-end gap-2 pt-1">
                <button type="button" data-action="close-player" class="btn btn-secondary">{{ __('Cancel') }}</button>
                <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
            </div>
        </form>
    </dialog>

    @vite(['resources/js/admin-auction.js'])
@endsection
