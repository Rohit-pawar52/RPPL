@extends('layouts.admin')

@section('title', __('Dashboard'))
@section('bare', '1')

@section('content')
    @php
        $hour = (int) display_datetime(now(), 'G');
        $greeting = $hour < 12 ? __('Good morning') : ($hour < 17 ? __('Good afternoon') : __('Good evening'));
        $firstName = \Illuminate\Support\Str::before(trim(auth()->user()->name), ' ');
        $inProgress = $auction && ($auction->isLive() || $auction->isPaused());
    @endphp

    <section class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-navy-900 to-navy-800 p-5 text-white shadow-raised sm:p-7">
        <div class="pointer-events-none absolute -right-16 -top-24 h-72 w-72 rounded-full bg-brand opacity-30 blur-3xl" aria-hidden="true"></div>
        <div class="relative">
            <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-accent-dark">{{ display_datetime(now(), 'l, j F') }}</p>
            <h1 class="mt-1.5 break-words text-2xl font-bold tracking-tight sm:text-3xl">{{ $greeting }}, {{ $firstName }}</h1>
            <p class="mt-2 text-[13px] text-white/70">{{ __('You are signed in as :role.', ['role' => auth()->user()->role->name]) }}</p>
        </div>
    </section>

    <x-admin.card :title="__('Player auction')" class="mt-6 max-w-2xl">
        @if(! $edition)
            <x-admin.empty icon="gavel">{{ __('There is no season yet.') }}</x-admin.empty>
        @else
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-lg font-bold tracking-tight text-slate-900">{{ $edition->name }}</p>
                    <p class="mt-0.5 text-[13px] text-slate-500">
                        @if(! $auction)
                            {{ __('The auction has not been set up yet.') }}
                        @elseif($auction->isLive())
                            {{ __('Auction is :status — round :round', ['status' => __($auction->status), 'round' => $auction->round]) }}
                        @else
                            {{ __('Auction is :status', ['status' => __($auction->status)]) }}
                        @endif
                    </p>
                </div>
                @if($auction)
                    <x-status-badge :status="$auction->status" />
                @endif
            </div>

            <div class="mt-5 flex flex-wrap gap-2">
                @if($inProgress)
                    <x-admin.button :href="route('admin.auctions.console', $edition)" variant="primary" size="lg" icon="gavel" class="max-sm:flex-1">{{ __('Open the console') }}</x-admin.button>
                    <x-admin.button :href="route('admin.auctions.show', $edition)" variant="secondary" size="lg" class="max-sm:flex-1">{{ __('Rules and set-up') }}</x-admin.button>
                @else
                    <x-admin.button :href="route('admin.auctions.show', $edition)" variant="primary" size="lg" icon="gavel" class="max-sm:flex-1">
                        {{ $auction ? __('Open the auction') : __('Set up the auction') }}
                    </x-admin.button>
                @endif
            </div>
        @endif
    </x-admin.card>
@endsection
