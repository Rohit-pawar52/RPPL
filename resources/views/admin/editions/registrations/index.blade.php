@extends('layouts.admin')

@section('title', 'Registrations')

@section('actions')
    <x-admin.button :href="route('admin.player-registrations.review-pending', ['edition_id' => $edition->id])" variant="secondary">Review pending</x-admin.button>
    <x-admin.button :href="route('admin.player-registrations.export', ['edition_id' => $edition->id] + $filters)" variant="secondary" icon="document-chart">Export</x-admin.button>
@endsection

@section('content')
    @include('admin.editions._crumbs', ['edition' => $edition, 'section' => 'Registrations'])

    @php $canBulkAdd = $canAdd && $editionTeams->isNotEmpty(); @endphp

    <div class="mb-3">
        <x-table-filters :action="route('admin.editions.registrations.index', $edition)" :filters="$filters" :per-page="$perPage">
            <input
                type="text"
                name="search"
                value="{{ $filters['search'] ?? '' }}"
                placeholder="Search registration #, name, phone, village&hellip;"
                class="w-full max-w-[220px] rounded-md border border-slate-300 px-3 py-1.5 text-[13px] focus:outline-none focus:ring-2 focus:border-green-500 focus:ring-green-100"
            />

            <select name="payment_status" aria-label="Payment status" class="rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-[13px] focus:outline-none focus:ring-2 focus:border-green-500 focus:ring-green-100">
                <option value="">All payment statuses</option>
                @foreach(\App\Models\PlayerRegistration::PAYMENT_STATUSES as $status)
                    <option value="{{ $status }}" @selected(($filters['payment_status'] ?? '') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>

            <select name="team" aria-label="Team" class="rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-[13px] focus:outline-none focus:ring-2 focus:border-green-500 focus:ring-green-100">
                <option value="">All teams</option>
                <option value="{{ $withoutTeamValue }}" @selected(($filters['team'] ?? '') === $withoutTeamValue)>Without a team</option>
                @foreach($editionTeams as $editionTeam)
                    <option value="{{ $editionTeam->id }}" @selected(($filters['team'] ?? '') === (string) $editionTeam->id)>{{ $editionTeam->team->name }}</option>
                @endforeach
            </select>
        </x-table-filters>
    </div>

    @if(! $canAdd)
        <p class="mb-3 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
            This season is completed, so players cannot be added to a team.
        </p>
    @elseif($editionTeams->isEmpty())
        <p class="mb-3 text-xs text-slate-500">
            @can('viewAny', \App\Models\EditionTeam::class)
                No teams in this season yet &mdash; <a href="{{ route('admin.editions.teams.index', $edition) }}" class="font-medium text-green-700 hover:underline">add teams</a> to put players in them.
            @else
                No teams in this season yet.
            @endcan
        </p>
    @else
        {{-- Bulk "Add to team": the row checkboxes below belong to this form
             through their form="" attribute. The button appears once at
             least one player is ticked. --}}
        <form id="add-to-team-form" method="POST" action="{{ route('admin.editions.registrations.add-to-team', $edition) }}" class="mb-3 flex flex-wrap items-center gap-2">
            @csrf
            @foreach(array_merge($filters, ['per_page' => request('per_page'), 'page' => request('page')]) as $name => $value)
                @if(filled($value))
                    <input type="hidden" name="{{ $name }}" value="{{ $value }}" />
                @endif
            @endforeach

            <select name="edition_team_id" required aria-label="Team to add the ticked players to" class="rounded-md border border-slate-300 bg-white px-2.5 py-1.5 text-[13px] focus:outline-none focus:ring-2 focus:border-green-500 focus:ring-green-100">
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
                class="inline-flex items-center justify-center gap-1.5 whitespace-nowrap rounded-md bg-green-600 px-3 py-1.5 text-[13px] font-medium text-white hover:bg-green-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-green-500 focus-visible:ring-offset-1"
            >Add to team</button>
        </form>
    @endif

    <x-admin.card :title="'Registrations in '.$edition->name.' ('.$registrations->total().')'" flush>
        <div class="overflow-x-auto" @if($canBulkAdd) data-row-selection="#add-to-team-button" @endif>
            <table class="w-full min-w-[640px] text-left text-[13px]">
                <thead class="border-b border-slate-200 bg-slate-50 text-[11px] uppercase tracking-wide text-slate-400">
                    <tr>
                        <th class="w-8 px-4 py-2">
                            @if($canBulkAdd)
                                <input type="checkbox" data-select-all aria-label="Select all players without a team on this page" />
                            @endif
                        </th>
                        <th class="px-4 py-2 font-medium">Registration #</th>
                        <th class="px-4 py-2 font-medium">Player</th>
                        <th class="hidden px-4 py-2 font-medium md:table-cell">Mobile</th>
                        <th class="px-4 py-2 font-medium">Payment</th>
                        <th class="px-4 py-2 font-medium">Team</th>
                        <th class="hidden px-4 py-2 font-medium lg:table-cell">Registered</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($registrations as $registration)
                        @php
                            $teamPlayer = $registration->teamPlayer;
                            $selectable = $canBulkAdd && ! $teamPlayer && $registration->player->is_active;
                        @endphp
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-1.5">
                                @if($selectable)
                                    <input
                                        type="checkbox"
                                        data-row-checkbox
                                        form="add-to-team-form"
                                        name="selected[]"
                                        value="{{ $registration->id }}"
                                        aria-label="Select {{ $registration->player->name }}"
                                    />
                                @endif
                            </td>
                            <td class="px-4 py-1.5 font-mono text-[12px] text-slate-600">
                                <a href="{{ route('admin.player-registrations.show', $registration) }}" class="hover:underline">{{ $registration->registration_number }}</a>
                            </td>
                            <td class="px-4 py-1.5 font-medium text-slate-800">
                                <a href="{{ route('admin.player-registrations.show', $registration) }}" class="hover:underline">{{ $registration->player->name }}</a>
                                @unless($registration->player->is_active)
                                    <span class="ml-1 text-[10px] font-normal text-slate-400">(inactive)</span>
                                @endunless
                                @if($registration->village)
                                    <span class="block text-[11px] font-normal text-slate-400">{{ $registration->village }}</span>
                                @endif
                            </td>
                            <td class="hidden px-4 py-1.5 text-slate-600 md:table-cell">{{ $registration->player->phone ?? '—' }}</td>
                            <td class="px-4 py-1.5"><x-status-badge :status="$registration->payment_status" /></td>
                            <td class="px-4 py-1.5 text-slate-600">{{ $teamPlayer?->editionTeam->team->name ?? '—' }}</td>
                            <td class="hidden px-4 py-1.5 text-slate-500 lg:table-cell">{{ display_datetime($registration->registered_at, 'd M Y') ?? '—' }}</td>
                        </tr>
                    @empty
                        <x-admin.empty table colspan="7">No registrations found.</x-admin.empty>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-admin.card>

    <div class="mt-3">
        {{ $registrations->links() }}
    </div>
@endsection
