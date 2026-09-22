@extends('layouts.public')

@section('title', 'Check Registration Status · '.$branding->shortName)

@section('content')
    <div class="mx-auto max-w-lg rounded-lg border border-neutral-200 bg-white p-6">
        <h1 class="text-base font-semibold text-neutral-900">Check Registration Status</h1>
        <p class="mt-1 text-xs text-neutral-500">
            Enter your Registration Number and the phone number you registered with.
        </p>

        <form method="POST" action="{{ route('public.player-registration.status.lookup') }}" class="mt-4" novalidate>
            @csrf
            <x-form.input
                name="registration_number"
                label="Registration Number"
                placeholder="RPPL-2026-000125"
                autocomplete="off"
                required
            />
            <x-form.input
                name="phone"
                label="Phone Number"
                placeholder="10-digit mobile number"
                autocomplete="tel"
                required
            />
            <button type="submit" class="w-full rounded-md theme-button px-3 py-2 text-[13px] font-medium">
                Check Status
            </button>
        </form>

        @isset($searched)
            @if($result)
                @php
                    $labels = [
                        'pending' => ['Pending Verification', 'Your payment proof is awaiting manual verification.'],
                        'paid' => ['Paid', 'Your payment has been verified.'],
                        'failed' => ['Payment Verification Failed', 'Your payment could not be verified. Please contact '.$branding->shortName.' administration.'],
                        'refunded' => ['Refunded', 'Your payment is marked as refunded.'],
                    ];
                    [$paymentLabel, $paymentMessage] = $labels[$result->payment_status] ?? [ucfirst($result->payment_status), ''];
                @endphp
                <div class="mt-5 rounded-md border border-neutral-100 bg-neutral-50 p-3">
                    <dl class="grid grid-cols-1 gap-3 text-xs sm:grid-cols-2">
                        <div>
                            <dt class="text-neutral-400">Registration Number</dt>
                            <dd class="mt-0.5 font-medium text-neutral-800">{{ $result->registration_number }}</dd>
                        </div>
                        <div>
                            <dt class="text-neutral-400">Player Name</dt>
                            <dd class="mt-0.5 font-medium text-neutral-800">{{ $result->player->name }}</dd>
                        </div>
                        <div>
                            <dt class="text-neutral-400">Edition</dt>
                            <dd class="mt-0.5 font-medium text-neutral-800">{{ $result->edition->name }} ({{ $result->edition->year }})</dd>
                        </div>
                        <div>
                            <dt class="text-neutral-400">Registration Fee</dt>
                            <dd class="mt-0.5 font-medium text-neutral-800">
                                {{ $result->registration_fee !== null ? money($result->registration_fee) : '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-neutral-400">Payment Status</dt>
                            <dd class="mt-0.5 font-medium text-neutral-800">{{ $paymentLabel }}</dd>
                        </div>
                        <div>
                            <dt class="text-neutral-400">Registered At</dt>
                            <dd class="mt-0.5 font-medium text-neutral-800">{{ $result->registered_at?->format('d M Y') ?? '—' }}</dd>
                        </div>
                    </dl>
                    @if($paymentMessage)
                        <p class="mt-3 text-xs text-neutral-500">{{ $paymentMessage }}</p>
                    @endif
                </div>
            @else
                <div class="mt-4 rounded-md border border-red-100 bg-red-50 px-3 py-2 text-xs text-red-700">
                    No matching registration was found. Please check your Registration Number and Phone Number.
                </div>
            @endif
        @endisset

        <a href="{{ route('public.player-registration.create') }}" class="mt-5 inline-block text-xs font-medium theme-link hover:underline">
            &larr; Back to Player Registration
        </a>
    </div>
@endsection
