@extends('layouts.admin')

@section('title', 'Matches')

@section('content')
    @include('admin.editions._crumbs', ['edition' => $edition, 'section' => 'Matches'])

    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <form method="GET" action="{{ route('admin.editions.matches.index', $edition) }}" id="season-match-filter" class="flex items-center gap-2">
            <label for="match_status" class="sr-only">Status</label>
            <select name="match_status" id="match_status" onchange="this.form.submit()" class="h-8 rounded-md border border-slate-300 bg-white px-2.5 text-[13px] focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-100">
                <option value="">All statuses</option>
                @foreach(\App\Models\GameMatch::STATUSES as $option)
                    <option value="{{ $option }}" @selected($status === $option)>{{ ucfirst($option) }}</option>
                @endforeach
            </select>
            <noscript><x-admin.button type="submit" variant="secondary" size="sm">Filter</x-admin.button></noscript>
            @if($status)
                <a href="{{ route('admin.editions.matches.index', $edition) }}" class="text-xs text-slate-500 hover:underline">Clear</a>
            @endif
        </form>

        @can('create', \App\Models\GameMatch::class)
            @if($canAdd)
                <x-admin.button :href="route('admin.editions.matches.create', $edition)">+ Add match</x-admin.button>
            @else
                <span class="rounded-md border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs text-amber-800">
                    This season is completed, so new matches cannot be scheduled.
                </span>
            @endif
        @endcan
    </div>

    <x-admin.card :title="'Matches in '.$edition->name.' ('.$matches->total().')'" flush>
        <table class="w-full min-w-[720px] text-left text-[13px]">
            <thead class="border-b border-slate-200 bg-slate-50 text-[11px] uppercase tracking-wide text-slate-400">
                <tr>
                    <th class="px-4 py-2 font-medium">#</th>
                    <th class="px-4 py-2 font-medium">Teams</th>
                    <th class="px-4 py-2 font-medium">When</th>
                    <th class="px-4 py-2 font-medium">Stage</th>
                    <th class="px-4 py-2 text-right font-medium">Overs</th>
                    <th class="px-4 py-2 font-medium">Venue</th>
                    <th class="px-4 py-2 font-medium">Status</th>
                    <th class="px-4 py-2 text-right font-medium"><span class="sr-only">Actions</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($matches as $match)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-1.5 text-slate-600">{{ $match->match_number ?? '—' }}</td>
                        <td class="px-4 py-1.5 font-medium text-slate-800">
                            <a href="{{ route('admin.matches.show', $match) }}" class="hover:underline">
                                {{ $match->teamA->team->name }} vs {{ $match->teamB->team->name }}
                            </a>
                        </td>
                        <td class="whitespace-nowrap px-4 py-1.5 text-slate-600">{{ display_datetime($match->scheduled_at, 'd M Y, h:i A') }}</td>
                        <td class="px-4 py-1.5 text-slate-600">{{ $match->match_stage ? ucwords(str_replace('_', ' ', $match->match_stage)) : '—' }}</td>
                        <td class="px-4 py-1.5 text-right text-slate-600">{{ $match->overs_per_innings ?? '—' }}</td>
                        <td class="px-4 py-1.5 text-slate-600">{{ $match->venue->name ?? 'TBD' }}</td>
                        <td class="px-4 py-1.5"><x-status-badge :status="$match->match_status" /></td>
                        <td class="px-4 py-1.5">
                            <div class="flex items-center justify-end gap-1">
                                <a href="{{ route('admin.matches.show', $match) }}" title="View" aria-label="View match" class="rounded p-1.5 text-slate-500 hover:bg-slate-100 hover:text-slate-700">
                                    <x-icon name="eye" class="h-4 w-4" />
                                </a>
                                @can('update', $match)
                                    <a href="{{ route('admin.matches.edit', $match) }}" title="Edit" aria-label="Edit match" class="rounded p-1.5 text-slate-500 hover:bg-slate-100 theme-hover-primary">
                                        <x-icon name="pencil" class="h-4 w-4" />
                                    </a>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <x-admin.empty table colspan="8">
                        @if($status)
                            No {{ $status }} matches in this season.
                        @else
                            No matches in this season yet.
                        @endif
                    </x-admin.empty>
                @endforelse
            </tbody>
        </table>
    </x-admin.card>

    <div class="mt-3">
        {{ $matches->links() }}
    </div>
@endsection
