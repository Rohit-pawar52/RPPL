@extends('layouts.admin')

@section('title', 'Teams')

@section('content')
    @include('admin.editions._crumbs', ['edition' => $edition, 'section' => 'Teams'])

    <div class="grid items-start gap-4 lg:grid-cols-[1fr_20rem]">
        {{-- Teams already in this season --}}
        <x-admin.card :title="'Teams in '.$edition->name.' ('.$editionTeams->count().')'" flush>
            <table class="w-full text-left text-[13px]">
                <thead class="border-b border-slate-200 bg-slate-50 text-[11px] uppercase tracking-wide text-slate-400">
                    <tr>
                        <th class="px-4 py-2 font-medium">Team</th>
                        <th class="px-4 py-2 text-right font-medium">Squad</th>
                        <th class="px-4 py-2 text-right font-medium">Matches</th>
                        <th class="px-4 py-2 text-right font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse($editionTeams as $editionTeam)
                        @php $matchCount = $editionTeam->matches_as_team_a_count + $editionTeam->matches_as_team_b_count; @endphp
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-1.5">
                                <a href="{{ route('admin.editions.squads.show', [$edition, $editionTeam]) }}" class="flex items-center gap-2 font-medium text-slate-800 hover:underline">
                                    <span class="flex h-6 w-6 shrink-0 items-center justify-center overflow-hidden rounded-full border border-slate-200 bg-slate-50 text-slate-300">
                                        @if($editionTeam->team->logo_path)
                                            <img src="{{ Illuminate\Support\Facades\Storage::url($editionTeam->team->logo_path) }}" alt="" class="h-full w-full object-cover" />
                                        @else
                                            <x-icon name="shield" class="h-3.5 w-3.5" />
                                        @endif
                                    </span>
                                    {{ $editionTeam->team->name }}
                                    @if($editionTeam->team->short_name)
                                        <span class="text-[11px] font-normal text-slate-400">{{ $editionTeam->team->short_name }}</span>
                                    @endif
                                </a>
                            </td>
                            <td class="px-4 py-1.5 text-right text-slate-600">{{ $editionTeam->team_players_count }}</td>
                            <td class="px-4 py-1.5 text-right text-slate-600">{{ $matchCount }}</td>
                            <td class="px-4 py-1.5 text-right">
                                @if($matchCount > 0)
                                    <span class="text-[11px] text-slate-400" title="A team with matches cannot be removed">Has matches</span>
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
                                        <button type="submit" title="Remove from season" aria-label="Remove {{ $editionTeam->team->name }} from this season" class="rounded p-1.5 text-slate-500 hover:bg-red-50 hover:text-red-600">
                                            <x-icon name="trash" class="h-4 w-4" />
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <x-admin.empty table colspan="4">No teams in this season yet &mdash; add some on the right.</x-admin.empty>
                    @endforelse
                </tbody>
            </table>
        </x-admin.card>

        {{-- Add teams --}}
        <div class="space-y-4">
            @unless($canAdd)
                <p class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                    This season is completed, so new teams cannot be added.
                </p>
            @else
                <x-admin.card title="Add existing teams">
                    @if($availableTeams->isEmpty())
                        <p class="text-xs text-slate-500">Every active team is already in this season. Create a new one below.</p>
                    @else
                        <form method="POST" action="{{ route('admin.editions.teams.store', $edition) }}" id="add-teams-form">
                            @csrf
                            <input type="search" id="team-filter" placeholder="Search teams&hellip;" class="mb-2 h-8 w-full rounded-md border border-slate-300 px-2.5 text-[13px] focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-100" />
                            <label class="mb-1 flex items-center gap-2 border-b border-slate-100 pb-1.5 text-xs text-slate-500">
                                <input type="checkbox" id="team-select-all" class="h-4 w-4 rounded border-slate-300 text-green-600" />
                                Select all shown
                            </label>
                            <div class="max-h-64 overflow-y-auto" id="team-options">
                                @foreach($availableTeams as $team)
                                    <label class="flex cursor-pointer items-center gap-2 rounded px-1 py-1 text-[13px] text-slate-700 hover:bg-slate-50" data-team-option="{{ mb_strtolower($team->name.' '.$team->short_name) }}">
                                        <input type="checkbox" name="team_ids[]" value="{{ $team->id }}" @checked(in_array($team->id, array_map('intval', (array) old('team_ids', [])), true)) class="h-4 w-4 rounded border-slate-300 text-green-600" />
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
                </x-admin.card>

                <x-admin.card title="Create a new team">
                    <form method="POST" action="{{ route('admin.editions.teams.store-new', $edition) }}" enctype="multipart/form-data" novalidate>
                        @csrf
                        <div class="mb-2.5">
                            <label for="new-team-name" class="mb-1 block text-xs font-medium text-slate-700">Team name <span class="text-red-500" aria-hidden="true">*</span></label>
                            <input id="new-team-name" name="name" value="{{ old('name') }}" required class="h-9 w-full rounded-md border px-2.5 text-[13px] focus:outline-none focus:ring-2 {{ $errors->newTeam->has('name') ? 'border-red-400 focus:ring-red-100' : 'border-slate-300 focus:border-green-500 focus:ring-green-100' }}" />
                            @if($errors->newTeam->has('name'))<p class="mt-1 text-xs text-red-600">{{ $errors->newTeam->first('name') }}</p>@endif
                        </div>
                        <div class="mb-2.5">
                            <label for="new-team-short" class="mb-1 block text-xs font-medium text-slate-700">Short name</label>
                            <input id="new-team-short" name="short_name" value="{{ old('short_name') }}" maxlength="20" class="h-9 w-full rounded-md border border-slate-300 px-2.5 text-[13px] focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-100" />
                            @if($errors->newTeam->has('short_name'))<p class="mt-1 text-xs text-red-600">{{ $errors->newTeam->first('short_name') }}</p>@endif
                        </div>
                        <div class="mb-3">
                            <label for="new-team-logo" class="mb-1 block text-xs font-medium text-slate-700">Logo (optional)</label>
                            <input id="new-team-logo" type="file" name="logo" accept="image/png,image/jpeg,image/webp" class="block w-full text-xs text-slate-600 file:mr-3 file:cursor-pointer file:rounded-md file:border file:border-slate-300 file:bg-white file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-slate-700 hover:file:bg-slate-50" />
                            @if($errors->newTeam->has('logo'))<p class="mt-1 text-xs text-red-600">{{ $errors->newTeam->first('logo') }}</p>@endif
                        </div>
                        <x-admin.button type="submit" variant="secondary" class="w-full">Create and add to season</x-admin.button>
                    </form>
                </x-admin.card>
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
