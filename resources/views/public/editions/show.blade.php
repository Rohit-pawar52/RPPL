@extends('layouts.public')

@section('title', $edition->name.' · '.$branding->shortName)

@section('content')
    <a href="{{ route('public.editions.index') }}" class="mb-2 inline-flex items-center gap-1 text-xs font-medium text-slate-500 hover:text-slate-900">
        <span aria-hidden="true">&larr;</span> {{ __('directory.editions.all_editions') }}
    </a>

    <div class="pub-card p-4 sm:p-5">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">
                <h1 class="pub-h1 break-words">{{ $edition->name }}</h1>
                <p class="pub-meta mt-0.5">{{ $edition->year }}</p>
            </div>
            <x-public.status-pill :status="$edition->status" />
        </div>

        <dl class="mt-4 grid grid-cols-2 gap-3 border-t border-line pt-4">
            <div>
                <dt class="pub-eyebrow">{{ __('directory.editions.teams') }}</dt>
                <dd class="mt-0.5 text-2xl font-bold tabular-nums text-slate-900">{{ $edition->edition_teams_count }}</dd>
            </div>
            <div>
                <dt class="pub-eyebrow">{{ __('directory.editions.matches') }}</dt>
                <dd class="mt-0.5 text-2xl font-bold tabular-nums text-slate-900">{{ $edition->matches_count }}</dd>
            </div>
        </dl>
    </div>

    {{-- Points table (same data/keys as shared/standings/_table, which is
         also used by admin and is left untouched). Top rows are tinted. --}}
    <x-public.card class="mt-4" flush :title="__('directory.standings.points_table')">
        <div class="pub-table-wrap">
            <table class="pub-table min-w-[360px]">
                <thead>
                    <tr>
                        <th class="w-10">#</th>
                        <th>{{ __('directory.standings.team') }}</th>
                        <th class="text-right">{{ __('directory.standings.played') }}</th>
                        <th class="hidden text-right sm:table-cell">{{ __('directory.standings.won') }}</th>
                        <th class="hidden text-right sm:table-cell">{{ __('directory.standings.lost') }}</th>
                        <th class="hidden text-right sm:table-cell">{{ __('directory.standings.tied') }}</th>
                        <th class="hidden text-right sm:table-cell">{{ __('directory.standings.no_result') }}</th>
                        <th class="text-right">{{ __('directory.standings.points') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($standings as $row)
                        <tr class="{{ $row['position'] <= 2 ? '[&>td]:bg-green-50/60' : '' }}">
                            <td>
                                <span class="inline-flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold tabular-nums {{ $row['position'] <= 2 ? 'bg-green-600 text-white' : 'text-slate-500' }}">{{ $row['position'] }}</span>
                            </td>
                            <td class="font-semibold text-slate-900">{{ $row['edition_team']->team->name }}</td>
                            <td class="text-right tabular-nums">{{ $row['played'] }}</td>
                            <td class="hidden text-right tabular-nums sm:table-cell">{{ $row['won'] }}</td>
                            <td class="hidden text-right tabular-nums sm:table-cell">{{ $row['lost'] }}</td>
                            <td class="hidden text-right tabular-nums sm:table-cell">{{ $row['tied'] }}</td>
                            <td class="hidden text-right tabular-nums sm:table-cell">{{ $row['no_result'] }}</td>
                            <td class="text-right font-bold tabular-nums text-slate-900">{{ $row['points'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8"><x-public.empty>{{ __('directory.standings.empty') }}</x-public.empty></td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-public.card>

    {{-- Leaderboard --}}
    <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
        <x-public.card flush :title="__('directory.leaderboard.top_run_scorers')">
            <div class="pub-table-wrap">
                <table class="pub-table min-w-[300px]">
                    <thead>
                        <tr>
                            <th>{{ __('directory.leaderboard.player') }}</th>
                            <th class="text-right">{{ __('directory.leaderboard.runs') }}</th>
                            <th class="hidden text-right sm:table-cell">{{ __('directory.leaderboard.innings') }}</th>
                            <th class="hidden text-right sm:table-cell">{{ __('directory.leaderboard.average') }}</th>
                            <th class="text-right">{{ __('directory.leaderboard.strike_rate') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($leaderboard['topRunScorers'] as $entry)
                            <tr>
                                <td class="font-semibold text-slate-900">{{ $entry['player']->name }}</td>
                                <td class="text-right font-bold tabular-nums text-slate-900">{{ $entry['stats']['runs'] }}</td>
                                <td class="hidden text-right tabular-nums sm:table-cell">{{ $entry['stats']['innings_batted'] }}</td>
                                <td class="hidden text-right tabular-nums sm:table-cell">
                                    {{ $entry['stats']['batting_average'] !== null ? number_format($entry['stats']['batting_average'], 2) : '-' }}
                                </td>
                                <td class="text-right tabular-nums">{{ number_format($entry['stats']['strike_rate'], 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5"><x-public.empty>{{ __('directory.leaderboard.batting_empty') }}</x-public.empty></td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-public.card>

        <x-public.card flush :title="__('directory.leaderboard.top_wicket_takers')">
            <div class="pub-table-wrap">
                <table class="pub-table min-w-[300px]">
                    <thead>
                        <tr>
                            <th>{{ __('directory.leaderboard.player') }}</th>
                            <th class="text-right">{{ __('directory.leaderboard.wickets') }}</th>
                            <th class="hidden text-right sm:table-cell">{{ __('directory.leaderboard.overs') }}</th>
                            <th class="hidden text-right sm:table-cell">{{ __('directory.leaderboard.runs') }}</th>
                            <th class="text-right">{{ __('directory.leaderboard.economy') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($leaderboard['topWicketTakers'] as $entry)
                            <tr>
                                <td class="font-semibold text-slate-900">{{ $entry['player']->name }}</td>
                                <td class="text-right font-bold tabular-nums text-slate-900">{{ $entry['stats']['wickets'] }}</td>
                                <td class="hidden text-right tabular-nums sm:table-cell">{{ $entry['stats']['overs'] }}</td>
                                <td class="hidden text-right tabular-nums sm:table-cell">{{ $entry['stats']['runs_conceded'] }}</td>
                                <td class="text-right tabular-nums">{{ number_format($entry['stats']['economy'], 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5"><x-public.empty>{{ __('directory.leaderboard.bowling_empty') }}</x-public.empty></td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-public.card>
    </div>

    {{-- Records --}}
    <x-public.card class="mt-4" :title="__('directory.records.title')">
        <dl class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div class="rounded-lg bg-slate-50 p-3">
                <dt class="pub-eyebrow">{{ __('directory.records.highest_score') }}</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900">
                    @if($records['highestScore'])
                        {{ $records['highestScore']['runs'] }}{{ $records['highestScore']['notOut'] ? '*' : '' }}
                        &mdash; {{ $records['highestScore']['player']->name }}
                    @else
                        -
                    @endif
                </dd>
            </div>
            <div class="rounded-lg bg-slate-50 p-3">
                <dt class="pub-eyebrow">{{ __('directory.records.best_bowling') }}</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900">
                    @if($records['bestBowling'])
                        {{ $records['bestBowling']['wickets'] }}/{{ $records['bestBowling']['runsConceded'] }}
                        &mdash; {{ $records['bestBowling']['player']->name }}
                    @else
                        -
                    @endif
                </dd>
            </div>
            <div class="rounded-lg bg-slate-50 p-3">
                <dt class="pub-eyebrow">{{ __('directory.records.most_sixes') }}</dt>
                <dd class="mt-1 text-sm font-semibold text-slate-900">
                    @if($records['mostSixes'])
                        {{ $records['mostSixes']['sixes'] }} &mdash; {{ $records['mostSixes']['player']->name }}
                    @else
                        -
                    @endif
                </dd>
            </div>
        </dl>
    </x-public.card>

    <x-public.card class="mt-4" :title="__('directory.editions.participating_teams')">
        <div class="flex flex-wrap gap-2">
            @forelse($teams as $editionTeam)
                <span class="inline-flex items-center gap-2 rounded-full border border-line bg-white py-1 pl-1 pr-3 text-xs font-medium text-slate-700">
                    <span class="pub-media flex h-6 w-6 items-center justify-center rounded-full text-[10px] font-bold text-slate-500">
                        {{ mb_strtoupper(mb_substr($editionTeam->team->name, 0, 1)) }}
                        @if($editionTeam->team->logo_path)
                            <img src="{{ Illuminate\Support\Facades\Storage::url($editionTeam->team->logo_path) }}" alt="{{ $editionTeam->team->name }}" class="absolute inset-0 h-full w-full bg-white object-cover" onerror="this.style.visibility='hidden'" />
                        @endif
                    </span>
                    {{ $editionTeam->team->name }}
                </span>
            @empty
                <p class="pub-meta">{{ __('directory.editions.participating_teams_empty') }}</p>
            @endforelse
        </div>
    </x-public.card>

    {{-- Recognition only — no contribution amount is ever rendered here
         (Phase 3.40). Ranking itself is still amount-driven, computed
         entirely by ContributorRankingService; this view only reads
         position/name/photo_path. --}}
    @if(! empty($contributorRanking))
        <x-public.card class="mt-4" :title="__('directory.editions.top_contributors', ['name' => $branding->shortName])">
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                @foreach($contributorRanking as $row)
                    @php
                        $badge = match (true) {
                            $row['position'] === 1 => ['label' => __('directory.editions.badge_top'), 'class' => 'bg-amber-100 text-amber-700', 'icon' => 'trophy'],
                            $row['position'] === 2 => ['label' => __('directory.editions.badge_second'), 'class' => 'bg-slate-200 text-slate-700', 'icon' => 'star'],
                            $row['position'] === 3 => ['label' => __('directory.editions.badge_third'), 'class' => 'bg-orange-100 text-orange-700', 'icon' => 'star'],
                            $row['is_top_ten'] => ['label' => __('directory.editions.badge_top_ten'), 'class' => 'bg-sky-50 text-sky-700', 'icon' => null],
                            default => null,
                        };
                        $initials = collect(preg_split('/\s+/', trim($row['name'])))
                            ->filter()
                            ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
                            ->take(2)
                            ->implode('');
                    @endphp
                    <div class="flex flex-col items-center gap-1.5 rounded-xl border border-line p-3 text-center">
                        <div class="relative">
                            <div class="pub-media flex h-14 w-14 items-center justify-center rounded-full border border-line text-sm font-semibold text-slate-500">
                                {{ $initials ?: '?' }}
                                @if($row['photo_path'])
                                    <img src="{{ Illuminate\Support\Facades\Storage::url($row['photo_path']) }}" alt="{{ $row['name'] }}" class="absolute inset-0 h-full w-full bg-white object-cover" onerror="this.style.visibility='hidden'" />
                                @endif
                            </div>
                            <span class="absolute -bottom-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full border border-white bg-slate-800 text-[10px] font-semibold text-white">
                                {{ $row['position'] }}
                            </span>
                        </div>
                        <p class="max-w-full truncate text-xs font-semibold text-slate-900">{{ $row['name'] }}</p>
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
        </x-public.card>
    @endif

    <x-public.card class="mt-4" :title="__('directory.editions.matches')">
        @forelse($matches as $match)
            @include('public.matches._list-row', ['match' => $match])
        @empty
            <x-public.empty class="!py-4">{{ __('directory.editions.matches_empty') }}</x-public.empty>
        @endforelse
    </x-public.card>
@endsection
