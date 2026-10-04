@extends('layouts.public')

@section('title', $match->teamA->team->name.' '.__('matches.common.vs').' '.$match->teamB->team->name.' · '.$branding->shortName)

@section('content')
    @include('public.matches._header', ['match' => $match, 'active' => 'info'])

    @php
        $hasMain = ($match->match_status === 'completed' && $match->match_result) || $match->firstInnings || $match->secondInnings;
    @endphp

    {{-- Nothing to show beside the details (e.g. a not-yet-started match):
         the details card takes the main column instead of leaving it empty. --}}
    @unless($hasMain)
        <div class="max-w-xl">
            @include('public.matches._details', ['match' => $match])
        </div>
    @endunless

    <div @class(['grid gap-4 lg:grid-cols-[minmax(0,1fr)_320px] lg:items-start', 'hidden' => ! $hasMain])>
        <div class="min-w-0 space-y-4">
            @if($match->match_status === 'completed' && $match->match_result)
                <p class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-800">{{ $match->match_result }}</p>
            @endif

            @if($match->firstInnings || $match->secondInnings)
                <x-public.card :title="__('matches.info.innings')" flush>
                    <div class="divide-y divide-line">
                        @foreach([$match->firstInnings, $match->secondInnings] as $inn)
                            @if($inn)
                                <div class="flex items-center justify-between gap-3 px-4 py-3.5">
                                    <span class="min-w-0 truncate text-sm font-semibold text-slate-800">{{ $inn->battingTeam->team->name }}</span>
                                    <span class="flex shrink-0 items-baseline gap-2 whitespace-nowrap">
                                        <span class="text-xl font-bold tabular-nums text-slate-900">{{ $inn->total_runs }}/{{ $inn->total_wickets }}</span>
                                        <span class="text-xs text-slate-500">({{ __('matches.common.overs_count', ['overs' => $inn->oversDisplay()]) }})</span>
                                    </span>
                                </div>
                            @endif
                        @endforeach
                    </div>
                </x-public.card>
            @endif
        </div>

        @if($hasMain)
            <aside class="space-y-4">
                @include('public.matches._details', ['match' => $match])
            </aside>
        @endif
    </div>

    <x-ad-slot tier="main" class="mt-4" />
    <x-ad-slot tier="normal" class="mt-4" />
    <x-ad-slot tier="mini" />
@endsection
