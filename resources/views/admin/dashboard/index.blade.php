@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
    <p class="mb-4 text-xs text-neutral-500">
        Welcome back, {{ auth()->user()->name }}. You are signed in as
        <span class="font-medium text-neutral-700">{{ auth()->user()->role->name }}</span>.
    </p>

    @if(! $edition)
        <div class="rounded-lg border border-neutral-200 bg-white p-4">
            <h2 class="mb-2 text-sm font-semibold text-neutral-900">No editions available yet</h2>
            <p class="text-xs text-neutral-500">
                Create a tournament edition to see operational summaries here.
            </p>
        </div>
    @else
        <div class="mb-4 flex items-center justify-between gap-3">
            <h1 class="text-base font-semibold text-neutral-900">{{ $edition->name }}</h1>
            <x-status-badge :status="$edition->status" />
        </div>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <x-stat-card
                label="Registered Players"
                :value="$registeredPlayers"
                icon="user"
                :subtext="$registeredPlayers > 0 ? $paidRegistrations.' paid, '.$pendingRegistrations.' pending' : null"
            />
            <x-stat-card label="Teams" :value="$teamsCount" icon="shield" />
            <x-stat-card label="Squad Players" :value="$squadPlayersCount" icon="users" />
            <x-stat-card
                label="Matches"
                :value="$matchesCount"
                icon="trophy"
                :subtext="$matchesCount > 0 ? $scheduledMatchesCount.' scheduled, '.$liveMatchesCount.' live, '.$completedMatchesCount.' completed' : null"
            />
        </div>

        {{-- Action Required (Phase 3.42) — only shown when there is
             actually something to act on, linking straight into the
             existing filtered registration queue. --}}
        @if($pendingRegistrations > 0)
            <a
                href="{{ route('admin.player-registrations.index', ['edition_id' => $edition->id, 'payment_status' => 'pending']) }}"
                class="mt-4 flex items-center justify-between gap-3 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-[13px] hover:bg-amber-100"
            >
                <span class="font-medium text-amber-800">
                    {{ $pendingRegistrations }} registration{{ $pendingRegistrations === 1 ? '' : 's' }} awaiting payment verification
                </span>
                <span class="whitespace-nowrap text-xs font-medium text-amber-700">Review &rarr;</span>
            </a>
        @endif

        <div class="mt-4 rounded-lg border border-neutral-200 bg-white p-4">
            <h3 class="mb-3 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">Matches Needing Attention</h3>

            @forelse($matchesNeedingAttention as $match)
                <div class="flex items-center justify-between gap-3 border-b border-neutral-100 py-2 text-[13px] last:border-b-0">
                    <div class="min-w-0">
                        <a href="{{ route('admin.matches.show', $match) }}" class="font-medium text-neutral-800 hover:underline">
                            {{ $match->teamA->team->name }} vs {{ $match->teamB->team->name }}
                        </a>
                        <p class="text-[11px] text-neutral-500">
                            {{ $match->scheduled_at->format('d M Y, h:i A') }}
                            @if($match->venue)
                                &middot; {{ $match->venue->name }}
                            @endif
                        </p>
                    </div>
                    <x-status-badge :status="$match->match_status" />
                </div>
            @empty
                <p class="py-4 text-center text-xs text-neutral-400">No matches need attention right now.</p>
            @endforelse
        </div>

        <div class="mt-4 rounded-lg border border-neutral-200 bg-white p-4">
            <h3 class="mb-3 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">Recent Results</h3>

            @forelse($recentResults as $match)
                <div class="flex items-center justify-between gap-3 border-b border-neutral-100 py-2 text-[13px] last:border-b-0">
                    <div class="min-w-0">
                        <a href="{{ route('admin.matches.show', $match) }}" class="font-medium text-neutral-800 hover:underline">
                            {{ $match->teamA->team->name }} vs {{ $match->teamB->team->name }}
                        </a>
                        <p class="text-[11px] text-neutral-500">{{ $match->scheduled_at->format('d M Y') }}</p>
                    </div>
                    <p class="text-[11px] text-neutral-600">{{ $match->match_result ?? '—' }}</p>
                </div>
            @empty
                <p class="py-4 text-center text-xs text-neutral-400">No completed matches yet.</p>
            @endforelse
        </div>

        {{-- Registration Payments (Phase 3.42) — replaces the old plain
             "Registration Summary" block with the same total/paid/
             pending figures plus the amount actually collected and an
             actionable link into the pending-verification queue. --}}
        <div class="mt-4 rounded-lg border border-neutral-200 bg-white p-4">
            <div class="mb-3 flex items-center justify-between gap-3">
                <h3 class="text-[11px] font-semibold uppercase tracking-wide text-neutral-400">Registration Payments</h3>
                <a href="{{ route('admin.player-registrations.index', ['edition_id' => $edition->id]) }}" class="text-[11px] font-medium text-blue-600 hover:underline">
                    View all &rarr;
                </a>
            </div>

            @if($registeredPlayers > 0)
                <dl class="grid grid-cols-2 gap-3 text-xs sm:grid-cols-4">
                    <div>
                        <dt class="text-neutral-400">Paid</dt>
                        <dd class="mt-0.5 font-medium text-neutral-800">{{ $paidRegistrations }}</dd>
                    </div>
                    <div>
                        <dt class="text-neutral-400">Pending Verification</dt>
                        <dd class="mt-0.5 font-medium">
                            <a
                                href="{{ route('admin.player-registrations.index', ['edition_id' => $edition->id, 'payment_status' => 'pending']) }}"
                                class="hover:underline {{ $pendingRegistrations > 0 ? 'text-amber-700' : 'text-neutral-800' }}"
                            >
                                {{ $pendingRegistrations }}
                            </a>
                        </dd>
                    </div>
                    @if($failedRegistrations > 0)
                        <div>
                            <dt class="text-neutral-400">Failed</dt>
                            <dd class="mt-0.5 font-medium text-neutral-800">{{ $failedRegistrations }}</dd>
                        </div>
                    @endif
                    @if($refundedRegistrations > 0)
                        <div>
                            <dt class="text-neutral-400">Refunded</dt>
                            <dd class="mt-0.5 font-medium text-neutral-800">{{ $refundedRegistrations }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-neutral-400">Paid Amount</dt>
                        <dd class="mt-0.5 font-medium text-neutral-800">&#8377;{{ number_format($paidRegistrationAmount, 2) }}</dd>
                    </div>
                </dl>
            @else
                <p class="text-xs text-neutral-400">No player registrations for this edition yet.</p>
            @endif
        </div>

        {{-- Finance & Contributions (Phase 3.42) — same semantics as the
             existing Finance/Contributions admin screens, never
             recalculated independently. The contribution total is a
             subset of Finance income (every contribution has a matching
             income transaction), not an addition to it — noted inline
             so it's never mistaken for extra money. --}}
        <div class="mt-4 grid gap-4 sm:grid-cols-2">
            <div class="rounded-lg border border-neutral-200 bg-white p-4">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h3 class="text-[11px] font-semibold uppercase tracking-wide text-neutral-400">Finance</h3>
                    <a href="{{ route('admin.edition-transactions.index', ['edition_id' => $edition->id]) }}" class="text-[11px] font-medium text-blue-600 hover:underline">
                        View ledger &rarr;
                    </a>
                </div>
                <dl class="grid grid-cols-3 gap-3 text-xs">
                    <div>
                        <dt class="text-neutral-400">Income</dt>
                        <dd class="mt-0.5 font-medium text-neutral-800">&#8377;{{ number_format($financeSummary['income'], 2) }}</dd>
                    </div>
                    <div>
                        <dt class="text-neutral-400">Expenses</dt>
                        <dd class="mt-0.5 font-medium text-neutral-800">&#8377;{{ number_format($financeSummary['expense'], 2) }}</dd>
                    </div>
                    <div>
                        <dt class="text-neutral-400">Balance</dt>
                        <dd class="mt-0.5 font-medium {{ $financeSummary['balance'] < 0 ? 'text-red-600' : 'text-neutral-800' }}">
                            &#8377;{{ number_format($financeSummary['balance'], 2) }}
                        </dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-lg border border-neutral-200 bg-white p-4">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h3 class="text-[11px] font-semibold uppercase tracking-wide text-neutral-400">Contributions</h3>
                    <a href="{{ route('admin.edition-contributions.index', ['edition_id' => $edition->id]) }}" class="text-[11px] font-medium text-blue-600 hover:underline">
                        View all &rarr;
                    </a>
                </div>
                <dl class="grid grid-cols-3 gap-3 text-xs">
                    <div>
                        <dt class="text-neutral-400">Total</dt>
                        <dd class="mt-0.5 font-medium text-neutral-800">&#8377;{{ number_format($contributionTotal, 2) }}</dd>
                    </div>
                    <div>
                        <dt class="text-neutral-400">Records</dt>
                        <dd class="mt-0.5 font-medium text-neutral-800">{{ $contributionCount }}</dd>
                    </div>
                    <div>
                        <dt class="text-neutral-400">Contributors</dt>
                        <dd class="mt-0.5 font-medium text-neutral-800">{{ $recognizedContributorsCount }}</dd>
                    </div>
                </dl>
                <p class="mt-2 text-[11px] text-neutral-400">Already included in Finance income above — shown separately for visibility only.</p>
            </div>
        </div>
    @endif
@endsection
