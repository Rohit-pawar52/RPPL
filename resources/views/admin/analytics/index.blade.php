@extends('layouts.admin')

@section('title', __('Analytics'))
@section('subtitle', __('Public page views for matches, editions and players.'))

@section('content')
    @php
        $typeLabels = ['all' => __('All content'), 'match' => __('Matches'), 'edition' => __('Editions'), 'player' => __('Players')];
        $presets = ['today' => __('Today'), 'yesterday' => __('Yesterday'), 'last7' => __('Last 7 days'), 'last30' => __('Last 30 days')];
        $maxDaily = max(1, collect($daily)->max('views'));
        $maxHourly = max(1, collect($hourly)->max('views'));
        $peakHour = collect($hourly)->sortByDesc('views')->first();
        $filtered = $filters['type'] !== 'all';
    @endphp

    {{-- Filters: one tap on a preset, or set a custom range below --}}
    <x-admin.card class="mb-6">
        @if($errors->any())
            <div class="mb-4 flex gap-2.5 rounded-lg border border-red-200 bg-red-50 px-3.5 py-3 text-xs text-red-700" role="alert">
                <x-admin.icon name="alert" class="mt-0.5 h-4 w-4 shrink-0" />
                <div>
                    <p class="font-semibold">{{ __('Those filters could not be applied, so the last 7 days are shown instead:') }}</p>
                    <ul class="mt-1 list-disc pl-4">
                        @foreach($errors->all() as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        <div class="mb-4 flex flex-wrap gap-2" role="group" aria-label="{{ __('Quick date ranges') }}">
            @foreach($presets as $key => $label)
                <a
                    href="{{ route('admin.analytics.index', ['range' => $key, 'type' => $filters['type']]) }}"
                    @class([
                        'btn btn-sm',
                        'btn-primary' => $filters['range'] === $key && $form['range'] === $key,
                        'btn-secondary' => ! ($filters['range'] === $key && $form['range'] === $key),
                    ])
                    @if($filters['range'] === $key && $form['range'] === $key) aria-current="true" @endif
                >{{ $label }}</a>
            @endforeach
        </div>

        <form method="GET" action="{{ route('admin.analytics.index') }}" id="analytics-filter">
            <div class="grid grid-cols-1 gap-x-3 sm:grid-cols-2 lg:grid-cols-5">
                <x-form.select
                    name="range"
                    :label="__('Date range')"
                    :options="$presets + ['custom' => __('Custom range')]"
                    :value="$form['range']"
                />

                <div data-custom-field @class(['hidden' => $form['range'] !== 'custom'])>
                    <x-form.input name="from_date" :label="__('From')" type="date" :value="$form['from_date']" :disabled="$form['range'] !== 'custom'" />
                </div>
                <div data-custom-field @class(['hidden' => $form['range'] !== 'custom'])>
                    <x-form.input name="to_date" :label="__('To')" type="date" :value="$form['to_date']" :disabled="$form['range'] !== 'custom'" />
                </div>

                <x-form.select name="type" :label="__('Content type')" :options="$typeLabels" :value="$form['type']" />

                <div class="fld flex items-end">
                    <x-admin.button type="submit" class="h-10 w-full">{{ __('Apply') }}</x-admin.button>
                </div>
            </div>
        </form>

        <p class="text-[11px] leading-4 text-slate-500">
            {{ __('Showing :from to :to.', ['from' => $dates['from'], 'to' => $dates['to']]) }} {{ __('Dates and hours are in the display timezone (:timezone).', ['timezone' => $timezone]) }}
            {{ __('The content type narrows the totals, daily and hourly tables and the top lists; the three type cards always show the full breakdown.') }}
        </p>
    </x-admin.card>

    {{-- Summary --}}
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
        <x-stat-card :label="__('Total views')" :value="number_format($summary['total'])" icon="eye" :subtext="$filtered ? __(':type only', ['type' => $typeLabels[$filters['type']]]) : null" />
        <x-stat-card :label="__('Unique visitors')" :value="number_format($summary['visitors'])" icon="users" :subtext="$filtered ? __(':type only', ['type' => $typeLabels[$filters['type']]]) : null" />
        <x-stat-card :label="__('Match views')" :value="number_format($breakdown['match_view']['views'])" icon="trophy" :subtext="__(':count visitors', ['count' => number_format($breakdown['match_view']['visitors'])])" />
        <x-stat-card :label="__('Edition views')" :value="number_format($breakdown['edition_view']['views'])" icon="calendar" :subtext="__(':count visitors', ['count' => number_format($breakdown['edition_view']['visitors'])])" />
        <x-stat-card class="col-span-2 lg:col-span-1" :label="__('Player views')" :value="number_format($breakdown['player_view']['views'])" icon="user" :subtext="__(':count visitors', ['count' => number_format($breakdown['player_view']['visitors'])])" />
    </div>

    @if($summary['total'] === 0)
        <x-admin.card class="mt-6">
            <x-admin.empty icon="chart-bar">{{ __('No page views recorded for this period.') }}</x-admin.empty>
        </x-admin.card>
    @else
        <div class="mt-6 grid gap-6 lg:grid-cols-2 lg:items-start">
            {{-- Daily --}}
            <x-admin.card :title="__('Daily views')" :subtitle="__('Bars are relative to the busiest day.')" flush>
                <div class="max-h-96 overflow-y-auto">
                    <table class="adm-table">
                        <thead class="sticky top-0">
                            <tr>
                                <th>{{ __('Date') }}</th>
                                <th class="text-right">{{ __('Views') }}</th>
                                <th class="text-right">{{ __('Unique visitors') }}</th>
                                <th class="hidden w-1/4 sm:table-cell"><span class="sr-only">{{ __('Share of busiest day') }}</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($daily as $day)
                                <tr>
                                    <td class="whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($day['date'])->translatedFormat('D, d M Y') }}</td>
                                    <td class="num font-semibold text-slate-900">{{ number_format($day['views']) }}</td>
                                    <td class="num text-slate-600">{{ number_format($day['visitors']) }}</td>
                                    <td class="hidden sm:table-cell">
                                        <div class="h-2 rounded-full bg-brand" style="width: {{ max(2, (int) round($day['views'] / $maxDaily * 100)) }}%"></div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-admin.card>

            {{-- Hourly --}}
            <x-admin.card :title="__('Views by hour of day')" :subtitle="$peakHour && $peakHour['views'] > 0 ? __('Busiest hour: :hour with :count views.', ['hour' => sprintf('%02d:00', $peakHour['hour']), 'count' => number_format($peakHour['views'])]) : null">
                <div class="flex h-52 items-end gap-[3px] sm:gap-1" role="img" aria-label="{{ __('Views for each hour of the day') }}">
                    @foreach($hourly as $hour)
                        <div class="group relative flex h-full min-w-0 flex-1 items-end" title="{{ sprintf('%02d:00', $hour['hour']) }} &middot; {{ __(':count views', ['count' => number_format($hour['views'])]) }}">
                            <div
                                class="w-full rounded-t-[3px] {{ $hour['views'] > 0 ? 'bg-brand group-hover:bg-brand-hover' : 'bg-slate-200' }}"
                                style="height: {{ $hour['views'] > 0 ? max(4, (int) round($hour['views'] / $maxHourly * 100)) : 2 }}%"
                            ></div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-2 flex justify-between text-[10px] font-medium tabular-nums text-slate-400" aria-hidden="true">
                    <span>00:00</span><span>06:00</span><span>12:00</span><span>18:00</span><span>23:00</span>
                </div>
                <details class="mt-4 text-xs">
                    <summary class="cursor-pointer font-semibold text-link hover:text-link-hover">{{ __('Show the numbers') }}</summary>
                    <div class="mt-3 grid grid-cols-2 gap-x-6 gap-y-1 tabular-nums sm:grid-cols-3">
                        @foreach($hourly as $hour)
                            <p class="flex justify-between border-b border-line py-1 text-slate-600">
                                <span>{{ sprintf('%02d:00', $hour['hour']) }}</span>
                                <span class="font-semibold text-slate-900">{{ number_format($hour['views']) }}</span>
                            </p>
                        @endforeach
                    </div>
                </details>
            </x-admin.card>
        </div>

        {{-- Most viewed --}}
        <div class="mt-6 space-y-6">
            @if($topMatches)
                @include('admin.analytics._top', ['title' => __('Top matches'), 'rows' => $topMatches, 'noun' => 'match', 'heading' => __('Match'), 'empty' => __('No match views recorded for this period.')])
            @endif
            @if($topEditions)
                @include('admin.analytics._top', ['title' => __('Top editions'), 'rows' => $topEditions, 'noun' => 'edition', 'heading' => __('Edition'), 'empty' => __('No edition views recorded for this period.')])
            @endif
            @if($topPlayers)
                @include('admin.analytics._top', ['title' => __('Top players'), 'rows' => $topPlayers, 'noun' => 'player', 'heading' => __('Player'), 'empty' => __('No player views recorded for this period.')])
            @endif
        </div>
    @endif

    <script>
        // Show the From/To fields only for the custom range.
        document.getElementById('range')?.addEventListener('change', function () {
            const custom = this.value === 'custom';
            document.querySelectorAll('[data-custom-field]').forEach(function (field) {
                field.classList.toggle('hidden', ! custom);
                field.querySelector('input').disabled = ! custom;
            });
        });
    </script>
@endsection
