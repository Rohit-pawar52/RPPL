@extends('layouts.public')

@section('title', __('registration.titles.success').' · '.$branding->shortName)

@section('content')
    <div class="pub-card mx-auto max-w-lg p-5 text-center sm:p-6">
        <span class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-green-50 text-green-600" aria-hidden="true">
            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7" /></svg>
        </span>
        <h1 class="pub-h1 mt-3">{{ __('registration.success.heading') }}</h1>

        <div class="mt-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3">
            <p class="pub-eyebrow !text-green-700">{{ __('registration.success.registration_number') }}</p>
            <p class="mt-1 break-all text-xl font-bold tracking-wide text-slate-900">{{ $registration_number }}</p>
        </div>
        <p class="mt-2 text-xs font-medium text-red-600">{{ __('registration.success.save_notice') }}</p>

        <dl class="mt-5 grid grid-cols-1 gap-3 rounded-xl border border-line bg-slate-50 p-4 text-left sm:grid-cols-2">
            <div>
                <dt class="pub-eyebrow">{{ __('registration.success.edition') }}</dt>
                <dd class="mt-0.5 text-sm font-semibold text-slate-900">{{ $edition_name }}</dd>
            </div>
            <div>
                <dt class="pub-eyebrow">{{ __('registration.success.player_name') }}</dt>
                <dd class="mt-0.5 break-words text-sm font-semibold text-slate-900">{{ $player_name }}</dd>
            </div>
            <div>
                <dt class="pub-eyebrow">{{ __('registration.success.payment_status') }}</dt>
                <dd class="mt-1"><span class="pub-pill pub-pill-warn">{{ __('registration.success.pending') }}</span></dd>
            </div>
            @if($registration_fee !== null)
                <div>
                    <dt class="pub-eyebrow">{{ __('registration.success.registration_fee') }}</dt>
                    <dd class="mt-0.5 text-sm font-semibold text-slate-900">{{ money($registration_fee) }}</dd>
                </div>
            @endif
        </dl>

        <p class="mt-5 text-xs leading-relaxed text-slate-500">
            {{ __('registration.success.verification_notice', ['league' => $branding->shortName]) }}
            {{ __('registration.success.return_notice') }}
            <a href="{{ route('public.player-registration.status') }}" class="pub-link">{{ __('registration.form.check_status_link') }}</a>
        </p>

        <a href="{{ route('public.home') }}" class="pub-link mt-4 inline-flex min-h-10 items-center text-xs">
            &larr; {{ __('registration.success.back_home', ['league' => $branding->shortName]) }}
        </a>
    </div>
@endsection
