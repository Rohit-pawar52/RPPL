@extends('layouts.public')

@section('title', 'Registration Successful · '.$branding->shortName)

@section('content')
    <div class="mx-auto max-w-lg rounded-lg border border-neutral-200 bg-white p-6 text-center">
        <h1 class="text-base font-semibold text-neutral-900">Registration Successful</h1>

        <p class="mt-4 text-xs text-neutral-500">Registration Number</p>
        <p class="mt-1 text-xl font-semibold tracking-wide theme-primary-text">{{ $registration_number }}</p>
        <p class="mt-2 text-xs font-medium text-red-600">Please save this Registration Number &mdash; you will need it to check your status later.</p>

        <dl class="mt-5 grid grid-cols-1 gap-3 text-left text-xs sm:grid-cols-2">
            <div>
                <dt class="text-neutral-400">Edition</dt>
                <dd class="mt-0.5 font-medium text-neutral-800">{{ $edition_name }}</dd>
            </div>
            <div>
                <dt class="text-neutral-400">Player Name</dt>
                <dd class="mt-0.5 font-medium text-neutral-800">{{ $player_name }}</dd>
            </div>
            <div>
                <dt class="text-neutral-400">Payment Status</dt>
                <dd class="mt-0.5 font-medium text-neutral-800">Pending</dd>
            </div>
            @if($registration_fee !== null)
                <div>
                    <dt class="text-neutral-400">Registration Fee</dt>
                    <dd class="mt-0.5 font-medium text-neutral-800">{{ money($registration_fee) }}</dd>
                </div>
            @endif
        </dl>

        <p class="mt-5 text-xs text-neutral-500">
            Your payment and documents will be manually verified by {{ $branding->shortName }} administration. This may take a few days.
            You can return anytime and
            <a href="{{ route('public.player-registration.status') }}" class="font-medium theme-link hover:underline">Check Registration Status</a>
            using your Registration Number.
        </p>

        <a href="{{ route('public.home') }}" class="mt-5 inline-block text-xs font-medium theme-link hover:underline">
            &larr; Back to {{ $branding->shortName }} home
        </a>
    </div>
@endsection
