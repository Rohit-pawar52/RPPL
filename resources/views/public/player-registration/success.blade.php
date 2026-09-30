@extends('layouts.public')

@section('title', __('registration.titles.success').' · '.$branding->shortName)

@section('content')
    <div class="mx-auto max-w-lg rounded-lg border border-neutral-200 bg-white p-6 text-center">
        <h1 class="text-base font-semibold text-neutral-900">{{ __('registration.success.heading') }}</h1>

        <p class="mt-4 text-xs text-neutral-500">{{ __('registration.success.registration_number') }}</p>
        <p class="mt-1 text-xl font-semibold tracking-wide theme-primary-text">{{ $registration_number }}</p>
        <p class="mt-2 text-xs font-medium text-red-600">{{ __('registration.success.save_notice') }}</p>

        <dl class="mt-5 grid grid-cols-1 gap-3 text-left text-xs sm:grid-cols-2">
            <div>
                <dt class="text-neutral-400">{{ __('registration.success.edition') }}</dt>
                <dd class="mt-0.5 font-medium text-neutral-800">{{ $edition_name }}</dd>
            </div>
            <div>
                <dt class="text-neutral-400">{{ __('registration.success.player_name') }}</dt>
                <dd class="mt-0.5 font-medium text-neutral-800">{{ $player_name }}</dd>
            </div>
            <div>
                <dt class="text-neutral-400">{{ __('registration.success.payment_status') }}</dt>
                <dd class="mt-0.5 font-medium text-neutral-800">{{ __('registration.success.pending') }}</dd>
            </div>
            @if($registration_fee !== null)
                <div>
                    <dt class="text-neutral-400">{{ __('registration.success.registration_fee') }}</dt>
                    <dd class="mt-0.5 font-medium text-neutral-800">{{ money($registration_fee) }}</dd>
                </div>
            @endif
        </dl>

        <p class="mt-5 text-xs text-neutral-500">
            {{ __('registration.success.verification_notice', ['league' => $branding->shortName]) }}
            {{ __('registration.success.return_notice') }}
            <a href="{{ route('public.player-registration.status') }}" class="font-medium theme-link hover:underline">{{ __('registration.form.check_status_link') }}</a>
        </p>

        <a href="{{ route('public.home') }}" class="mt-5 inline-block text-xs font-medium theme-link hover:underline">
            &larr; {{ __('registration.success.back_home', ['league' => $branding->shortName]) }}
        </a>
    </div>
@endsection
