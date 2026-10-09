@extends('layouts.admin')

@section('title', 'Teams')

@section('content')
    @include('admin.editions._crumbs', ['edition' => $edition, 'section' => 'Teams'])

    {{-- A team's name opens its squad, which needs the teams permission (this page only needs editions.view):
         without it the name stays plain text. --}}
    @php $canOpenSquads = auth()->user()->can('viewAny', \App\Models\TeamPlayer::class); @endphp

    <div class="grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_21rem]">
        {{-- Teams already in this season --}}
        <section class="ops-card">
            <div class="ops-card-head">
                <h3 class="ops-title">Teams in {{ $edition->name }} ({{ $editionTeams->count() }})</h3>
            </div>

            <div class="divide-y divide-line">
                @forelse($editionTeams as $editionTeam)
                    @php $matchCount = $editionTeam->matches_as_team_a_count + $editionTeam->matches_as_team_b_count; @endphp
                    <div class="relative flex flex-wrap items-center gap-x-4 gap-y-2 p-3 transition hover:bg-hover/50 sm:p-4">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-full border border-line bg-slate-50 text-slate-300">
                            <x-media-image :path="$editionTeam->team->logo_path" kind="image" alt="" loading="lazy" class="h-full w-full object-cover" />
                        </span>
                        <div class="min-w-0 flex-1 basis-36">
                            <a @if($canOpenSquads) href="{{ route('admin.editions.squads.show', [$edition, $editionTeam]) }}" @endif class="block truncate text-[15px] font-semibold text-slate-900{{ $canOpenSquads ? ' after:absolute after:inset-0 after:content-[\'\'] hover:underline' : '' }}">
                                {{ $editionTeam->team->name }}
                                @if($editionTeam->team->short_name)
                                    <span class="text-[11px] font-normal text-slate-400">{{ $editionTeam->team->short_name }}</span>
                                @endif
                            </a>
                            <p class="text-xs text-slate-500">
                                <span class="font-semibold tabular-nums text-slate-700">{{ $editionTeam->team_players_count }}</span> in the squad
                                &middot; <span class="font-semibold tabular-nums text-slate-700">{{ $matchCount }}</span> {{ \Illuminate\Support\Str::plural('match', $matchCount) }}
                            </p>
                        </div>
                        <div class="relative z-10">
                            @if($matchCount > 0)
                                <span class="ops-pill ops-pill-slate" title="A team with matches cannot be removed">Has matches</span>
                            @else
                                <form
                                    method="POST"
                                    action="{{ route('admin.editions.teams.destroy', [$edition, $editionTeam]) }}"
                                    data-confirm-delete
                                    data-confirm-title="Remove {{ $editionTeam->team->name }} from this season?"
                                    data-confirm-text="{{ $editionTeam->team_players_count > 0 ? 'Its squad of '.$editionTeam->team_players_count.' player(s) is removed too; the players\' registrations stay.' : 'The team itself is kept and can be added again.' }}"
                                    class="inline"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" title="Remove from season" aria-label="Remove {{ $editionTeam->team->name }} from this season" class="btn btn-ghost btn-icon hover:!bg-red-50 hover:!text-red-600">
                                        <x-icon name="trash" class="h-4 w-4" />
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                @empty
                    <x-admin.empty icon="shield" class="py-12">No teams in this season yet &mdash; add some on the right.</x-admin.empty>
                @endforelse
            </div>
        </section>

        {{-- Add teams --}}
        <div class="space-y-4">
            @unless($canAdd)
                <p class="rounded-xl border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                    This season is completed, so new teams cannot be added.
                </p>
            @else
                <section class="ops-card">
                    <div class="ops-card-head"><h3 class="ops-title">Add existing teams</h3></div>
                    <div class="ops-card-body">
                    @if($availableTeams->isEmpty())
                        <p class="text-xs text-slate-500">Every active team is already in this season. Create a new one below.</p>
                    @else
                        <form method="POST" action="{{ route('admin.editions.teams.store', $edition) }}" id="add-teams-form">
                            @csrf
                            <div class="relative">
                                <x-ops.icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                                <input type="search" id="team-filter" placeholder="Search teams&hellip;" aria-label="Search teams" class="ops-input pl-9" />
                            </div>
                            <label class="mb-1 mt-2 flex min-h-9 items-center gap-2 border-b border-line pb-1.5 text-xs text-slate-500">
                                <input type="checkbox" id="team-select-all" class="h-4 w-4 rounded border-slate-300" />
                                Select all shown
                            </label>
                            <div class="max-h-64 overflow-y-auto" id="team-options">
                                @foreach($availableTeams as $team)
                                    <label class="flex min-h-11 cursor-pointer items-center gap-2 rounded-lg px-1 py-1 text-[13px] text-slate-700 hover:bg-hover" data-team-option="{{ mb_strtolower($team->name.' '.$team->short_name) }}">
                                        <input type="checkbox" name="team_ids[]" value="{{ $team->id }}" @checked(in_array($team->id, array_map('intval', (array) old('team_ids', [])), true)) class="h-4 w-4 rounded border-slate-300" />
                                        <span class="truncate">{{ $team->name }}</span>
                                        @if($team->short_name)<span class="text-[11px] text-slate-400">{{ $team->short_name }}</span>@endif
                                    </label>
                                @endforeach
                            </div>
                            @error('team_ids')
                                <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                            @enderror
                            <x-admin.button type="submit" class="mt-3 w-full">Add selected teams</x-admin.button>
                        </form>
                    @endif
                    </div>
                </section>

                <section class="ops-card">
                    <div class="ops-card-head"><h3 class="ops-title">Create a new team</h3></div>
                    <div class="ops-card-body">
                    <form method="POST" action="{{ route('admin.editions.teams.store-new', $edition) }}" enctype="multipart/form-data" novalidate>
                        @csrf
                        <div class="mb-3">
                            <label for="new-team-name" class="ops-label">Team name <span class="text-red-500" aria-hidden="true">*</span></label>
                            <input id="new-team-name" name="name" value="{{ old('name') }}" required class="ops-input {{ $errors->newTeam->has('name') ? 'border-red-400' : '' }}" />
                            @if($errors->newTeam->has('name'))<p class="mt-1 text-xs text-red-600">{{ $errors->newTeam->first('name') }}</p>@endif
                        </div>
                        <div class="mb-3">
                            <label for="new-team-short" class="ops-label">Short name</label>
                            <input id="new-team-short" name="short_name" value="{{ old('short_name') }}" maxlength="20" class="ops-input" />
                            @if($errors->newTeam->has('short_name'))<p class="mt-1 text-xs text-red-600">{{ $errors->newTeam->first('short_name') }}</p>@endif
                        </div>
                        <x-form.image-upload name="logo" label="Logo (optional)" kind="image" shape="circle" empty-text="Click the picture to add a logo" help="JPG, PNG or WebP." />
                        @if($errors->newTeam->has('logo'))<p class="-mt-2 mb-3 text-xs text-red-600">{{ $errors->newTeam->first('logo') }}</p>@endif
                        <x-admin.button type="submit" variant="secondary" class="w-full">Create and add to season</x-admin.button>
                    </form>
                    </div>
                </section>
            @endunless
        </div>
    </div>

    <script>
        (function () {
            var filter = document.getElementById('team-filter');
            var all = document.getElementById('team-select-all');
            var options = Array.prototype.slice.call(document.querySelectorAll('[data-team-option]'));

            if (!filter || !all) return;

            function visible() { return options.filter(function (o) { return !o.hidden; }); }

            filter.addEventListener('input', function () {
                var q = filter.value.trim().toLowerCase();
                options.forEach(function (o) { o.hidden = q !== '' && o.dataset.teamOption.indexOf(q) === -1; });
                all.checked = false;
            });

            all.addEventListener('change', function () {
                visible().forEach(function (o) { o.querySelector('input').checked = all.checked; });
            });
        })();
    </script>
@endsection
