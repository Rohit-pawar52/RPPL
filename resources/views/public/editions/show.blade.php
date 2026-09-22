@extends('layouts.public')

@section('title', $edition->name.' · '.$branding->shortName)

@section('content')
    <div class="mb-4">
        <a href="{{ route('public.editions.index') }}" class="text-xs text-neutral-500 hover:text-neutral-700">
            &larr; All editions
        </a>
    </div>

    <div class="rounded-lg border border-neutral-200 bg-white p-4">
        <div class="flex items-center justify-between gap-3">
            <h1 class="text-base font-semibold text-neutral-900">{{ $edition->name }}</h1>
            <x-status-badge :status="$edition->status" />
        </div>
        <p class="mt-1 text-xs text-neutral-500">{{ $edition->year }}</p>
    </div>

    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-2">
        <x-stat-card label="Teams" :value="$edition->edition_teams_count" icon="shield" />
        <x-stat-card label="Matches" :value="$edition->matches_count" icon="trophy" />
    </div>

    <div class="mt-4 rounded-lg border border-neutral-200 bg-white p-4">
        <h3 class="mb-3 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">Participating Teams</h3>
        <div class="flex flex-wrap gap-2">
            @forelse($teams as $editionTeam)
                <span class="inline-flex items-center gap-1.5 rounded-full border border-neutral-200 px-2.5 py-1 text-xs text-neutral-700">
                    <span class="flex h-5 w-5 items-center justify-center overflow-hidden rounded-full border border-neutral-200 bg-neutral-50 text-neutral-300">
                        @if($editionTeam->team->logo_path)
                            <img src="{{ Illuminate\Support\Facades\Storage::url($editionTeam->team->logo_path) }}" alt="{{ $editionTeam->team->name }}" class="h-full w-full object-cover" />
                        @else
                            <x-icon name="shield" class="h-3 w-3" />
                        @endif
                    </span>
                    {{ $editionTeam->team->name }}
                </span>
            @empty
                <p class="text-xs text-neutral-400">No teams participating yet.</p>
            @endforelse
        </div>
    </div>

    <div class="mt-4">
        @include('shared.standings._table', ['standings' => $standings])
    </div>

    <div class="mt-4">
        @include('shared.statistics._leaderboard', ['leaderboard' => $leaderboard])
    </div>

    <div class="mt-4">
        @include('shared.statistics._records', ['records' => $records])
    </div>

    {{-- Recognition only — no contribution amount is ever rendered here
         (Phase 3.40). Ranking itself is still amount-driven, computed
         entirely by ContributorRankingService; this view only reads
         position/name/photo_path. --}}
    @if(! empty($contributorRanking))
        <div class="mt-4 rounded-lg border border-neutral-200 bg-white p-4">
            <h3 class="mb-3 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">Top {{ $branding->shortName }} Contributors</h3>
            <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4">
                @foreach($contributorRanking as $row)
                    @php
                        $badge = match (true) {
                            $row['position'] === 1 => ['label' => 'Top Contributor', 'class' => 'bg-amber-100 text-amber-700', 'icon' => 'trophy'],
                            $row['position'] === 2 => ['label' => '2nd Contributor', 'class' => 'bg-neutral-200 text-neutral-700', 'icon' => 'star'],
                            $row['position'] === 3 => ['label' => '3rd Contributor', 'class' => 'bg-orange-100 text-orange-700', 'icon' => 'star'],
                            $row['is_top_ten'] => ['label' => 'Top 10', 'class' => 'bg-blue-50 text-blue-600', 'icon' => null],
                            default => null,
                        };
                        $initials = collect(preg_split('/\s+/', trim($row['name'])))
                            ->filter()
                            ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
                            ->take(2)
                            ->implode('');
                    @endphp
                    <div class="flex flex-col items-center gap-1.5 rounded-md border border-neutral-100 p-3 text-center">
                        <div class="relative">
                            <div class="flex h-14 w-14 items-center justify-center overflow-hidden rounded-full border border-neutral-200 bg-neutral-50 text-sm font-semibold text-neutral-400">
                                @if($row['photo_path'])
                                    <img src="{{ Illuminate\Support\Facades\Storage::url($row['photo_path']) }}" alt="{{ $row['name'] }}" class="h-full w-full object-cover" />
                                @else
                                    {{ $initials ?: '?' }}
                                @endif
                            </div>
                            <span class="absolute -bottom-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full border border-white bg-neutral-800 text-[10px] font-semibold text-white">
                                {{ $row['position'] }}
                            </span>
                        </div>
                        <p class="max-w-full truncate text-xs font-medium text-neutral-800">{{ $row['name'] }}</p>
                        @if($badge)
                            <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $badge['class'] }}">
                                @if($badge['icon'])
                                    <x-icon name="{{ $badge['icon'] }}" class="h-3 w-3" />
                                @endif
                                {{ $badge['label'] }}
                            </span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="mt-4 rounded-lg border border-neutral-200 bg-white p-4">
        <h3 class="mb-3 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">Matches</h3>
        @forelse($matches as $match)
            @include('public.matches._list-row', ['match' => $match])
        @empty
            <p class="py-4 text-center text-xs text-neutral-400">No matches scheduled yet.</p>
        @endforelse
    </div>
@endsection
