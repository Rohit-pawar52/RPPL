@extends('layouts.admin')

@section('title', 'Squads')

@section('content')
    @include('admin.editions._crumbs', ['edition' => $edition, 'section' => 'Squads'])

    <section class="ops-card">
        <div class="ops-card-head">
            <h3 class="ops-title">Squads in {{ $edition->name }}</h3>
        </div>

        <div class="divide-y divide-line">
            @forelse($editionTeams as $editionTeam)
                <div class="relative flex flex-wrap items-center gap-x-4 gap-y-2 p-3 transition hover:bg-hover/50 sm:p-4">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-full border border-line bg-slate-50">
                        <x-media-image :path="$editionTeam->team->logo_path" kind="image" alt="" loading="lazy" class="h-full w-full object-cover" />
                    </span>
                    <div class="min-w-0 flex-1 basis-40">
                        <a href="{{ route('admin.editions.squads.show', [$edition, $editionTeam]) }}" class="block truncate text-[15px] font-semibold text-slate-900 after:absolute after:inset-0 after:content-[''] hover:underline">{{ $editionTeam->team->name }}</a>
                        <p class="text-xs text-slate-500">
                            <span class="font-semibold tabular-nums text-slate-700">{{ $editionTeam->team_players_count }}</span> {{ \Illuminate\Support\Str::plural('player', $editionTeam->team_players_count) }}
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="ops-kicker">Bought for (points)</p>
                        <p class="text-[15px] font-bold tabular-nums text-slate-900">{{ $editionTeam->sold_total !== null ? points($editionTeam->sold_total, true) : '—' }}</p>
                    </div>
                    <span class="btn btn-secondary btn-sm relative max-sm:w-full">Open squad <x-ops.icon name="arrow-right" class="h-3.5 w-3.5" /></span>
                </div>
            @empty
                <x-admin.empty icon="users" class="py-12">
                    No teams in this season yet.
                    @can('viewAny', \App\Models\EditionTeam::class)
                        <a href="{{ route('admin.editions.teams.index', $edition) }}" class="font-medium text-link hover:underline">Add teams first</a>.
                    @endcan
                </x-admin.empty>
            @endforelse
        </div>
    </section>

    <p class="mt-3 text-xs text-slate-500">
        <span class="font-medium text-slate-700">{{ $withoutTeam }}</span> {{ \Illuminate\Support\Str::plural('player', $withoutTeam) }} of this season not in any team yet.
    </p>
@endsection
