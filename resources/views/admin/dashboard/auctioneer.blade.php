@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
    <p class="mb-4 text-xs text-neutral-500">
        Welcome back, {{ auth()->user()->name }}. You are signed in as
        <span class="font-medium text-neutral-700">{{ auth()->user()->role->name }}</span>.
    </p>

    <x-admin.card title="Player auction" class="max-w-xl">
        @if(! $edition)
            <p class="text-[13px] text-slate-600">There is no season yet.</p>
        @else
            <div class="flex items-center justify-between gap-3">
                <div>
                    <p class="text-[15px] font-semibold text-slate-900">{{ $edition->name }}</p>
                    <p class="mt-0.5 text-xs text-slate-500">
                        {{ $auction ? 'Auction is '.$auction->status.($auction->isLive() ? ' — round '.$auction->round : '') : 'The auction has not been set up yet.' }}
                    </p>
                </div>
                @if($auction)
                    <x-status-badge :status="$auction->status" />
                @endif
            </div>

            <div class="mt-4">
                <x-admin.button :href="route('admin.auctions.show', $edition)" variant="primary" icon="gavel">
                    {{ $auction ? 'Open the auction' : 'Set up the auction' }}
                </x-admin.button>
            </div>
        @endif
    </x-admin.card>
@endsection
