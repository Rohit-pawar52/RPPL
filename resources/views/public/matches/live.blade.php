@extends('layouts.public')

@section('title', __('matches.nav.live').' · '.$match->teamA->team->name.' '.__('matches.common.vs').' '.$match->teamB->team->name)

@section('content')
    @php
        // 'chase' may be absent on an older payload shape — treat as null.
        $chase = $liveData['chase'] ?? null;
        $chasingTeam = collect($liveData['innings'])->firstWhere('innings_number', 2)['batting_team'] ?? null;
    @endphp

    @include('public.matches._header', ['match' => $match, 'active' => 'live', 'liveStatus' => true])

    <div
        id="live-match-root"
        class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_320px] lg:items-start"
        data-match-id="{{ $match->id }}"
        data-live-data-url="{{ route('public.matches.live-data', $match) }}"
        data-should-poll="{{ $liveData['should_poll'] ? '1' : '0' }}"
    >
        <div class="min-w-0 space-y-4">
            <p
                id="live-match-result"
                class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-semibold text-green-800 {{ $liveData['match_result'] ? '' : 'hidden' }}"
            >{{ $liveData['match_result'] }}</p>

            <div id="live-innings" class="pub-card grid divide-y divide-line overflow-hidden sm:auto-cols-fr sm:grid-flow-col sm:divide-x sm:divide-y-0" aria-live="polite">
                @foreach($liveData['innings'] as $innings)
                    @include('public.matches._live-innings-row', ['innings' => $innings])
                @endforeach
            </div>

            <div id="live-chase" class="rounded-xl border border-green-200 bg-green-50 p-4 {{ $chase ? '' : 'hidden' }}" aria-live="polite">
                @if($chase)
                    @include('public.matches._live-chase', ['chase' => $chase, 'chasingTeam' => $chasingTeam])
                @endif
            </div>

            <section class="pub-card overflow-hidden">
                <header class="pub-card-head">
                    <h2 class="pub-card-title">{{ __('matches.live.commentary') }}</h2>
                    <span class="inline-flex items-center gap-1.5 pub-meta">
                        {{ __('matches.live.this_over') }}
                        @if($liveData['should_poll'])
                            &middot; <span class="live-dot text-green-600" aria-hidden="true"></span> {{ __('matches.live.auto_updates') }}
                        @endif
                    </span>
                </header>
                <div id="live-deliveries">
                    @forelse($liveData['recent_deliveries'] as $delivery)
                        @include('public.matches._live-delivery-row', ['delivery' => $delivery])
                    @empty
                        <p class="pub-empty">No deliveries recorded yet.</p>
                    @endforelse
                </div>
            </section>
        </div>

        <aside class="space-y-4">
            @include('public.matches._details', ['match' => $match])

            {{-- Sponsors sit beside the commentary on wide screens (below it
                 on phones), outside every element the polling script
                 rewrites (#live-innings, #live-chase, #live-deliveries). --}}
            <x-ad-slot tier="main" />
            <x-ad-slot tier="normal" />
        </aside>
    </div>

    <x-ad-slot tier="mini" />

    @vite(['resources/js/public-live-match.js'])
@endsection
