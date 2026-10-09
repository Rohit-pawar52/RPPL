@extends('layouts.admin')

@section('title', 'Matches')

@section('subtitle', 'Schedule, start, score and finish every match from here.')

@section('actions')
    <x-selected-report-action
        id="matches-selected-export"
        :action="route('admin.matches.export-selected')"
        label="Export Selected ({count})"
    />
    <x-admin.button :href="route('admin.matches.export', $filters)" variant="secondary" icon="document-chart">Export</x-admin.button>
    @can('create', \App\Models\GameMatch::class)
        <x-admin.button href="{{ route('admin.matches.create') }}" variant="primary">+ Schedule match</x-admin.button>
    @endcan
@endsection

@section('content')
    @php
        $statusCounts = \App\Models\GameMatch::query()
            ->when($filters['edition_id'] ?? null, fn ($query, $editionId) => $query->where('edition_id', $editionId))
            ->selectRaw('match_status, count(*) as total')
            ->groupBy('match_status')
            ->pluck('total', 'match_status');
        $activeStatus = $filters['match_status'] ?? '';
        $statusTabs = ['' => 'All'] + collect(\App\Models\GameMatch::STATUSES)->mapWithKeys(fn ($s) => [$s => ucfirst($s)])->all();
        $liveCount = (int) ($statusCounts['live'] ?? 0);
    @endphp

    <div class="space-y-4">
        @if($liveCount > 0 && $activeStatus !== 'live')
            <a href="{{ request()->fullUrlWithQuery(['match_status' => 'live', 'page' => null]) }}" class="flex items-center gap-3 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-[13px] font-medium text-green-800 transition hover:bg-green-100">
                <span class="relative flex h-2.5 w-2.5"><span class="absolute inline-flex h-full w-full rounded-full bg-green-500 opacity-60 motion-safe:animate-ping"></span><span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-green-600"></span></span>
                {{ $liveCount }} {{ \Illuminate\Support\Str::plural('match', $liveCount) }} live right now &mdash; tap to open
                <x-ops.icon name="arrow-right" class="ml-auto" />
            </a>
        @endif

        <nav class="ops-chips" aria-label="Match status">
            @foreach($statusTabs as $value => $label)
                @php $count = $value === '' ? $statusCounts->sum() : (int) ($statusCounts[$value] ?? 0); @endphp
                <a
                    href="{{ request()->fullUrlWithQuery(['match_status' => $value === '' ? null : $value, 'page' => null]) }}"
                    @class(['ops-chip', 'ops-chip-active' => $activeStatus === $value])
                    @if($activeStatus === $value) aria-current="page" @endif
                >
                    {{ $label }}
                    <span class="ops-chip-count">{{ $count }}</span>
                </a>
            @endforeach
        </nav>

        <form method="GET" action="{{ route('admin.matches.index') }}" class="space-y-2">
            @if($activeStatus !== '')
                <input type="hidden" name="match_status" value="{{ $activeStatus }}" />
            @endif
            <div class="flex flex-wrap items-center gap-2">
                <div class="relative min-w-0 flex-1 basis-56 sm:max-w-md">
                    <x-ops.icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                    <input
                        type="search"
                        name="search"
                        value="{{ $filters['search'] ?? '' }}"
                        enterkeyhint="search"
                        placeholder="Search team or venue"
                        aria-label="Search matches"
                        class="ops-input pl-9"
                    />
                </div>
                <select name="edition_id" onchange="this.form.submit()" aria-label="Season" class="ops-input w-auto max-w-44 sm:max-w-none">
                    <option value="">All editions</option>
                    @foreach($editions as $edition)
                        <option value="{{ $edition->id }}" @selected(($filters['edition_id'] ?? '') == $edition->id)>{{ $edition->name }}</option>
                    @endforeach
                </select>
                <button type="submit" class="btn btn-secondary min-h-10">Search</button>
                @if(array_filter($filters))
                    <a href="{{ route('admin.matches.index') }}" class="ops-link text-[13px]">Clear all</a>
                @endif
            </div>
            <details class="group text-xs" @if(! empty($filters['from_date']) || ! empty($filters['to_date'])) open @endif>
                <summary class="inline-flex min-h-9 cursor-pointer list-none items-center gap-1 font-medium text-slate-500 hover:text-slate-800">
                    <x-ops.icon name="chevron-right" class="h-3.5 w-3.5 transition group-open:rotate-90" />
                    Dates and rows per page
                </summary>
                <div class="mt-2 flex flex-wrap items-end gap-3">
                    <div>
                        <label class="ops-label" for="mi-from">Played from</label>
                        <input id="mi-from" type="date" name="from_date" value="{{ $filters['from_date'] ?? '' }}" class="ops-input w-40" />
                    </div>
                    <div>
                        <label class="ops-label" for="mi-to">to</label>
                        <input id="mi-to" type="date" name="to_date" value="{{ $filters['to_date'] ?? '' }}" class="ops-input w-40" />
                    </div>
                    <div>
                        <label class="ops-label" for="mi-per">Rows</label>
                        <select id="mi-per" name="per_page" onchange="this.form.submit()" class="ops-input w-auto">
                            @foreach([10, 20, 50, 100, 200] as $option)
                                <option value="{{ $option }}" @selected((int) $perPage === $option)>{{ $option }} / page</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-secondary min-h-10">Apply</button>
                </div>
            </details>
        </form>

        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500">
            <span class="ops-kicker">Sort</span>
            <x-sortable-header column="scheduled_at" :sort="$sort" :direction="$direction">Scheduled</x-sortable-header>
            <x-sortable-header column="match_status" :sort="$sort" :direction="$direction">Status</x-sortable-header>
        </div>

        <div class="ops-card" data-row-selection="#matches-selected-export-button">
            <div class="flex items-center gap-3 border-b border-line px-3 py-2 sm:px-4">
                <label class="flex min-h-9 cursor-pointer items-center gap-2 text-xs font-medium text-slate-500">
                    <input type="checkbox" data-select-all class="h-4 w-4 rounded border-slate-300" aria-label="Select all matches on this page" />
                    Select all on this page
                </label>
                <span class="ml-auto text-xs tabular-nums text-slate-400">{{ $matches->total() }} {{ \Illuminate\Support\Str::plural('match', $matches->total()) }}</span>
            </div>

            <div class="divide-y divide-line">
                @forelse($matches as $match)
                    @php
                        $status = $match->match_status;
                        $liveInnings = $status === 'live' ? $match->innings()->where('status', 'live')->first() : null;
                        $canScore = $liveInnings && auth()->user()->can('score', $match);
                    @endphp
                    <div class="relative flex flex-wrap items-center gap-x-3 gap-y-3 p-3 transition hover:bg-hover/50 sm:p-4 lg:flex-nowrap lg:gap-x-4">
                        <div class="relative z-10 self-start pt-1 lg:self-center lg:pt-0">
                            <input
                                type="checkbox"
                                data-row-checkbox
                                form="matches-selected-export"
                                name="selected_ids[]"
                                value="{{ $match->id }}"
                                class="h-4 w-4 rounded border-slate-300"
                                aria-label="Select match {{ $match->teamA->team->name }} vs {{ $match->teamB->team->name }}"
                            />
                        </div>

                        {{-- When --}}
                        <div class="w-16 shrink-0 text-center">
                            <p class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ display_datetime($match->scheduled_at, 'M') }}</p>
                            <p class="text-2xl font-bold leading-6 tabular-nums text-slate-900">{{ display_datetime($match->scheduled_at, 'd') }}</p>
                            <p class="mt-0.5 text-[11px] tabular-nums text-slate-500">{{ display_datetime($match->scheduled_at, 'h:i A') }}</p>
                        </div>

                        {{-- Who plays --}}
                        <div class="min-w-0 flex-1 basis-40">
                            <a href="{{ route('admin.matches.show', $match) }}" class="block break-words text-[15px] font-semibold leading-5 text-slate-900 after:absolute after:inset-0 after:content-[''] hover:underline">
                                {{ $match->teamA->team->name }} <span class="font-normal text-slate-400">vs</span> {{ $match->teamB->team->name }}
                            </a>
                            <p class="mt-1 truncate text-xs text-slate-500">
                                @if($match->match_number) Match {{ $match->match_number }} &middot; @endif
                                @if(empty($filters['edition_id'])) {{ $match->edition->name }} &middot; @endif
                                {{ $match->venue->name ?? 'Venue to be decided' }}
                            </p>
                            @if($status === 'completed' && $match->match_result)
                                <p class="mt-1 line-clamp-2 text-xs font-medium text-slate-700">{{ $match->match_result }}</p>
                            @endif
                            <div class="mt-2 lg:hidden"><x-status-badge :status="$status" /></div>
                        </div>

                        <div class="min-w-0 max-lg:hidden"><x-status-badge :status="$status" /></div>

                        {{-- The one thing to do next --}}
                        <div class="relative z-10 flex items-center gap-2 max-lg:w-full max-lg:pl-[calc(1rem+0.75rem)] lg:justify-end">
                            @if($canScore)
                                <a href="{{ route('admin.matches.innings.score', [$match, $liveInnings]) }}" class="btn btn-primary min-h-10 max-lg:flex-1 lg:min-h-9"><x-ops.icon name="bolt" /> Score</a>
                            @elseif($status === 'scheduled' || $status === 'toss')
                                <a href="{{ route('admin.matches.show', $match) }}" class="btn btn-primary min-h-10 max-lg:flex-1 lg:min-h-9">
                                    {{ $status === 'toss' ? 'Continue' : 'Start' }} <x-ops.icon name="arrow-right" />
                                </a>
                            @elseif($status === 'live')
                                <a href="{{ route('admin.matches.show', $match) }}" class="btn btn-primary min-h-10 max-lg:flex-1 lg:min-h-9">Open match</a>
                            @else
                                <a href="{{ route('admin.matches.show', $match) }}" class="btn btn-secondary min-h-10 max-lg:flex-1 lg:min-h-9">Result</a>
                            @endif

                            <details class="relative" data-more>
                                <summary class="btn btn-ghost btn-icon btn-sm min-h-10 w-10 cursor-pointer list-none lg:min-h-9 lg:w-9" aria-label="More actions">
                                    <x-ops.icon name="dots" class="h-5 w-5" />
                                </summary>
                                <div class="ops-menu">
                                    <a href="{{ route('admin.matches.show', $match) }}" class="ops-menu-item"><x-icon name="eye" class="h-4 w-4" /> Open</a>
                                    @can('update', $match)
                                        <a href="{{ route('admin.matches.edit', $match) }}" class="ops-menu-item"><x-icon name="pencil" class="h-4 w-4" /> Edit</a>
                                    @endcan
                                    @can('delete', $match)
                                        <form
                                            method="POST"
                                            action="{{ route('admin.matches.destroy', $match) }}"
                                            data-confirm-delete
                                            data-confirm-title="Delete this match?"
                                            data-confirm-text="This cannot be undone. Matches with existing squad or scoring data cannot be deleted."
                                        >
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="ops-menu-item w-full text-red-600 hover:!bg-red-50"><x-icon name="trash" class="h-4 w-4" /> Delete</button>
                                        </form>
                                    @endcan
                                </div>
                            </details>
                        </div>
                    </div>
                @empty
                    <x-admin.empty icon="trophy" class="py-14">
                        @if(array_filter($filters))
                            No matches match these filters.
                        @else
                            No matches scheduled yet.
                        @endif
                        <x-slot:action>
                            @if(array_filter($filters))
                                <a href="{{ route('admin.matches.index') }}" class="btn btn-secondary btn-sm">Clear filters</a>
                            @else
                                @can('create', \App\Models\GameMatch::class)
                                    <a href="{{ route('admin.matches.create') }}" class="btn btn-primary btn-sm">+ Schedule the first match</a>
                                @endcan
                            @endif
                        </x-slot:action>
                    </x-admin.empty>
                @endforelse
            </div>
        </div>

        <div>
            {{ $matches->links() }}
        </div>
    </div>

    <script>
        document.addEventListener('click', function (event) {
            document.querySelectorAll('[data-more][open]').forEach(function (menu) {
                if (!menu.contains(event.target)) { menu.removeAttribute('open'); }
            });
        });
    </script>
@endsection
