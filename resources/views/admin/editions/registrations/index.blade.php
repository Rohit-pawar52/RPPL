@extends('layouts.admin')

@section('title', 'Registrations')

@section('actions')
    <x-admin.button :href="route('admin.player-registrations.review-pending', ['edition_id' => $edition->id])" variant="secondary">Review pending</x-admin.button>
    <x-admin.button :href="route('admin.player-registrations.export', ['edition_id' => $edition->id] + $filters)" variant="secondary" icon="document-chart">Export</x-admin.button>
@endsection

@section('content')
    @include('admin.editions._crumbs', ['edition' => $edition, 'section' => 'Registrations'])

    @php
        $canBulkAdd = $canAdd && $editionTeams->isNotEmpty();
        $statusCounts = \App\Models\PlayerRegistration::query()
            ->where('edition_id', $edition->id)
            ->selectRaw('payment_status, count(*) as total')
            ->groupBy('payment_status')
            ->pluck('total', 'payment_status');
        $activeStatus = $filters['payment_status'] ?? '';
        $statusTabs = ['' => 'All'] + collect(\App\Models\PlayerRegistration::PAYMENT_STATUSES)->mapWithKeys(fn ($s) => [$s => ucfirst($s)])->all();
        $withoutTeamActive = ($filters['team'] ?? '') === $withoutTeamValue;
    @endphp

    <div class="space-y-3">
        <nav class="ops-chips" aria-label="Payment status">
            @foreach($statusTabs as $value => $label)
                @php $count = $value === '' ? $statusCounts->sum() : (int) ($statusCounts[$value] ?? 0); @endphp
                <a
                    href="{{ request()->fullUrlWithQuery(['payment_status' => $value === '' ? null : $value, 'page' => null]) }}"
                    @class(['ops-chip', 'ops-chip-active' => $activeStatus === $value])
                    @if($activeStatus === $value) aria-current="page" @endif
                >{{ $label }} <span class="ops-chip-count">{{ $count }}</span></a>
            @endforeach
            <a
                href="{{ request()->fullUrlWithQuery(['team' => $withoutTeamActive ? null : $withoutTeamValue, 'page' => null]) }}"
                @class(['ops-chip', 'ops-chip-active' => $withoutTeamActive])
            >Without a team</a>
        </nav>

        <form method="GET" action="{{ route('admin.editions.registrations.index', $edition) }}" class="flex flex-wrap items-center gap-2">
            @if($activeStatus !== '')<input type="hidden" name="payment_status" value="{{ $activeStatus }}" />@endif
            <div class="relative min-w-0 flex-1 basis-56 sm:max-w-md">
                <x-ops.icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input
                    type="search"
                    name="search"
                    value="{{ $filters['search'] ?? '' }}"
                    enterkeyhint="search"
                    placeholder="Search name, phone, reg. no. or village"
                    aria-label="Search registrations"
                    class="ops-input pl-9"
                />
            </div>
            <select name="team" onchange="this.form.submit()" aria-label="Team" class="ops-input w-auto max-w-44 sm:max-w-none">
                <option value="">All teams</option>
                <option value="{{ $withoutTeamValue }}" @selected($withoutTeamActive)>Without a team</option>
                @foreach($editionTeams as $editionTeam)
                    <option value="{{ $editionTeam->id }}" @selected(($filters['team'] ?? '') === (string) $editionTeam->id)>{{ $editionTeam->team->name }}</option>
                @endforeach
            </select>
            <select name="per_page" onchange="this.form.submit()" aria-label="Rows per page" class="ops-input w-auto">
                @foreach([10, 20, 50, 100, 200] as $option)
                    <option value="{{ $option }}" @selected((int) $perPage === $option)>{{ $option }} / page</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-secondary min-h-10">Search</button>
            @if(array_filter($filters))
                <a href="{{ route('admin.editions.registrations.index', $edition) }}" class="ops-link text-[13px]">Clear all</a>
            @endif
        </form>

        @if(! $canAdd)
            <p class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                This season is completed, so players cannot be added to a team.
            </p>
        @elseif($editionTeams->isEmpty())
            <p class="text-xs text-slate-500">
                @can('viewAny', \App\Models\EditionTeam::class)
                    No teams in this season yet &mdash; <a href="{{ route('admin.editions.teams.index', $edition) }}" class="font-medium text-link hover:underline">add teams</a> to put players in them.
                @else
                    No teams in this season yet.
                @endcan
            </p>
        @else
            {{-- Bulk "Add to team": the row checkboxes below belong to this form
                 through their form="" attribute. The button appears once at
                 least one player is ticked. --}}
            <form id="add-to-team-form" method="POST" action="{{ route('admin.editions.registrations.add-to-team', $edition) }}" class="flex flex-wrap items-center gap-2 rounded-xl border border-line bg-white px-3 py-2.5 shadow-card">
                @csrf
                @foreach(array_merge($filters, ['per_page' => request('per_page'), 'page' => request('page')]) as $name => $value)
                    @if(filled($value))
                        <input type="hidden" name="{{ $name }}" value="{{ $value }}" />
                    @endif
                @endforeach

                <x-ops.icon name="users" class="h-4 w-4 text-slate-400" />
                <select name="edition_team_id" required aria-label="Team to add the ticked players to" class="ops-input w-auto min-w-48 flex-1 sm:flex-none">
                    <option value="">Add ticked players to&hellip;</option>
                    @foreach($editionTeams as $editionTeam)
                        <option value="{{ $editionTeam->id }}">{{ $editionTeam->team->name }}</option>
                    @endforeach
                </select>
                <button
                    type="submit"
                    id="add-to-team-button"
                    data-label="Add to team ({count})"
                    hidden
                    class="btn btn-primary min-h-10"
                >Add to team</button>
                <span class="text-xs text-slate-400">Tick players below who are not in a team yet.</span>
            </form>
        @endif

        <section class="ops-card" @if($canBulkAdd) data-row-selection="#add-to-team-button" @endif>
            <div class="flex items-center gap-3 border-b border-line px-3 py-2 sm:px-4">
                @if($canBulkAdd)
                    <label class="flex min-h-9 cursor-pointer items-center gap-2 text-xs font-medium text-slate-500">
                        <input type="checkbox" data-select-all class="h-4 w-4 rounded border-slate-300" aria-label="Select all players without a team on this page" />
                        Select all without a team
                    </label>
                @endif
                <h3 class="ops-title ml-0 text-[13px]">Registrations in {{ $edition->name }} ({{ $registrations->total() }})</h3>
            </div>

            <div class="divide-y divide-line">
                @forelse($registrations as $registration)
                    @php
                        $teamPlayer = $registration->teamPlayer;
                        $selectable = $canBulkAdd && ! $teamPlayer && $registration->player->is_active;
                    @endphp
                    <div class="relative flex flex-wrap items-center gap-x-3 gap-y-2 p-3 transition hover:bg-hover/50 sm:p-4">
                        <div class="relative z-10 flex w-5 shrink-0 justify-center">
                            @if($selectable)
                                <input
                                    type="checkbox"
                                    data-row-checkbox
                                    form="add-to-team-form"
                                    name="selected[]"
                                    value="{{ $registration->id }}"
                                    class="h-4 w-4 rounded border-slate-300"
                                    aria-label="Select {{ $registration->player->name }}"
                                />
                            @endif
                        </div>
                        <x-media-image :path="$registration->player->photo_path" kind="user" alt="" loading="lazy" class="h-10 w-10 shrink-0 rounded-full bg-slate-100 object-cover" />
                        <div class="min-w-0 flex-1 basis-44">
                            <a href="{{ route('admin.player-registrations.show', $registration) }}" class="block truncate text-sm font-semibold text-slate-900 after:absolute after:inset-0 after:content-[''] hover:underline">{{ $registration->player->name }}</a>
                            <p class="truncate text-xs text-slate-500">
                                <span class="font-mono text-[11px]">{{ $registration->registration_number }}</span>
                                @if($registration->player->phone) &middot; {{ $registration->player->phone }} @endif
                                @if($registration->village) &middot; {{ $registration->village }} @endif
                                @unless($registration->player->is_active) &middot; <span class="text-slate-400">inactive</span> @endunless
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <x-status-badge :status="$registration->payment_status" />
                            <span class="min-w-24 text-xs text-slate-600">{{ $teamPlayer?->editionTeam->team->name ?? '—' }}</span>
                            <span class="hidden text-xs tabular-nums text-slate-400 lg:inline">{{ display_datetime($registration->registered_at, 'd M Y') ?? '—' }}</span>
                        </div>
                    </div>
                @empty
                    <x-admin.empty icon="clipboard" class="py-12">No registrations found.</x-admin.empty>
                @endforelse
            </div>
        </section>

        <div>
            {{ $registrations->links() }}
        </div>
    </div>
@endsection
