@extends('layouts.admin')

@section('title', __('Dashboard'))

{{-- The dashboard draws its own heading (the greeting band), so the layout's title block is skipped. --}}
@section('bare', '1')

@section('content')
    @php
        // Seeing the dashboard (dashboard.tournament) does not mean being able to open every page it points
        // to: a link is drawn only when the role may open its target, otherwise the figure stays unlinked.
        $user = auth()->user();
        $canOpenRegistrations = $user->can('viewAny', \App\Models\PlayerRegistration::class);
        $canOpenMatches = $user->can('viewAny', \App\Models\GameMatch::class);
        $canCreateMatch = $user->can('create', \App\Models\GameMatch::class);
        $canViewEdition = $edition && $user->can('view', $edition);

        $hour = (int) display_datetime(now(), 'G');
        $greeting = $hour < 12 ? __('Good morning') : ($hour < 17 ? __('Good afternoon') : __('Good evening'));
        $firstName = \Illuminate\Support\Str::before(trim($user->name), ' ');

        $reviewable = $canViewFinance && $canOpenRegistrations;

        // The few newest registrations still waiting for a payment check, so they can be opened in one tap.
        $pendingQueue = collect();
        if ($edition && $reviewable && $pendingRegistrations > 0) {
            $pendingQueue = \App\Models\PlayerRegistration::query()
                ->where('edition_id', $edition->id)
                ->where('payment_status', 'pending')
                ->with('player')
                ->orderByDesc('registered_at')
                ->limit(5)
                ->get();
        }

        $quickHtml = trim((string) \Illuminate\Support\Facades\Blade::render('<x-admin.quick-links layout="grid" />'));
    @endphp

    {{-- Greeting band --}}
    <section class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-navy-900 to-navy-800 p-5 text-white shadow-raised sm:p-7">
        <div class="pointer-events-none absolute -right-16 -top-24 h-72 w-72 rounded-full bg-brand opacity-30 blur-3xl" aria-hidden="true"></div>
        <div class="relative flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div class="min-w-0">
                <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-accent-dark">{{ display_datetime(now(), 'l, j F') }}</p>
                <h1 class="mt-1.5 break-words text-2xl font-bold tracking-tight sm:text-3xl">{{ $greeting }}, {{ $firstName }}</h1>
                @if($edition)
                    <p class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1.5 text-[13px] text-white/70">
                        <span class="text-base font-semibold text-white">{{ $edition->name }}</span>
                        <x-status-badge :status="$edition->status" />
                    </p>
                @else
                    <p class="mt-2 text-[13px] text-white/70">{{ __('Signed in as :role.', ['role' => $user->role->name]) }}</p>
                @endif
            </div>

            @if($edition)
                <div class="flex flex-wrap items-center gap-2">
                    @if($reviewable && $pendingRegistrations > 0)
                        <a href="{{ route('admin.player-registrations.review-pending', ['edition_id' => $edition->id]) }}" class="btn btn-primary btn-lg max-sm:flex-1">
                            <x-admin.icon name="check-circle" class="h-[18px] w-[18px]" />
                            {{ __('Review next pending') }}
                        </a>
                    @endif
                    @if($canCreateMatch)
                        <a href="{{ route('admin.editions.matches.create', $edition) }}" class="btn btn-lg border border-white/25 bg-white/10 text-white hover:bg-white/20 focus-visible:outline-white max-sm:flex-1">
                            <x-admin.icon name="plus" class="h-[18px] w-[18px]" />
                            {{ __('New match') }}
                        </a>
                    @endif
                    @if($canOpenMatches && $liveMatchesCount > 0)
                        <a href="{{ route('admin.matches.index', ['edition_id' => $edition->id, 'match_status' => 'live']) }}" class="btn btn-lg border border-white/25 bg-white/10 text-white hover:bg-white/20 focus-visible:outline-white max-sm:flex-1">
                            <span class="live-dot text-accent-dark"></span>
                            {{ __(':count live now', ['count' => $liveMatchesCount]) }}
                        </a>
                    @endif
                </div>
            @endif
        </div>
    </section>

    @if(! $edition)
        <div class="adm-card mt-6">
            <x-admin.empty icon="calendar" :title="__('No editions available yet')">
                {{ __('Create a tournament edition to see operational summaries here.') }}
                @can('create', \App\Models\Edition::class)
                    <x-slot:action>
                        <x-admin.button :href="route('admin.editions.create')" icon="plus">{{ __('Create an edition') }}</x-admin.button>
                    </x-slot:action>
                @endcan
            </x-admin.empty>
        </div>
    @else
        <div class="mt-6 space-y-6 lg:mt-8 lg:space-y-8">
            {{-- Needs action: payments waiting for a check (only for roles that may see finance and registrations) --}}
            @if($reviewable && $pendingRegistrations > 0)
                <section class="overflow-hidden rounded-xl border border-amber-200 bg-white shadow-card" aria-labelledby="dash-pending">
                    <div class="flex flex-col gap-3 border-b border-amber-100 bg-amber-50 px-4 py-3.5 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-700"><x-admin.icon name="clock" class="h-5 w-5" /></span>
                            <div class="min-w-0">
                                <h2 id="dash-pending" class="text-[15px] font-semibold tracking-tight text-amber-900">
                                    {{ $pendingRegistrations === 1 ? __('1 registration awaiting payment verification') : __(':count registrations awaiting payment verification', ['count' => $pendingRegistrations]) }}
                                </h2>
                                <p class="text-xs text-amber-800/80">{{ __('Open one to check the payment proof and approve it; the next one follows automatically.') }}</p>
                            </div>
                        </div>
                        <div class="flex shrink-0 flex-wrap items-center gap-2">
                            <a href="{{ route('admin.player-registrations.review-pending', ['edition_id' => $edition->id]) }}" class="btn btn-primary btn-sm max-sm:flex-1">{{ __('Review next') }}</a>
                            <a
                                href="{{ route('admin.player-registrations.index', ['edition_id' => $edition->id, 'payment_status' => 'pending']) }}"
                                class="btn btn-secondary btn-sm max-sm:flex-1"
                            >{{ __('View all pending') }}</a>
                        </div>
                    </div>
                    <ul class="divide-y divide-line">
                        @foreach($pendingQueue as $registration)
                            <li>
                                <a href="{{ route('admin.player-registrations.show', $registration) }}" class="flex min-h-14 items-center gap-3 px-4 py-2.5 transition-colors hover:bg-hover sm:px-5">
                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-100 text-xs font-bold uppercase text-slate-500">{{ \Illuminate\Support\Str::substr($registration->player->name ?? '?', 0, 1) }}</span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-[13px] font-semibold text-slate-900">{{ $registration->player->name ?? __('Unknown player') }}</span>
                                        <span class="block truncate text-[11px] text-slate-500">
                                            {{ $registration->registered_at ? display_datetime($registration->registered_at, 'd M Y, h:i A') : __('Registered') }}
                                            @if($registration->registration_fee) &middot; {{ money($registration->registration_fee) }} @endif
                                        </span>
                                    </span>
                                    <span class="btn btn-soft btn-sm shrink-0">{{ __('Review') }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            {{-- KPI tiles: each one is a link to the matching, already-filtered list --}}
            <section aria-label="{{ __('Key figures') }}">
                <div class="grid grid-cols-2 gap-3 md:grid-cols-3">
                    <x-stat-card
                        :label="__('Registered Players')"
                        :value="$registeredPlayers"
                        icon="user"
                        :href="$canOpenRegistrations ? route('admin.player-registrations.index', ['edition_id' => $edition->id]) : null"
                        :subtext="$canViewFinance && $registeredPlayers > 0 ? __(':paid paid, :pending pending', ['paid' => $paidRegistrations, 'pending' => $pendingRegistrations]) : null"
                    />
                    @if($reviewable)
                        <x-stat-card
                            :label="__('To verify')"
                            :value="$pendingRegistrations"
                            icon="clock"
                            :tone="$pendingRegistrations > 0 ? 'amber' : 'green'"
                            :href="route('admin.player-registrations.index', ['edition_id' => $edition->id, 'payment_status' => 'pending'])"
                            :subtext="$pendingRegistrations > 0 ? __('payments to check') : __('all caught up')"
                        />
                    @endif
                    <x-stat-card
                        :label="__('Matches')"
                        :value="$matchesCount"
                        icon="trophy"
                        :href="$canOpenMatches ? route('admin.matches.index', ['edition_id' => $edition->id]) : null"
                        :subtext="$matchesCount > 0 ? __(':scheduled scheduled, :live live, :completed completed', ['scheduled' => $scheduledMatchesCount, 'live' => $liveMatchesCount, 'completed' => $completedMatchesCount]) : null"
                    />
                    <x-stat-card
                        :label="__('Live now')"
                        :value="$liveMatchesCount"
                        icon="bolt"
                        :tone="$liveMatchesCount > 0 ? 'green' : 'brand'"
                        :href="$canOpenMatches ? route('admin.matches.index', ['edition_id' => $edition->id, 'match_status' => 'live']) : null"
                        :subtext="$liveMatchesCount > 0 ? __('in progress') : __('nothing live')"
                    />
                    <x-stat-card
                        :label="__('Teams')"
                        :value="$teamsCount"
                        icon="shield"
                        :href="$canViewEdition ? route('admin.editions.teams.index', $edition) : null"
                    />
                    <x-stat-card
                        :label="__('Squad Players')"
                        :value="$squadPlayersCount"
                        icon="users"
                        :href="$canViewEdition ? route('admin.editions.squads.index', $edition) : null"
                    />
                </div>
            </section>

            {{-- Matches and results side by side on wide screens --}}
            <div class="grid gap-6 lg:grid-cols-2 lg:items-start lg:gap-8">
                <section class="adm-card adm-card-flush" aria-labelledby="dash-matches">
                    <div class="adm-card-head">
                        <div>
                            <h2 id="dash-matches" class="adm-card-title">{{ __('Matches Needing Attention') }}</h2>
                            <p class="adm-card-sub">{{ __('Live and toss first, then what is coming up.') }}</p>
                        </div>
                        @if($canOpenMatches)
                            <a href="{{ route('admin.matches.index', ['edition_id' => $edition->id]) }}" class="text-xs font-semibold text-link hover:text-link-hover">{{ __('All matches') }} &rarr;</a>
                        @endif
                    </div>

                    @forelse($matchesNeedingAttention as $match)
                        @php $names = __(':team_a vs :team_b', ['team_a' => $match->teamA->team->name, 'team_b' => $match->teamB->team->name]); @endphp
                        <div class="relative flex min-h-16 items-center justify-between gap-3 border-b border-line px-4 py-3 last:border-b-0 sm:px-5 {{ $canOpenMatches ? 'transition-colors hover:bg-hover' : '' }}">
                            <div class="min-w-0">
                                @if($canOpenMatches)
                                    <a href="{{ route('admin.matches.show', $match) }}" class="block truncate text-[13px] font-semibold text-slate-900 after:absolute after:inset-0 after:content-['']">{{ $names }}</a>
                                @else
                                    <span class="block truncate text-[13px] font-semibold text-slate-900">{{ $names }}</span>
                                @endif
                                <p class="mt-0.5 truncate text-[11px] text-slate-500">
                                    {{ display_datetime($match->scheduled_at, 'd M Y, h:i A') }}
                                    @if($match->venue)
                                        &middot; {{ $match->venue->name }}
                                    @endif
                                </p>
                            </div>
                            <x-status-badge :status="$match->match_status" />
                        </div>
                    @empty
                        <x-admin.empty icon="trophy">{{ __('No matches need attention right now.') }}</x-admin.empty>
                    @endforelse
                </section>

                <section class="adm-card adm-card-flush" aria-labelledby="dash-results">
                    <div class="adm-card-head">
                        <div>
                            <h2 id="dash-results" class="adm-card-title">{{ __('Recent Results') }}</h2>
                            <p class="adm-card-sub">{{ __('The last completed matches.') }}</p>
                        </div>
                    </div>

                    @forelse($recentResults as $match)
                        @php $names = __(':team_a vs :team_b', ['team_a' => $match->teamA->team->name, 'team_b' => $match->teamB->team->name]); @endphp
                        <div class="relative min-h-16 border-b border-line px-4 py-3 last:border-b-0 sm:px-5 {{ $canOpenMatches ? 'transition-colors hover:bg-hover' : '' }}">
                            @if($canOpenMatches)
                                <a href="{{ route('admin.matches.show', $match) }}" class="block truncate text-[13px] font-semibold text-slate-900 after:absolute after:inset-0 after:content-['']">{{ $names }}</a>
                            @else
                                <span class="block truncate text-[13px] font-semibold text-slate-900">{{ $names }}</span>
                            @endif
                            <p class="mt-0.5 text-[11px] text-slate-500">
                                {{ display_datetime($match->scheduled_at, 'd M Y') }} &middot; <span class="font-medium text-slate-700">{{ $match->match_result ?? '—' }}</span>
                            </p>
                        </div>
                    @empty
                        <x-admin.empty icon="trophy">{{ __('No completed matches yet.') }}</x-admin.empty>
                    @endforelse
                </section>
            </div>

            {{-- Quick actions: only the ones this role may use --}}
            @if($quickHtml !== '')
                <section aria-labelledby="dash-quick">
                    <h2 id="dash-quick" class="adm-kicker mb-3">{{ __('Quick actions') }}</h2>
                    {!! $quickHtml !!}
                </section>
            @endif

            @if($canViewFinance)
                {{-- Registration Payments, Finance and Contributions: the same figures the Finance,
                     Contributions and Registrations screens show, never recalculated here. The
                     contribution total is a subset of Finance income (every contribution has a matching
                     income transaction), not an addition to it - noted inline so it is never mistaken
                     for extra money. --}}
                <section aria-label="{{ __('Money') }}">
                    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3 xl:items-start">
                        <div class="adm-card adm-card-body">
                            <div class="mb-3 flex items-center justify-between gap-3">
                                <h2 class="adm-kicker">{{ __('Registration Payments') }}</h2>
                                @if($canOpenRegistrations)
                                    <a href="{{ route('admin.player-registrations.index', ['edition_id' => $edition->id]) }}" class="text-xs font-semibold text-link hover:text-link-hover">{{ __('View all') }} &rarr;</a>
                                @endif
                            </div>

                            @if($registeredPlayers > 0)
                                <dl class="grid grid-cols-2 gap-x-3 gap-y-4">
                                    <div>
                                        <dt class="text-xs text-slate-500">{{ __('Paid') }}</dt>
                                        <dd class="mt-0.5 text-xl font-bold tabular-nums text-slate-900">{{ $paidRegistrations }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs text-slate-500">{{ __('Pending Verification') }}</dt>
                                        <dd class="mt-0.5 text-xl font-bold tabular-nums">
                                            @if($canOpenRegistrations)
                                                <a
                                                    href="{{ route('admin.player-registrations.index', ['edition_id' => $edition->id, 'payment_status' => 'pending']) }}"
                                                    class="hover:underline {{ $pendingRegistrations > 0 ? 'text-amber-600' : 'text-slate-900' }}"
                                                >{{ $pendingRegistrations }}</a>
                                            @else
                                                <span class="{{ $pendingRegistrations > 0 ? 'text-amber-600' : 'text-slate-900' }}">{{ $pendingRegistrations }}</span>
                                            @endif
                                        </dd>
                                    </div>
                                    @if($failedRegistrations > 0)
                                        <div>
                                            <dt class="text-xs text-slate-500">{{ __('Failed') }}</dt>
                                            <dd class="mt-0.5 text-xl font-bold tabular-nums text-slate-900">{{ $failedRegistrations }}</dd>
                                        </div>
                                    @endif
                                    @if($refundedRegistrations > 0)
                                        <div>
                                            <dt class="text-xs text-slate-500">{{ __('Refunded') }}</dt>
                                            <dd class="mt-0.5 text-xl font-bold tabular-nums text-slate-900">{{ $refundedRegistrations }}</dd>
                                        </div>
                                    @endif
                                    <div class="col-span-2 border-t border-line pt-3">
                                        <dt class="text-xs text-slate-500">{{ __('Paid Amount') }}</dt>
                                        <dd class="mt-0.5 text-2xl font-bold tabular-nums tracking-tight text-slate-900">{{ money($paidRegistrationAmount) }}</dd>
                                    </div>
                                </dl>
                            @else
                                <p class="py-4 text-center text-[13px] text-slate-500">{{ __('No player registrations for this edition yet.') }}</p>
                            @endif
                        </div>

                        <div class="adm-card adm-card-body">
                            <div class="mb-3 flex items-center justify-between gap-3">
                                <h2 class="adm-kicker">{{ __('Finance') }}</h2>
                                <a href="{{ route('admin.edition-transactions.index', ['edition_id' => $edition->id]) }}" class="text-xs font-semibold text-link hover:text-link-hover">{{ __('View ledger') }} &rarr;</a>
                            </div>
                            <dl class="grid grid-cols-2 gap-x-3 gap-y-4">
                                <div>
                                    <dt class="text-xs text-slate-500">{{ __('Income') }}</dt>
                                    <dd class="mt-0.5 text-lg font-bold tabular-nums text-green-700">{{ money($financeSummary['income']) }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs text-slate-500">{{ __('Expenses') }}</dt>
                                    <dd class="mt-0.5 text-lg font-bold tabular-nums text-red-600">{{ money($financeSummary['expense']) }}</dd>
                                </div>
                                <div class="col-span-2 border-t border-line pt-3">
                                    <dt class="text-xs text-slate-500">{{ __('Balance') }}</dt>
                                    <dd class="mt-0.5 text-2xl font-bold tabular-nums tracking-tight {{ $financeSummary['balance'] < 0 ? 'text-red-600' : 'text-slate-900' }}">
                                        {{ money($financeSummary['balance']) }}
                                    </dd>
                                </div>
                            </dl>
                        </div>

                        <div class="adm-card adm-card-body md:col-span-2 xl:col-span-1">
                            <div class="mb-3 flex items-center justify-between gap-3">
                                <h2 class="adm-kicker">{{ __('Contributions') }}</h2>
                                <a href="{{ route('admin.edition-contributions.index', ['edition_id' => $edition->id]) }}" class="text-xs font-semibold text-link hover:text-link-hover">{{ __('View all') }} &rarr;</a>
                            </div>
                            <dl class="grid grid-cols-3 gap-x-3 gap-y-4">
                                <div class="col-span-3">
                                    <dt class="text-xs text-slate-500">{{ __('Total') }}</dt>
                                    <dd class="mt-0.5 text-2xl font-bold tabular-nums tracking-tight text-slate-900">{{ money($contributionTotal) }}</dd>
                                </div>
                                <div class="col-span-1">
                                    <dt class="text-xs text-slate-500">{{ __('Records') }}</dt>
                                    <dd class="mt-0.5 text-lg font-bold tabular-nums text-slate-900">{{ $contributionCount }}</dd>
                                </div>
                                <div class="col-span-2">
                                    <dt class="text-xs text-slate-500">{{ __('Contributors') }}</dt>
                                    <dd class="mt-0.5 text-lg font-bold tabular-nums text-slate-900">{{ $recognizedContributorsCount }}</dd>
                                </div>
                            </dl>
                            <p class="mt-3 text-[11px] leading-4 text-slate-500">{{ __('Already included in Finance income — shown separately for visibility only.') }}</p>
                        </div>
                    </div>

                    @can('reports.view')
                        <p class="mt-3 text-right">
                            <a href="{{ route('admin.reports.index', ['edition_id' => $edition->id]) }}" class="text-xs font-semibold text-link hover:text-link-hover">
                                {{ __('View full Reports') }} &rarr;
                            </a>
                        </p>
                    @endcan
                </section>
            @endif
        </div>
    @endif
@endsection
