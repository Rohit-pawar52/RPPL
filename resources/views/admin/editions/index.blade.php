@extends('layouts.admin')

@section('title', 'Editions')

@section('subtitle', 'Every season of the tournament. Open one to run it step by step.')

@section('actions')
    <x-admin.button href="{{ route('admin.editions.create') }}" variant="primary">+ New edition</x-admin.button>
@endsection

@section('content')
    @php
        $statusTabs = ['' => 'All'] + collect(\App\Models\Edition::STATUSES)->mapWithKeys(fn ($s) => [$s => ucfirst($s)])->all();
        $activeStatus = $filters['status'] ?? '';
    @endphp

    <div class="space-y-4">
        <nav class="ops-chips" aria-label="Edition status">
            @foreach($statusTabs as $value => $label)
                <a
                    href="{{ request()->fullUrlWithQuery(['status' => $value === '' ? null : $value, 'page' => null]) }}"
                    @class(['ops-chip', 'ops-chip-active' => $activeStatus === $value])
                    @if($activeStatus === $value) aria-current="page" @endif
                >{{ $label }}</a>
            @endforeach
        </nav>

        <form method="GET" action="{{ route('admin.editions.index') }}" class="flex flex-wrap items-center gap-2">
            @if($activeStatus !== '')<input type="hidden" name="status" value="{{ $activeStatus }}" />@endif
            <div class="relative min-w-0 flex-1 basis-48 sm:max-w-xs">
                <x-ops.icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Search by name&hellip;" aria-label="Search editions" class="ops-input pl-9" />
            </div>
            <input type="number" name="year" value="{{ $filters['year'] ?? '' }}" placeholder="Year" aria-label="Year" class="ops-input w-24" />
            <button type="submit" class="btn btn-secondary min-h-10">Filter</button>
            @if(array_filter($filters))
                <a href="{{ route('admin.editions.index') }}" class="ops-link text-[13px]">Clear filters</a>
            @endif
        </form>

        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            @forelse($editions as $edition)
                <article class="group relative flex flex-col gap-4 rounded-xl border border-line bg-white p-4 shadow-card transition hover:-translate-y-px hover:shadow-raised sm:p-5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="ops-kicker">Season {{ $edition->year }}</p>
                            <a href="{{ route('admin.editions.show', $edition) }}" class="block truncate text-lg font-bold tracking-tight text-slate-900 after:absolute after:inset-0 after:content-[''] hover:underline">{{ $edition->name }}</a>
                        </div>
                        <x-status-badge :status="$edition->status" />
                    </div>

                    <dl class="grid grid-cols-3 gap-2 text-center">
                        <div class="rounded-lg bg-slate-50 py-2">
                            <dd class="text-xl font-bold tabular-nums text-slate-900">{{ $edition->player_registrations_count }}</dd>
                            <dt class="text-[11px] text-slate-500">Registrations</dt>
                        </div>
                        <div class="rounded-lg bg-slate-50 py-2">
                            <dd class="text-xl font-bold tabular-nums text-slate-900">{{ $edition->edition_teams_count }}</dd>
                            <dt class="text-[11px] text-slate-500">Teams</dt>
                        </div>
                        <div class="rounded-lg bg-slate-50 py-2">
                            <dd class="text-xl font-bold tabular-nums text-slate-900">{{ $edition->matches_count }}</dd>
                            <dt class="text-[11px] text-slate-500">Matches</dt>
                        </div>
                    </dl>

                    <p class="-mt-1 text-[11px] text-slate-400">Added {{ display_datetime($edition->created_at, 'd M Y') }}</p>

                    <div class="relative z-10 mt-auto flex items-center justify-between gap-2">
                        <span class="inline-flex items-center gap-1 text-[13px] font-semibold text-link">Open season <x-ops.icon name="arrow-right" class="h-4 w-4" /></span>
                        <div class="flex items-center gap-1">
                            <a href="{{ route('admin.editions.edit', $edition) }}" title="Edit" aria-label="Edit {{ $edition->name }}" class="btn btn-ghost btn-icon">
                                <x-icon name="pencil" class="h-4 w-4" />
                            </a>
                            <form
                                method="POST"
                                action="{{ route('admin.editions.destroy', $edition) }}"
                                data-confirm-delete
                                data-confirm-title="Delete {{ $edition->name }}?"
                                data-confirm-text="This cannot be undone. Editions with existing tournament data cannot be deleted."
                            >
                                @csrf
                                @method('DELETE')
                                <button type="submit" title="Delete" aria-label="Delete {{ $edition->name }}" class="btn btn-ghost btn-icon hover:!bg-red-50 hover:!text-red-600">
                                    <x-icon name="trash" class="h-4 w-4" />
                                </button>
                            </form>
                        </div>
                    </div>
                </article>
            @empty
                <div class="col-span-full">
                    <x-admin.empty icon="calendar" class="ops-card py-14">
                        No editions found.
                        <x-slot:action>
                            @if(array_filter($filters))
                                <a href="{{ route('admin.editions.index') }}" class="btn btn-secondary btn-sm">Clear filters</a>
                            @else
                                <a href="{{ route('admin.editions.create') }}" class="btn btn-primary btn-sm">+ Create the first edition</a>
                            @endif
                        </x-slot:action>
                    </x-admin.empty>
                </div>
            @endforelse
        </div>

        <div>
            {{ $editions->links() }}
        </div>
    </div>
@endsection
