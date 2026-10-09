@extends('layouts.admin')

@section('title', 'Auction · '.$edition->name)

@section('content')
    <nav aria-label="Auction" class="mb-3 flex flex-wrap items-center gap-1.5 text-xs text-slate-500">
        <a href="{{ route('admin.auctions.index') }}" class="hover:text-slate-800 hover:underline">Auction</a>
        <span class="text-slate-300" aria-hidden="true">&rsaquo;</span>
        <span class="font-medium text-slate-800" aria-current="page">{{ $edition->name }}</span>
    </nav>

    @if(! $auction)
        {{-- No auction yet: choose the rules and create it. --}}
        <div class="grid items-start gap-4 lg:grid-cols-[minmax(0,34rem)_minmax(0,1fr)]">
            <section class="ops-card">
                <div class="ops-card-head"><h3 class="ops-title">Set up the {{ $edition->name }} auction</h3></div>
                <div class="ops-card-body">
                    @if($edition->status === 'completed')
                        <p class="text-[13px] text-slate-600">This season is completed, so it cannot have a new auction.</p>
                    @else
                        <form method="POST" action="{{ route('admin.auctions.store', $edition) }}" novalidate>
                            @csrf

                            @include('admin.auctions._settings', ['values' => [
                                'team_purse' => 600000,
                                'min_bid' => 500,
                                'bid_step' => 500,
                                'min_squad' => 12,
                                'max_squad' => 15,
                                'show_live_bids' => true,
                                'notify_start' => true,
                                'notify_sale_min' => null,
                            ]])

                            <x-admin.button class="min-h-11 w-full sm:w-auto">Create auction</x-admin.button>
                        </form>
                    @endif
                </div>
            </section>

            <section class="ops-card">
                <div class="ops-card-head"><h3 class="ops-title">Ready to go</h3></div>
                <div class="ops-card-body">
                    <dl class="grid grid-cols-2 gap-3 text-[13px]">
                        <div class="rounded-xl bg-slate-50 p-3">
                            <dt class="ops-kicker">Teams in this season</dt>
                            <dd class="mt-1 text-3xl font-bold tabular-nums text-slate-900">{{ $teamCount }}</dd>
                        </div>
                        <div class="rounded-xl bg-slate-50 p-3">
                            <dt class="ops-kicker">Paid players not in a team</dt>
                            <dd class="mt-1 text-3xl font-bold tabular-nums text-slate-900">{{ $readyPlayers }}</dd>
                        </div>
                    </dl>
                    <p class="mt-3 text-[12px] leading-relaxed text-slate-500">
                        Only players whose registration is <span class="font-medium text-slate-700">paid</span> go into the auction.
                        Every setting can be changed later, even while the auction is running — a change applies from the next bid.
                    </p>
                </div>
            </section>
        </div>
    @else
        @php
            $editable = ! $auction->isCompleted();
        @endphp

        {{-- Where the auction stands, and the one big button for the next step. --}}
        <section class="rounded-2xl bg-gradient-to-br from-navy-900 to-navy-800 p-4 text-white shadow-raised sm:p-6" aria-label="Auction status">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-3">
                    <x-status-badge :status="$auction->status" />
                    <span class="text-sm text-white/70">Round {{ $auction->round }}</span>
                </div>
                @if($auction->started_at)
                    <p class="text-xs text-white/60">
                        Started {{ display_datetime($auction->started_at, 'd M Y, h:i A') }}@if($auction->completed_at) &middot; completed {{ display_datetime($auction->completed_at, 'd M Y, h:i A') }}@endif
                    </p>
                @endif
            </div>

            <dl class="mt-4 grid grid-cols-3 gap-2 text-center sm:grid-cols-6">
                @foreach([
                    'Waiting' => $counts['pending'],
                    'On the block' => $counts['live'],
                    'Sold' => $counts['sold'],
                    'Hold' => $counts['hold'],
                    'Unsold' => $counts['unsold'],
                    'In the pool' => $counts['total'],
                ] as $label => $number)
                    <div class="rounded-xl bg-white/10 px-1 py-2.5">
                        <dd class="text-2xl font-bold tabular-nums">{{ $number }}</dd>
                        <dt class="text-[10px] uppercase tracking-wide text-white/60">{{ $label }}</dt>
                    </div>
                @endforeach
            </dl>

            <div class="mt-4 flex flex-wrap items-center gap-2">
                @if($auction->isLive() || $auction->isPaused())
                    <a href="{{ route('admin.auctions.console', $edition) }}" class="btn btn-primary btn-lg min-h-14 flex-1 text-base font-bold sm:flex-none sm:px-8">
                        <x-icon name="gavel" class="h-5 w-5" /> Open the console
                    </a>
                @endif
                @if($auction->isDraft())
                    <form method="POST" action="{{ route('admin.auctions.start', $edition) }}" class="flex-1 sm:flex-none">
                        @csrf
                        <button type="submit" class="btn btn-primary btn-lg min-h-14 w-full text-base font-bold sm:px-8">
                            <x-ops.icon name="play" class="h-5 w-5" /> Start auction
                        </button>
                    </form>
                @endif
                @if($auction->isLive())
                    <form method="POST" action="{{ route('admin.auctions.pause', $edition) }}">
                        @csrf
                        <button type="submit" class="btn btn-lg border border-white/30 text-white hover:bg-white/10">Pause</button>
                    </form>
                @endif
                @if($auction->isPaused())
                    <form method="POST" action="{{ route('admin.auctions.resume', $edition) }}">
                        @csrf
                        <button type="submit" class="btn btn-primary btn-lg">Resume</button>
                    </form>
                @endif
                @if($auction->isLive() || $auction->isPaused())
                    <form
                        method="POST"
                        action="{{ route('admin.auctions.complete', $edition) }}"
                        onsubmit="return confirm('Complete the auction? Everyone still waiting, on hold or on the block becomes unsold. This cannot be undone.')"
                    >
                        @csrf
                        <button type="submit" class="btn btn-lg border border-red-300/60 text-red-100 hover:bg-red-500/20">Complete</button>
                    </form>
                @endif
            </div>
        </section>

        <div class="mt-4 grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_21rem]">
            <section class="ops-card">
                <div class="ops-card-head"><h3 class="ops-title">Rules and team purses</h3></div>
                <div class="ops-card-body">
                    <form method="POST" action="{{ route('admin.auctions.update', $edition) }}" novalidate>
                        @csrf
                        @method('PUT')

                        <fieldset @disabled(! $editable)>
                            @include('admin.auctions._settings', ['values' => [
                                'team_purse' => $auction->team_purse,
                                'min_bid' => $auction->min_bid,
                                'bid_step' => $auction->bid_step,
                                'min_squad' => $auction->min_squad,
                                'max_squad' => $auction->max_squad,
                                'show_live_bids' => $auction->show_live_bids,
                                'notify_start' => $auction->notify_start,
                                'notify_sale_min' => $auction->notify_sale_min,
                            ]])

                            <p class="mb-1.5 mt-1 text-xs font-semibold text-slate-700">Teams</p>
                            <div class="mb-3.5 overflow-x-auto rounded-xl border border-line">
                                <table class="w-full min-w-[460px] text-left text-[13px]">
                                    <thead class="border-b border-line bg-slate-50 text-[11px] uppercase tracking-wide text-slate-400">
                                        <tr>
                                            <th class="px-3 py-2 font-semibold">Team</th>
                                            <th class="px-3 py-2 font-semibold">Own purse</th>
                                            <th class="px-3 py-2 text-right font-semibold">Players</th>
                                            <th class="px-3 py-2 text-right font-semibold">Spent</th>
                                            <th class="px-3 py-2 text-right font-semibold">Left</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-line">
                                        @forelse($standings as $row)
                                            <tr>
                                                <td class="px-3 py-1.5 font-semibold text-slate-800">{{ $row['edition_team']->team->name }}</td>
                                                <td class="px-3 py-1.5">
                                                    <input
                                                        type="number"
                                                        inputmode="numeric"
                                                        min="0"
                                                        step="1"
                                                        name="team_purses[{{ $row['edition_team']->id }}]"
                                                        value="{{ old('team_purses.'.$row['edition_team']->id, $row['edition_team']->auction_purse) }}"
                                                        placeholder="{{ points($auction->team_purse) }}"
                                                        aria-label="Own purse for {{ $row['edition_team']->team->name }}"
                                                        class="ops-input min-h-10 w-32"
                                                    />
                                                </td>
                                                <td class="px-3 py-1.5 text-right tabular-nums text-slate-600">{{ $row['count'] }}</td>
                                                <td class="px-3 py-1.5 text-right tabular-nums text-slate-600">{{ points($row['spent']) }}</td>
                                                <td class="px-3 py-1.5 text-right font-semibold tabular-nums text-slate-800">{{ points($row['left']) }}</td>
                                            </tr>
                                        @empty
                                            <x-admin.empty table colspan="5">No teams in this season yet.</x-admin.empty>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                            <p class="-mt-2 mb-3.5 text-[11px] text-slate-400">Leave a team's own purse empty to give it the default purse of {{ points($auction->team_purse, true) }}.</p>

                            @if($editable)
                                <x-admin.button class="min-h-11 w-full sm:w-auto">Save settings</x-admin.button>
                            @endif
                        </fieldset>
                    </form>
                </div>
            </section>

            @if($editable)
                <section class="ops-card">
                    <div class="ops-card-head"><h3 class="ops-title">Player pool</h3></div>
                    <div class="ops-card-body">
                        <p class="text-[13px] text-slate-600">
                            Paid players who are not in a team. The pool follows payments and team changes by itself; use this only if something was changed outside the app and the console shows a notice about it.
                        </p>
                        <form method="POST" action="{{ route('admin.auctions.refresh-pool', $edition) }}" class="mt-3">
                            @csrf
                            <x-admin.button variant="secondary" class="min-h-11">Update the pool</x-admin.button>
                        </form>
                        <p class="mt-2 text-[11px] text-slate-400">Players who are already sold or on the block are never touched.</p>
                    </div>
                </section>
            @endif
        </div>
    @endif
@endsection
