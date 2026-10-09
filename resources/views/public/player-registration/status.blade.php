@extends('layouts.public')

@section('title', __('registration.titles.status').' · '.$branding->shortName)

@section('content')
    <div class="mx-auto max-w-lg">
        <div class="pc-panel">
            <div class="relative isolate overflow-hidden bg-linear-to-br from-navy-900 to-navy-800 px-5 py-5 text-white sm:px-6">
                <div class="pointer-events-none absolute -right-10 -top-12 -z-10 h-36 w-36 rounded-full bg-brand/40 blur-2xl" aria-hidden="true"></div>
                <span class="mb-2 flex h-10 w-10 items-center justify-center rounded-full bg-white/10 text-accent-dark ring-1 ring-white/15" aria-hidden="true">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7" /><path d="M20 20l-3.5-3.5" /></svg>
                </span>
                <h1 class="text-xl font-bold tracking-tight sm:text-2xl">{{ __('registration.status.heading') }}</h1>
                <p class="mt-1 text-xs leading-relaxed text-slate-300">{{ __('registration.status.intro') }}</p>
            </div>

            <form method="POST" action="{{ route('public.player-registration.status.lookup') }}" class="p-5 sm:p-6" novalidate>
                @csrf
                @include('public.player-registration._field', [
                    'name' => 'phone',
                    'label' => __('registration.fields.phone'),
                    'type' => 'tel',
                    'inputmode' => 'tel',
                    'placeholder' => __('registration.fields.phone_placeholder'),
                    'autocomplete' => 'tel',
                    'required' => true,
                    'autofocus' => ! isset($searched),
                ])
                @include('public.player-registration._field', [
                    'name' => 'registration_number',
                    'label' => __('registration.fields.registration_number_optional'),
                    'placeholder' => 'RPPL-2026-000125',
                    'autocomplete' => 'off',
                    'hint' => __('registration.status.number_optional_hint'),
                ])
                <button type="submit" class="btn btn-primary btn-lg btn-block">
                    {{ __('registration.status.submit') }}
                </button>
            </form>
        </div>

        @isset($searched)
            @forelse($results as $result)
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
                    // The colour of the strip on top says it before the words do (meaning colours).
                    $stripe = match ($result->payment_status) {
                        'paid' => 'bg-green-500',
                        'failed' => 'bg-red-500',
                        'pending' => 'bg-amber-400',
                        default => 'bg-slate-300',
                    };
                @endphp
                <article class="pc-panel pc-rise mt-4">
                    <div class="h-1.5 {{ $stripe }}" aria-hidden="true"></div>
                    <div class="p-5 sm:p-6">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="pc-eyebrow !text-slate-400">{{ __('registration.status.registration_number') }}</p>
                                <p class="mt-0.5 break-all text-xl font-bold tracking-wide text-slate-900">{{ $result->registration_number }}</p>
                            </div>
                            <div class="sm:text-right">
                                <p class="pc-eyebrow !text-slate-400">{{ __('registration.status.payment_status') }}</p>
                                <span class="pub-pill {{ $paymentPill }} mt-1">{{ $paymentLabel }}</span>
                            </div>
                        </div>

                        <dl class="mt-4 grid grid-cols-1 gap-x-4 gap-y-3 border-t border-line pt-4 sm:grid-cols-2">
                            <div>
                                <dt class="pc-eyebrow !text-slate-400">{{ __('registration.status.player_name') }}</dt>
                                <dd class="mt-0.5 break-words text-sm font-semibold text-slate-900">{{ $result->player->name }}</dd>
                            </div>
                            <div>
                                <dt class="pc-eyebrow !text-slate-400">{{ __('registration.status.edition') }}</dt>
                                <dd class="mt-0.5 text-sm font-semibold text-slate-900">{{ $result->edition->name }} ({{ $result->edition->year }})</dd>
                            </div>
                            <div>
                                <dt class="pc-eyebrow !text-slate-400">{{ __('registration.status.registration_fee') }}</dt>
                                <dd class="mt-0.5 text-sm font-semibold tabular-nums text-slate-900">
                                    {{ $result->registration_fee !== null ? money($result->registration_fee) : '—' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="pc-eyebrow !text-slate-400">{{ __('registration.status.registered_at') }}</dt>
                                <dd class="mt-0.5 text-sm font-semibold text-slate-900">{{ display_datetime($result->registered_at, 'd M Y') ?? '—' }}</dd>
                            </div>
                        </dl>

                        @if($result->payment_status === 'failed' && filled($result->payment_failure_reason))
                            <p class="mt-4 rounded-lg bg-red-50 px-3 py-2.5 text-xs font-semibold leading-relaxed text-red-700 ring-1 ring-inset ring-red-200">
                                {{ __('registration.status.payment.failure_reason') }} {{ $result->payment_failure_reason }}
                            </p>
                        @endif
                        @if($paymentMessage)
                            <p class="mt-4 text-xs leading-relaxed text-slate-600">{{ $paymentMessage }}</p>
                        @endif
                    </div>
                </article>
            @empty
                <div class="pc-empty pc-rise mt-4" role="alert">
                    <span class="pc-empty-icon bg-red-50 text-red-500"><x-icon name="clipboard" class="h-7 w-7" /></span>
                    <p class="pc-empty-title">{{ __('registration.status.not_found') }}</p>
                    <a href="{{ route('public.player-registration.create') }}" class="btn btn-secondary mt-2">{{ __('registration.status.back_to_registration') }}</a>
                </div>
            @endforelse
        @endisset

        <p class="mt-5 text-center">
            <a href="{{ route('public.player-registration.create') }}" class="pub-link inline-flex min-h-10 items-center text-xs">
                &larr; {{ __('registration.status.back_to_registration') }}
            </a>
        </p>
    </div>
@endsection
