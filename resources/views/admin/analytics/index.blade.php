@extends('layouts.admin')

@section('title', 'Analytics')
@section('subtitle', 'Public page views for matches, editions and players.')

@section('content')
    @php
        $typeLabels = ['all' => 'All content', 'match' => 'Matches', 'edition' => 'Editions', 'player' => 'Players'];
        $maxDaily = max(1, collect($daily)->max('views'));
        $maxHourly = max(1, collect($hourly)->max('views'));
        $filtered = $filters['type'] !== 'all';
    @endphp

    {{-- Filters --}}
    <x-admin.card class="mb-4">
        @if($errors->any())
            <div class="mb-3 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-xs text-red-700" role="alert">
                <p class="font-medium">Those filters could not be applied, so the last 7 days are shown instead:</p>
                <ul class="mt-1 list-disc pl-4">
                    @foreach($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="GET" action="{{ route('admin.analytics.index') }}" id="analytics-filter">
            <div class="grid grid-cols-1 gap-x-3 sm:grid-cols-2 lg:grid-cols-5">
                <x-form.select
                    name="range"
                    label="Date range"
                    :options="['today' => 'Today', 'yesterday' => 'Yesterday', 'last7' => 'Last 7 days', 'last30' => 'Last 30 days', 'custom' => 'Custom range']"
                    :value="$form['range']"
                />

                <div data-custom-field @class(['hidden' => $form['range'] !== 'custom'])>
                    <x-form.input name="from_date" label="From" type="date" :value="$form['from_date']" :disabled="$form['range'] !== 'custom'" />
                </div>
                <div data-custom-field @class(['hidden' => $form['range'] !== 'custom'])>
                    <x-form.input name="to_date" label="To" type="date" :value="$form['to_date']" :disabled="$form['range'] !== 'custom'" />
                </div>

                <x-form.select name="type" label="Content type" :options="$typeLabels" :value="$form['type']" />

                <div class="mb-3.5 flex items-end">
                    <x-admin.button type="submit" class="h-10 w-full">Apply</x-admin.button>
                </div>
            </div>
        </form>

        <p class="text-[11px] text-slate-400">
            Showing {{ $dates['from'] }} to {{ $dates['to'] }}. Dates and hours are in the display timezone ({{ $timezone }}).
            The content type narrows the totals, daily and hourly tables and the top lists; the three type cards always show the full breakdown.
        </p>
    </x-admin.card>

    {{-- Summary --}}
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-5">
        <x-stat-card label="Total views" :value="number_format($summary['total'])" icon="eye" :subtext="$filtered ? $typeLabels[$filters['type']].' only' : null" />
        <x-stat-card label="Unique visitors" :value="number_format($summary['visitors'])" icon="users" :subtext="$filtered ? $typeLabels[$filters['type']].' only' : null" />
        <x-stat-card label="Match views" :value="number_format($breakdown['match_view']['views'])" icon="trophy" :subtext="number_format($breakdown['match_view']['visitors']).' visitors'" />
        <x-stat-card label="Edition views" :value="number_format($breakdown['edition_view']['views'])" icon="calendar" :subtext="number_format($breakdown['edition_view']['visitors']).' visitors'" />
        <x-stat-card label="Player views" :value="number_format($breakdown['player_view']['views'])" icon="user" :subtext="number_format($breakdown['player_view']['visitors']).' visitors'" />
    </div>

    @if($summary['total'] === 0)
        <x-admin.card class="mt-4">
            <x-admin.empty icon="chart-bar">No page views recorded for this period.</x-admin.empty>
        </x-admin.card>
    @else
        <div class="mt-4 grid gap-4 lg:grid-cols-2 lg:items-start">
            {{-- Daily --}}
            <x-admin.card title="Daily views" flush>
                <div class="max-h-96 overflow-y-auto">
                    <table class="w-full text-left text-[13px]">
                        <thead class="sticky top-0 border-b border-slate-200 bg-slate-50 text-[11px] uppercase tracking-wide text-slate-400">
                            <tr>
                                <th class="px-4 py-2 font-medium">Date</th>
                                <th class="px-4 py-2 text-right font-medium">Views</th>
                                <th class="px-4 py-2 text-right font-medium">Unique visitors</th>
                                <th class="hidden w-1/4 px-4 py-2 sm:table-cell"><span class="sr-only">Share of busiest day</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($daily as $day)
                                <tr>
                                    <td class="whitespace-nowrap px-4 py-1.5 text-slate-700">{{ \Illuminate\Support\Carbon::parse($day['date'])->format('D, d M Y') }}</td>
                                    <td class="px-4 py-1.5 text-right font-medium text-slate-800">{{ number_format($day['views']) }}</td>
                                    <td class="px-4 py-1.5 text-right text-slate-600">{{ number_format($day['visitors']) }}</td>
                                    <td class="hidden px-4 py-1.5 sm:table-cell">
                                        <div class="h-2 rounded bg-green-500" style="width: {{ (int) round($day['views'] / $maxDaily * 100) }}%"></div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-admin.card>

            {{-- Hourly --}}
            <x-admin.card title="Views by hour of day" flush>
                <div>
                    <table class="w-full text-left text-[13px]">
                        <thead class="border-b border-slate-200 bg-slate-50 text-[11px] uppercase tracking-wide text-slate-400">
                            <tr>
                                <th class="px-4 py-2 font-medium">Hour</th>
                                <th class="px-4 py-2 text-right font-medium">Views</th>
                                <th class="hidden w-1/2 px-4 py-2 sm:table-cell"><span class="sr-only">Share of busiest hour</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($hourly as $hour)
                                <tr>
                                    <td class="whitespace-nowrap px-4 py-1.5 text-slate-700">{{ sprintf('%02d:00', $hour['hour']) }}</td>
                                    <td class="px-4 py-1.5 text-right font-medium text-slate-800">{{ number_format($hour['views']) }}</td>
                                    <td class="hidden px-4 py-1.5 sm:table-cell">
                                        <div class="h-2 rounded bg-green-500" style="width: {{ (int) round($hour['views'] / $maxHourly * 100) }}%"></div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-admin.card>
        </div>

        {{-- Most viewed --}}
        <div class="mt-4 space-y-4">
            @if($topMatches)
                @include('admin.analytics._top', ['title' => 'Top matches', 'rows' => $topMatches, 'noun' => 'match'])
            @endif
            @if($topEditions)
                @include('admin.analytics._top', ['title' => 'Top editions', 'rows' => $topEditions, 'noun' => 'edition'])
            @endif
            @if($topPlayers)
                @include('admin.analytics._top', ['title' => 'Top players', 'rows' => $topPlayers, 'noun' => 'player'])
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
