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

        <div class="mt-4 rounded-lg border border-neutral-200 bg-white p-4">
            <h3 class="mb-3 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">Registration Summary</h3>

            @if($registeredPlayers > 0)
                <dl class="grid grid-cols-3 gap-3 text-xs sm:grid-cols-4">
                    <div>
                        <dt class="text-neutral-400">Total</dt>
                        <dd class="mt-0.5 font-medium text-neutral-800">{{ $registeredPlayers }}</dd>
                    </div>
                    <div>
                        <dt class="text-neutral-400">Paid</dt>
                        <dd class="mt-0.5 font-medium text-neutral-800">{{ $paidRegistrations }}</dd>
                    </div>
                    <div>
                        <dt class="text-neutral-400">Pending</dt>
                        <dd class="mt-0.5 font-medium text-neutral-800">{{ $pendingRegistrations }}</dd>
                    </div>
                </dl>
            @else
                <p class="text-xs text-neutral-400">No player registrations for this edition yet.</p>
            @endif
        </div>
    @endif
@endsection
