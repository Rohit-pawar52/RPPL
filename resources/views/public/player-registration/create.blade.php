@extends('layouts.public')

@section('title', 'Player Registration &middot; RPPL')

@section('content')
    @if(! $edition)
        <div class="rounded-lg border border-neutral-200 bg-white p-6 text-center">
            <h1 class="text-base font-semibold text-neutral-900">Player Registration</h1>
            <p class="mt-2 text-sm text-neutral-500">Player registration is currently closed.</p>
            <p class="mt-1 text-xs text-neutral-400">Please check back later or contact RPPL administration.</p>
            <p class="mt-4 text-xs text-neutral-500">
                Already registered?
                <a href="{{ route('public.player-registration.status') }}" class="font-medium theme-link hover:underline">Check Registration Status</a>
            </p>
        </div>
    @else
        <div class="mb-4 rounded-lg border border-neutral-200 bg-white p-4">
            <h1 class="text-base font-semibold text-neutral-900">Player Registration &mdash; {{ $edition->name }}</h1>
            <p class="mt-1 text-xs text-neutral-500">
                Registration fee: <span class="font-medium text-neutral-800">&#8377;{{ number_format($edition->registration_fee, 2) }}</span>
            </p>
            <p class="mt-2 text-xs text-neutral-500">
                Pay the registration fee via UPI/bank transfer and upload your payment proof below. Payment and
                document verification is done manually by RPPL administration after submission.
            </p>
            <p class="mt-2 text-xs text-neutral-500">
                Already registered?
                <a href="{{ route('public.player-registration.status') }}" class="font-medium theme-link hover:underline">Check Registration Status</a>
            </p>
        </div>

        <div class="max-w-lg rounded-lg border border-neutral-200 bg-white p-4">
            <form method="POST" action="{{ route('public.player-registration.store') }}" enctype="multipart/form-data" novalidate>
                @csrf

                <x-form.input name="name" label="Full Name" :value="old('name')" required autofocus />
                <x-form.input name="phone" label="Phone Number" :value="old('phone')" placeholder="10-digit mobile number" required />
                <x-form.input name="email" label="Email (optional)" type="email" :value="old('email')" />
                <x-form.input name="date_of_birth" label="Date of Birth" type="date" :value="old('date_of_birth')" required />

                <x-form.select
                    name="primary_role"
                    label="Player Type"
                    placeholder="Select player type"
                    :options="[
                        'batter' => 'Batter',
                        'bowler' => 'Bowler',
                        'all_rounder' => 'All-rounder',
                        'wicket_keeper' => 'Wicket Keeper',
                    ]"
                    :value="old('primary_role')"
                />

                <div class="mb-3.5">
                    <label for="aadhaar_document" class="mb-1 block text-xs font-medium text-neutral-700">Aadhaar Document</label>
                    <input
                        id="aadhaar_document"
                        name="aadhaar_document"
                        type="file"
                        accept="image/jpeg,image/png,application/pdf"
                        class="block w-full text-xs text-neutral-600 file:mr-3 file:rounded-md file:border file:border-neutral-300 file:bg-white file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-neutral-700"
                    />
                    <p class="mt-1 text-[11px] text-neutral-400">JPEG, PNG, or PDF &mdash; up to 4 MB.</p>
                    @error('aadhaar_document')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-3.5">
                    <label for="payment_proof" class="mb-1 block text-xs font-medium text-neutral-700">Payment Proof</label>
                    <input
                        id="payment_proof"
                        name="payment_proof"
                        type="file"
                        accept="image/jpeg,image/png"
                        class="block w-full text-xs text-neutral-600 file:mr-3 file:rounded-md file:border file:border-neutral-300 file:bg-white file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-neutral-700"
                    />
                    <p class="mt-1 text-[11px] text-neutral-400">Screenshot of your UPI/bank payment &mdash; JPEG or PNG, up to 2 MB.</p>
                    @error('payment_proof')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="w-full rounded-md theme-button px-3 py-2 text-[13px] font-medium">
                    Submit Registration
                </button>
            </form>
        </div>
    @endif
@endsection
