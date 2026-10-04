@extends('layouts.public')

@section('title', $player->name.' · '.$branding->shortName)

@php
    $batting = [
        ['matches', $stats['matches_played']],
        ['innings', $stats['batting']['innings_batted']],
        ['runs', $stats['batting']['runs']],
        ['highest', $stats['batting']['highest_score'] !== null
            ? $stats['batting']['highest_score'].($stats['batting']['highest_score_not_out'] ? '*' : '')
            : '-'],
        ['average', $stats['batting']['batting_average'] !== null ? number_format($stats['batting']['batting_average'], 2) : '-'],
        ['strike_rate', number_format($stats['batting']['strike_rate'], 2)],
        ['fours', $stats['batting']['fours']],
        ['sixes', $stats['batting']['sixes']],
    ];
    $bowling = [
        ['overs', $stats['bowling']['overs']],
        ['runs', $stats['bowling']['runs_conceded']],
        ['wickets', $stats['bowling']['wickets']],
        ['best', $stats['bowling']['best_bowling'] ?? '-'],
        ['average', $stats['bowling']['bowling_average'] !== null ? number_format($stats['bowling']['bowling_average'], 2) : '-'],
        ['economy', number_format($stats['bowling']['economy'], 2)],
    ];
@endphp

@section('content')
    <a href="{{ route('public.players.index') }}" class="mb-2 inline-flex items-center gap-1 text-xs font-medium text-slate-500 hover:text-slate-900">
        <span aria-hidden="true">&larr;</span> {{ __('directory.players.back') }}
    </a>

    <div class="pub-card p-4 sm:p-5">
        <div class="flex items-center gap-4">
            <div class="pub-media flex h-16 w-16 shrink-0 items-center justify-center rounded-full border border-line text-xl font-bold text-slate-500 sm:h-20 sm:w-20 sm:text-2xl">
                <x-media-image :path="$player->photo_path" kind="user" alt="" class="absolute inset-0 h-full w-full bg-white object-cover" />
            </div>

            <div class="min-w-0">
                <h1 class="pub-h1 break-words">{{ $player->name }}</h1>
                <p class="pub-meta mt-0.5">
                    {{ $currentTeam?->name ?? __('directory.players.no_current_team') }}
                    @if($player->primary_role)
                        &middot; {{ \Illuminate\Support\Facades\Lang::has('directory.roles.'.$player->primary_role) ? __('directory.roles.'.$player->primary_role) : str_replace('_', ' ', ucfirst($player->primary_role)) }}
                    @endif
                </p>
            </div>
        </div>

        <div class="mt-4 flex gap-2 overflow-x-auto border-t border-line pt-3">
            <a
                href="{{ route('public.players.show', $player) }}"
                class="inline-flex min-h-9 shrink-0 items-center rounded-full border px-3 text-xs font-semibold {{ ! $selectedEdition ? 'border-green-600 bg-green-50 text-green-700' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50' }}"
            >
                {{ __('directory.players.career') }}
            </a>
            @foreach($player->playerRegistrations as $registration)
                <a
                    href="{{ route('public.players.show', ['player' => $player, 'edition_id' => $registration->edition_id]) }}"
                    class="inline-flex min-h-9 shrink-0 items-center rounded-full border px-3 text-xs font-semibold {{ $selectedEdition?->id === $registration->edition_id ? 'border-green-600 bg-green-50 text-green-700' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50' }}"
                >
                    {{ $registration->edition->name }}
                </a>
            @endforeach
        </div>
    </div>

    <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
        <x-public.card :title="__('directory.players.batting')">
            <dl class="grid grid-cols-4 gap-2">
                @foreach($batting as [$label, $value])
                    <div class="rounded-lg bg-slate-50 px-2 py-2.5 text-center">
                        <dt class="pub-eyebrow truncate">{{ __('directory.players.'.$label) }}</dt>
                        <dd class="mt-0.5 text-base font-bold tabular-nums text-slate-900">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </x-public.card>

        <x-public.card :title="__('directory.players.bowling')">
            <dl class="grid grid-cols-3 gap-2 sm:grid-cols-6 lg:grid-cols-3 xl:grid-cols-6">
                @foreach($bowling as [$label, $value])
                    <div class="rounded-lg bg-slate-50 px-2 py-2.5 text-center">
                        <dt class="pub-eyebrow truncate">{{ __('directory.players.'.$label) }}</dt>
                        <dd class="mt-0.5 text-base font-bold tabular-nums text-slate-900">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </x-public.card>
    </div>

    <x-public.card class="mt-4" flush :title="__('directory.players.match_history')">
        <div class="pub-table-wrap">
            <table class="pub-table min-w-[560px]">
                <thead>
                    <tr>
                        <th>{{ __('directory.players.date') }}</th>
                        <th>{{ __('directory.players.match') }}</th>
                        <th>{{ __('directory.players.batting') }}</th>
                        <th>{{ __('directory.players.bowling') }}</th>
                        <th class="hidden md:table-cell">{{ __('directory.players.result') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($matchHistory as $row)
                        @php $match = $row['match']; @endphp
                        <tr>
                            <td class="whitespace-nowrap text-slate-500">{{ display_datetime($match->scheduled_at, 'd M Y') }}</td>
                            <td class="text-slate-900">
                                <a href="{{ route('public.matches.show', $match) }}" class="font-medium hover:text-green-700">
                                    {{ $match->teamA->team->name }} {{ __('directory.common.vs') }} {{ $match->teamB->team->name }}
                                </a>
                            </td>
                            <td class="whitespace-nowrap tabular-nums">
                                @if($row['batting'])
                                    {{ $row['batting']['runs'] }} ({{ $row['batting']['balls'] }}){{ $row['batting']['not_out'] ? '*' : '' }}
                                @else
                                    &mdash;
                                @endif
                            </td>
                            <td class="whitespace-nowrap tabular-nums">
                                @if($row['bowling'])
                                    {{ $row['bowling']['wickets'] }}/{{ $row['bowling']['runs_conceded'] }} ({{ $row['bowling']['overs'] }})
                                @else
                                    &mdash;
                                @endif
                            </td>
                            <td class="hidden text-slate-500 md:table-cell">
                                @if($match->match_status === 'completed')
                                    {{ $match->match_result }}
                                @else
                                    <x-public.status-pill :status="$match->match_status" />
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-6 text-center text-slate-400">{{ __('directory.players.match_history_empty') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-public.card>

    <div class="mt-4">
        {{ $matchHistory->links() }}
    </div>
@endsection
