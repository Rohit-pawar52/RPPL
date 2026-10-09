@extends('layouts.public')

@section('title', $player->name.' · '.$branding->shortName)

@php
    use Illuminate\Support\Facades\Lang;

    $b = $stats['batting'];
    $w = $stats['bowling'];
    $played = $stats['matches_played'];

    $highest = $b['highest_score'] !== null ? $b['highest_score'].($b['highest_score_not_out'] ? '*' : '') : '-';
    $average = $b['batting_average'] !== null ? number_format($b['batting_average'], 2) : '-';

    // The four numbers people look for first.
    $headline = [
        ['matches', $played, false],
        ['runs', $b['runs'], true],
        ['wickets', $w['wickets'], true],
        ['best_figures', $w['best_bowling'] ?? '-', false],
    ];

    $batting = [
        ['innings', $b['innings_batted']],
        ['highest', $highest],
        ['average', $average],
        ['strike_rate', number_format($b['strike_rate'], 2)],
        ['fours', $b['fours']],
        ['sixes', $b['sixes']],
    ];
    $bowling = [
        ['overs', $w['overs']],
        ['wickets', $w['wickets']],
        ['runs', $w['runs_conceded']],
        ['best', $w['best_bowling'] ?? '-'],
        ['average', $w['bowling_average'] !== null ? number_format($w['bowling_average'], 2) : '-'],
        ['economy', number_format($w['economy'], 2)],
    ];

    $roleLabel = fn (?string $role) => $role === null
        ? null
        : (Lang::has('directory.roles.'.$role) ? __('directory.roles.'.$role) : str_replace('_', ' ', ucfirst($role)));

    $styleLabel = function (string $group, ?string $value) {
        if ($value === null || $value === '') {
            return null;
        }

        return Lang::has('registration.'.$group.'.'.$value) ? __('registration.'.$group.'.'.$value) : str_replace('_', ' ', ucfirst($value));
    };

    $facts = array_filter([
        $roleLabel($player->primary_role),
        $styleLabel('batting_styles', $player->batting_style) ? __('ux_public_content.player.bats', ['style' => $styleLabel('batting_styles', $player->batting_style)]) : null,
        $player->bowling_style && $player->bowling_style !== 'none' && $styleLabel('bowling_styles', $player->bowling_style)
            ? __('ux_public_content.player.bowls', ['style' => $styleLabel('bowling_styles', $player->bowling_style)])
            : null,
    ]);
@endphp

@section('content')
    <a href="{{ route('public.players.index') }}" class="pc-back">
        <span aria-hidden="true">&larr;</span> {{ __('directory.players.back') }}
    </a>

    {{-- Profile --}}
    <section class="pc-hero p-5 sm:p-8">
        <div class="flex flex-col items-center gap-4 text-center sm:flex-row sm:gap-7 sm:text-left">
            <span class="pc-avatar h-28 w-28 shrink-0 border-0 ring-4 ring-white/15 sm:h-36 sm:w-36">
                <x-media-image :path="$player->photo_path" kind="user" alt="" class="object-top" />
            </span>

            <div class="min-w-0 flex-1">
                <p class="pc-eyebrow">{{ $currentTeam?->name ?? __('directory.players.no_current_team') }}</p>
                <h1 class="mt-1 break-words text-3xl font-bold leading-tight tracking-tight sm:text-4xl">{{ $player->name }}</h1>

                @if(count($facts))
                    <div class="mt-3 flex flex-wrap justify-center gap-2 sm:justify-start">
                        @foreach($facts as $fact)
                            <span class="inline-flex items-center rounded-full bg-white/10 px-3 py-1 text-xs font-semibold text-white ring-1 ring-white/15">{{ $fact }}</span>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="pc-chips mt-5 border-t border-white/10 pt-4 sm:mt-6" role="tablist" aria-label="{{ __('ux_public_content.player.season') }}">
            <a
                href="{{ route('public.players.show', $player) }}"
                class="pc-chip pc-chip-dark {{ ! $selectedEdition ? 'pc-chip-active' : '' }}"
                @if(! $selectedEdition) aria-current="true" @endif
            >
                {{ __('directory.players.career') }}
            </a>
            @foreach($player->playerRegistrations as $registration)
                <a
                    href="{{ route('public.players.show', ['player' => $player, 'edition_id' => $registration->edition_id]) }}"
                    class="pc-chip pc-chip-dark {{ $selectedEdition?->id === $registration->edition_id ? 'pc-chip-active' : '' }}"
                    @if($selectedEdition?->id === $registration->edition_id) aria-current="true" @endif
                >
                    {{ $registration->edition->name }}
                </a>
            @endforeach
        </div>
    </section>

    {{-- The four numbers that matter --}}
    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
        @foreach($headline as [$label, $value, $accent])
            <div class="pc-stat py-4 sm:py-5 {{ $accent ? 'pc-stat-accent' : '' }}">
                <p class="pc-stat-label">{{ $label === 'best_figures' ? __('ux_public_content.player.best_figures') : __('directory.players.'.$label) }}</p>
                <p class="pc-stat-value text-3xl sm:text-4xl">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    {{-- Season by season (career view, when the player has been in more than one season) --}}
    @php
        $seasonRows = collect();

        if (! $selectedEdition && $player->playerRegistrations->count() > 1) {
            $statisticsService = app(\App\Services\Statistics\PlayerStatisticsService::class);

            $seasonRows = $player->playerRegistrations->take(8)->map(function ($registration) use ($player, $statisticsService) {
                $seasonStats = $statisticsService->getPlayerStatistics($player, $registration->edition);

                return [
                    'edition' => $registration->edition,
                    'team' => $registration->teamPlayer?->editionTeam?->team?->name,
                    'matches' => $seasonStats['matches_played'],
                    'runs' => $seasonStats['batting']['runs'],
                    'wickets' => $seasonStats['bowling']['wickets'],
                ];
            });
        }
    @endphp
    @if($seasonRows->isNotEmpty())
        <section class="mt-6 lg:mt-8">
            <h2 class="pc-h2">{{ __('ux_public_content.player.by_season') }}</h2>
            <div class="pc-panel">
                <div class="pub-table-wrap">
                    <table class="pub-table">
                        <thead>
                            <tr>
                                <th>{{ __('ux_public_content.player.season_col') }}</th>
                                <th>{{ __('ux_public_content.player.team_col') }}</th>
                                <th class="text-right">{{ __('directory.players.matches') }}</th>
                                <th class="text-right">{{ __('directory.players.runs') }}</th>
                                <th class="text-right">{{ __('directory.players.wickets') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($seasonRows as $row)
                                <tr>
                                    <td class="whitespace-nowrap font-semibold">
                                        <a href="{{ route('public.players.show', ['player' => $player, 'edition_id' => $row['edition']->id]) }}" class="text-slate-900 hover:text-brand">{{ $row['edition']->name }}</a>
                                    </td>
                                    <td class="whitespace-nowrap text-slate-500">{{ $row['team'] ?? '—' }}</td>
                                    <td class="text-right font-semibold tabular-nums text-slate-900">{{ $row['matches'] }}</td>
                                    <td class="text-right font-semibold tabular-nums text-slate-900">{{ $row['runs'] }}</td>
                                    <td class="text-right font-semibold tabular-nums text-slate-900">{{ $row['wickets'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    @endif

    {{-- Batting and bowling in detail --}}
    <div class="mt-6 grid grid-cols-1 gap-4 lg:mt-8 lg:grid-cols-2">
        <section class="pc-panel">
            <header class="pc-panel-head">
                <h2 class="pc-panel-title">{{ __('directory.players.batting') }}</h2>
                <span class="text-xs text-slate-400">{{ __('ux_public_content.player.runs_total', ['count' => $b['runs']]) }}</span>
            </header>
            <dl class="grid grid-cols-3 gap-2 p-4">
                @foreach($batting as [$label, $value])
                    <div class="rounded-lg bg-slate-50 px-2 py-3 text-center">
                        <dt class="truncate text-[10px] font-semibold uppercase tracking-wide text-slate-400 sm:text-[11px]">{{ __('directory.players.'.$label) }}</dt>
                        <dd class="mt-1 text-lg font-bold tabular-nums text-slate-900">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>

        <section class="pc-panel">
            <header class="pc-panel-head">
                <h2 class="pc-panel-title">{{ __('directory.players.bowling') }}</h2>
                <span class="text-xs text-slate-400">{{ __('ux_public_content.player.wickets_total', ['count' => $w['wickets']]) }}</span>
            </header>
            <dl class="grid grid-cols-3 gap-2 p-4">
                @foreach($bowling as [$label, $value])
                    <div class="rounded-lg bg-slate-50 px-2 py-3 text-center">
                        <dt class="truncate text-[10px] font-semibold uppercase tracking-wide text-slate-400 sm:text-[11px]">{{ __('directory.players.'.$label) }}</dt>
                        <dd class="mt-1 text-lg font-bold tabular-nums text-slate-900">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>
    </div>

    {{-- Recent form: the last few innings at a glance --}}
    @php
        $form = $matchHistory->getCollection()->filter(fn ($row) => $row['batting'] || $row['bowling'])->take(6);
    @endphp
    @if($form->isNotEmpty() && $matchHistory->currentPage() === 1)
        <section class="mt-6 lg:mt-8">
            <h2 class="pc-h2">{{ __('ux_public_content.player.recent_form') }}</h2>
            <div class="flex gap-2 overflow-x-auto pb-1" style="scrollbar-width: none">
                @foreach($form as $row)
                    @php
                        $runs = $row['batting']['runs'] ?? null;
                        $wk = $row['bowling']['wickets'] ?? null;
                    @endphp
                    <a href="{{ route('public.matches.show', $row['match']) }}" class="pc-stat min-w-28 shrink-0 text-left transition hover:border-brand/40 hover:shadow-raised">
                        <p class="pc-stat-label">{{ display_datetime($row['match']->scheduled_at, 'd M') }}</p>
                        @if($row['batting'])
                            <p class="mt-1.5 text-xl font-bold tabular-nums leading-none text-slate-900">{{ $runs }}{{ $row['batting']['not_out'] ? '*' : '' }}<span class="ml-1 text-[11px] font-medium text-slate-400">({{ $row['batting']['balls'] }})</span></p>
                        @endif
                        @if($row['bowling'])
                            <p class="mt-1.5 text-sm font-semibold tabular-nums text-slate-600">{{ $wk }}/{{ $row['bowling']['runs_conceded'] }}</p>
                        @endif
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Match by match --}}
    <section class="mt-6 lg:mt-8">
        <h2 class="pc-h2">{{ __('directory.players.match_history') }}</h2>

        @if($matchHistory->isEmpty())
            <div class="pc-empty">
                <span class="pc-empty-icon"><x-icon name="chart-bar" class="h-7 w-7" /></span>
                <p class="pc-empty-title">{{ __('directory.players.match_history_empty') }}</p>
            </div>
        @else
            {{-- Phones: one card per match --}}
            <div class="pc-panel divide-y divide-line md:hidden">
                @foreach($matchHistory as $row)
                    @php $match = $row['match']; @endphp
                    <a href="{{ route('public.matches.show', $match) }}" class="block px-4 py-3 transition hover:bg-hover">
                        <div class="flex items-start justify-between gap-3">
                            <p class="min-w-0 text-[13px] font-semibold text-slate-900">{{ $match->teamA->team->name }} {{ __('directory.common.vs') }} {{ $match->teamB->team->name }}</p>
                            <p class="shrink-0 text-[11px] text-slate-400">{{ display_datetime($match->scheduled_at, 'd M Y') }}</p>
                        </div>
                        <div class="mt-2 flex items-center gap-4 text-sm tabular-nums">
                            <p class="text-slate-500"><span class="text-[11px] uppercase tracking-wide text-slate-400">{{ __('ux_public_content.player.bat_short') }}</span>
                                <span class="font-bold text-slate-900">
                                    @if($row['batting']) {{ $row['batting']['runs'] }}{{ $row['batting']['not_out'] ? '*' : '' }} ({{ $row['batting']['balls'] }}) @else &mdash; @endif
                                </span>
                            </p>
                            <p class="text-slate-500"><span class="text-[11px] uppercase tracking-wide text-slate-400">{{ __('ux_public_content.player.bowl_short') }}</span>
                                <span class="font-bold text-slate-900">
                                    @if($row['bowling']) {{ $row['bowling']['wickets'] }}/{{ $row['bowling']['runs_conceded'] }} ({{ $row['bowling']['overs'] }}) @else &mdash; @endif
                                </span>
                            </p>
                        </div>
                        <p class="mt-1.5 text-xs text-slate-500">
                            @if($match->match_status === 'completed')
                                {{ $match->match_result }}
                            @else
                                <x-public.status-pill :status="$match->match_status" />
                            @endif
                        </p>
                    </a>
                @endforeach
            </div>

            {{-- Wider screens: a table --}}
            <div class="pc-panel hidden md:block">
                <div class="pub-table-wrap">
                    <table class="pub-table">
                        <thead>
                            <tr>
                                <th>{{ __('directory.players.date') }}</th>
                                <th>{{ __('directory.players.match') }}</th>
                                <th>{{ __('directory.players.batting') }}</th>
                                <th>{{ __('directory.players.bowling') }}</th>
                                <th>{{ __('directory.players.result') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($matchHistory as $row)
                                @php $match = $row['match']; @endphp
                                <tr>
                                    <td class="whitespace-nowrap text-slate-500">{{ display_datetime($match->scheduled_at, 'd M Y') }}</td>
                                    <td class="text-slate-900">
                                        <a href="{{ route('public.matches.show', $match) }}" class="font-medium hover:text-brand">
                                            {{ $match->teamA->team->name }} {{ __('directory.common.vs') }} {{ $match->teamB->team->name }}
                                        </a>
                                    </td>
                                    <td class="whitespace-nowrap font-semibold tabular-nums text-slate-900">
                                        @if($row['batting'])
                                            {{ $row['batting']['runs'] }} ({{ $row['batting']['balls'] }}){{ $row['batting']['not_out'] ? '*' : '' }}
                                        @else
                                            <span class="font-normal text-slate-300">&mdash;</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap font-semibold tabular-nums text-slate-900">
                                        @if($row['bowling'])
                                            {{ $row['bowling']['wickets'] }}/{{ $row['bowling']['runs_conceded'] }} ({{ $row['bowling']['overs'] }})
                                        @else
                                            <span class="font-normal text-slate-300">&mdash;</span>
                                        @endif
                                    </td>
                                    <td class="text-slate-500">
                                        @if($match->match_status === 'completed')
                                            {{ $match->match_result }}
                                        @else
                                            <x-public.status-pill :status="$match->match_status" />
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </section>

    <div class="mt-4">
        {{ $matchHistory->links() }}
    </div>
@endsection
