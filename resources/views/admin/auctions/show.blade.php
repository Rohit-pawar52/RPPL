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
            <x-admin.card :title="'Set up the '.$edition->name.' auction'">
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

                        <x-admin.button>Create auction</x-admin.button>
                    </form>
                @endif
            </x-admin.card>

            <x-admin.card title="Ready to go">
                <dl class="grid grid-cols-2 gap-3 text-[13px]">
                    <div>
                        <dt class="text-xs text-slate-400">Teams in this season</dt>
                        <dd class="mt-0.5 text-lg font-semibold text-slate-900">{{ $teamCount }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-400">Paid players not in a team</dt>
                        <dd class="mt-0.5 text-lg font-semibold text-slate-900">{{ $readyPlayers }}</dd>
                    </div>
                </dl>
                <p class="mt-3 text-[11px] leading-relaxed text-slate-400">
                    Only players whose registration is <span class="font-medium text-slate-500">paid</span> go into the auction.
                    Every setting can be changed later, even while the auction is running — a change applies from the next bid.
                </p>
            </x-admin.card>
        </div>
    @else
        @php
            $editable = ! $auction->isCompleted();
        @endphp

        <div class="grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_21rem]">
            <x-admin.card title="Rules and team purses">
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

                        <p class="mb-1.5 mt-1 text-xs font-medium text-slate-700">Teams</p>
                        <div class="mb-3.5 overflow-x-auto rounded-md border border-slate-200">
                            <table class="w-full min-w-[460px] text-left text-[13px]">
                                <thead class="border-b border-slate-200 bg-slate-50 text-[11px] uppercase tracking-wide text-slate-400">
                                    <tr>
                                        <th class="px-3 py-2 font-medium">Team</th>
                                        <th class="px-3 py-2 font-medium">Own purse</th>
                                        <th class="px-3 py-2 text-right font-medium">Players</th>
                                        <th class="px-3 py-2 text-right font-medium">Spent</th>
                                        <th class="px-3 py-2 text-right font-medium">Left</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                    @forelse($standings as $row)
                                        <tr>
                                            <td class="px-3 py-1.5 font-medium text-slate-800">{{ $row['edition_team']->team->name }}</td>
                                            <td class="px-3 py-1.5">
                                                <input
                                                    type="number"
                                                    min="0"
                                                    step="1"
                                                    name="team_purses[{{ $row['edition_team']->id }}]"
                                                    value="{{ old('team_purses.'.$row['edition_team']->id, $row['edition_team']->auction_purse) }}"
                                                    placeholder="{{ points($auction->team_purse) }}"
                                                    aria-label="Own purse for {{ $row['edition_team']->team->name }}"
                                                    class="h-8 w-28 rounded-md border border-slate-300 px-2 text-[13px] focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-100"
                                                />
                                            </td>
                                            <td class="px-3 py-1.5 text-right tabular-nums text-slate-600">{{ $row['count'] }}</td>
                                            <td class="px-3 py-1.5 text-right tabular-nums text-slate-600">{{ points($row['spent']) }}</td>
                                            <td class="px-3 py-1.5 text-right font-medium tabular-nums text-slate-800">{{ points($row['left']) }}</td>
                                        </tr>
                                    @empty
                                        <x-admin.empty table colspan="5">No teams in this season yet.</x-admin.empty>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                        <p class="-mt-2 mb-3.5 text-[11px] text-slate-400">Leave a team's own purse empty to give it the default purse of {{ points($auction->team_purse, true) }}.</p>

                        @if($editable)
                            <x-admin.button>Save settings</x-admin.button>
                        @endif
                    </fieldset>
                </form>
            </x-admin.card>

            <div class="space-y-4">
                <x-admin.card title="Auction status">
                    <div class="flex items-center justify-between gap-2">
                        <x-status-badge :status="$auction->status" />
                        <span class="text-xs text-slate-500">Round {{ $auction->round }}</span>
                    </div>

                    <dl class="mt-3 grid grid-cols-3 gap-2 text-center">
                        @foreach([
                            'Waiting' => $counts['pending'],
                            'On the block' => $counts['live'],
                            'Sold' => $counts['sold'],
                            'Hold' => $counts['hold'],
                            'Unsold' => $counts['unsold'],
                            'In the pool' => $counts['total'],
                        ] as $label => $number)
                            <div class="rounded-md border border-slate-100 bg-slate-50 px-1 py-2">
                                <dd class="text-base font-semibold tabular-nums text-slate-900">{{ $number }}</dd>
                                <dt class="text-[10px] uppercase tracking-wide text-slate-400">{{ $label }}</dt>
                            </div>
                        @endforeach
                    </dl>

                    @if($auction->started_at)
                        <p class="mt-3 text-[11px] text-slate-400">
                            Started {{ display_datetime($auction->started_at, 'd M Y, h:i A') }}@if($auction->completed_at) &middot; completed {{ display_datetime($auction->completed_at, 'd M Y, h:i A') }}@endif
                        </p>
                    @endif

                    <div class="mt-3 flex flex-wrap gap-2">
                        @if($auction->isLive() || $auction->isPaused())
                            <x-admin.button :href="route('admin.auctions.console', $edition)" variant="primary" icon="gavel">Open the console</x-admin.button>
                        @endif
                        @if($auction->isDraft())
                            <form method="POST" action="{{ route('admin.auctions.start', $edition) }}">
                                @csrf
                                <x-admin.button>Start auction</x-admin.button>
                            </form>
                        @endif
                        @if($auction->isLive())
                            <form method="POST" action="{{ route('admin.auctions.pause', $edition) }}">
                                @csrf
                                <x-admin.button variant="secondary">Pause</x-admin.button>
                            </form>
                        @endif
                        @if($auction->isPaused())
                            <form method="POST" action="{{ route('admin.auctions.resume', $edition) }}">
                                @csrf
                                <x-admin.button>Resume</x-admin.button>
                            </form>
                        @endif
                        @if($auction->isLive() || $auction->isPaused())
                            <form
                                method="POST"
                                action="{{ route('admin.auctions.complete', $edition) }}"
                                onsubmit="return confirm('Complete the auction? Everyone still waiting, on hold or on the block becomes unsold. This cannot be undone.')"
                            >
                                @csrf
                                <x-admin.button variant="danger">Complete</x-admin.button>
                            </form>
                        @endif
                    </div>
                </x-admin.card>

                @if($editable)
                    <x-admin.card title="Player pool">
                        <p class="text-[13px] text-slate-600">
                            Paid players who are not in a team. The pool follows payments and team changes by itself; use this only if something was changed outside the app and the console shows a notice about it.
                        </p>
                        <form method="POST" action="{{ route('admin.auctions.refresh-pool', $edition) }}" class="mt-3">
                            @csrf
                            <x-admin.button variant="secondary">Update the pool</x-admin.button>
                        </form>
                        <p class="mt-2 text-[11px] text-slate-400">Players who are already sold or on the block are never touched.</p>
                    </x-admin.card>
                @endif
            </div>
        </div>
    @endif
@endsection
