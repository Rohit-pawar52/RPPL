@extends('layouts.public')

@section('title', __('registration.titles.status').' · '.$branding->shortName)

@section('content')
    <div class="mx-auto max-w-lg rounded-lg border border-neutral-200 bg-white p-6">
        <h1 class="text-base font-semibold text-neutral-900">{{ __('registration.status.heading') }}</h1>
        <p class="mt-1 text-xs text-neutral-500">
            {{ __('registration.status.intro') }}
        </p>

        <form method="POST" action="{{ route('public.player-registration.status.lookup') }}" class="mt-4" novalidate>
            @csrf
            <x-form.input
                name="registration_number"
                :label="__('registration.fields.registration_number')"
                placeholder="RPPL-2026-000125"
                autocomplete="off"
                required
            />
            <x-form.input
                name="phone"
                :label="__('registration.fields.phone')"
                :placeholder="__('registration.fields.phone_placeholder')"
                autocomplete="tel"
                required
            />
            <button type="submit" class="w-full rounded-md theme-button px-3 py-2 text-[13px] font-medium">
                {{ __('registration.status.submit') }}
            </button>
        </form>

        @isset($searched)
            @if($result)
                @php
                    $labels = [
                        'pending' => [__('registration.status.payment.pending'), __('registration.status.payment.pending_message')],
                        'paid' => [__('registration.status.payment.paid'), __('registration.status.payment.paid_message')],
                        'failed' => [__('registration.status.payment.failed'), __('registration.status.payment.failed_message', ['league' => $branding->shortName])],
                        'refunded' => [__('registration.status.payment.refunded'), __('registration.status.payment.refunded_message')],
                    ];
                    [$paymentLabel, $paymentMessage] = $labels[$result->payment_status] ?? [ucfirst($result->payment_status), ''];
                @endphp
                <div class="mt-5 rounded-md border border-neutral-100 bg-neutral-50 p-3">
                    <dl class="grid grid-cols-1 gap-3 text-xs sm:grid-cols-2">
                        <div>
                            <dt class="text-neutral-400">{{ __('registration.status.registration_number') }}</dt>
                            <dd class="mt-0.5 font-medium text-neutral-800">{{ $result->registration_number }}</dd>
                        </div>
                        <div>
                            <dt class="text-neutral-400">{{ __('registration.status.player_name') }}</dt>
                            <dd class="mt-0.5 font-medium text-neutral-800">{{ $result->player->name }}</dd>
                        </div>
                        <div>
                            <dt class="text-neutral-400">{{ __('registration.status.edition') }}</dt>
                            <dd class="mt-0.5 font-medium text-neutral-800">{{ $result->edition->name }} ({{ $result->edition->year }})</dd>
                        </div>
                        <div>
                            <dt class="text-neutral-400">{{ __('registration.status.registration_fee') }}</dt>
                            <dd class="mt-0.5 font-medium text-neutral-800">
                                {{ $result->registration_fee !== null ? money($result->registration_fee) : '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-neutral-400">{{ __('registration.status.payment_status') }}</dt>
                            <dd class="mt-0.5 font-medium text-neutral-800">{{ $paymentLabel }}</dd>
                        </div>
                        <div>
                            <dt class="text-neutral-400">{{ __('registration.status.registered_at') }}</dt>
                            <dd class="mt-0.5 font-medium text-neutral-800">{{ $result->registered_at?->format('d M Y') ?? '—' }}</dd>
                        </div>
                    </dl>
                    @if($paymentMessage)
                        <p class="mt-3 text-xs text-neutral-500">{{ $paymentMessage }}</p>
                    @endif
                </div>
            @else
                <div class="mt-4 rounded-md border border-red-100 bg-red-50 px-3 py-2 text-xs text-red-700">
                    {{ __('registration.status.not_found') }}
                </div>
            @endif
        @endisset

        <a href="{{ route('public.player-registration.create') }}" class="mt-5 inline-block text-xs font-medium theme-link hover:underline">
            &larr; {{ __('registration.status.back_to_registration') }}
        </a>
    </div>
@endsection
