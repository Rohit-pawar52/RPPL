@extends('layouts.public')

@section('title', __('registration.titles.status').' · '.$branding->shortName)

@section('content')
    <div class="pub-card mx-auto max-w-lg p-5 sm:p-6">
        <h1 class="pub-h1">{{ __('registration.status.heading') }}</h1>
        <p class="pub-meta mt-1">
            {{ __('registration.status.intro') }}
        </p>

        <form method="POST" action="{{ route('public.player-registration.status.lookup') }}" class="mt-4" novalidate>
            @csrf
            @include('public.player-registration._field', [
                'name' => 'registration_number',
                'label' => __('registration.fields.registration_number'),
                'placeholder' => 'RPPL-2026-000125',
                'autocomplete' => 'off',
                'required' => true,
            ])
            @include('public.player-registration._field', [
                'name' => 'phone',
                'label' => __('registration.fields.phone'),
                'placeholder' => __('registration.fields.phone_placeholder'),
                'autocomplete' => 'tel',
                'required' => true,
            ])
            <button type="submit" class="pub-btn h-11 w-full text-sm">
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
                    $paymentPill = match ($result->payment_status) {
                        'paid' => 'pub-pill-success',
                        'failed' => 'pub-pill-danger',
                        'pending' => 'pub-pill-warn',
                        default => 'pub-pill-neutral',
                    };
                @endphp
                <div class="mt-5 rounded-xl border border-line bg-slate-50 p-4">
                    <dl class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div>
                            <dt class="pub-eyebrow">{{ __('registration.status.registration_number') }}</dt>
                            <dd class="mt-0.5 break-words text-sm font-semibold text-slate-900">{{ $result->registration_number }}</dd>
                        </div>
                        <div>
                            <dt class="pub-eyebrow">{{ __('registration.status.player_name') }}</dt>
                            <dd class="mt-0.5 break-words text-sm font-semibold text-slate-900">{{ $result->player->name }}</dd>
                        </div>
                        <div>
                            <dt class="pub-eyebrow">{{ __('registration.status.edition') }}</dt>
                            <dd class="mt-0.5 text-sm font-semibold text-slate-900">{{ $result->edition->name }} ({{ $result->edition->year }})</dd>
                        </div>
                        <div>
                            <dt class="pub-eyebrow">{{ __('registration.status.registration_fee') }}</dt>
                            <dd class="mt-0.5 text-sm font-semibold text-slate-900">
                                {{ $result->registration_fee !== null ? money($result->registration_fee) : '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="pub-eyebrow">{{ __('registration.status.payment_status') }}</dt>
                            <dd class="mt-1"><span class="pub-pill {{ $paymentPill }}">{{ $paymentLabel }}</span></dd>
                        </div>
                        <div>
                            <dt class="pub-eyebrow">{{ __('registration.status.registered_at') }}</dt>
                            <dd class="mt-0.5 text-sm font-semibold text-slate-900">{{ display_datetime($result->registered_at, 'd M Y') ?? '—' }}</dd>
                        </div>
                    </dl>
                    @if($paymentMessage)
                        <p class="mt-3 border-t border-line pt-3 text-xs leading-relaxed text-slate-600">{{ $paymentMessage }}</p>
                    @endif
                </div>
            @else
                <div class="mt-4 rounded-lg bg-red-50 px-3 py-2.5 text-xs text-red-700 ring-1 ring-inset ring-red-200" role="alert">
                    {{ __('registration.status.not_found') }}
                </div>
            @endif
        @endisset

        <a href="{{ route('public.player-registration.create') }}" class="pub-link mt-5 inline-flex min-h-10 items-center text-xs">
            &larr; {{ __('registration.status.back_to_registration') }}
        </a>
    </div>
@endsection
