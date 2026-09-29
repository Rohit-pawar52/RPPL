@extends('layouts.public')

@section('title', __('registration.titles.registration').' · '.$branding->shortName)

@section('content')
    @if(! $edition)
        <div class="rounded-lg border border-neutral-200 bg-white p-6 text-center">
            <h1 class="text-base font-semibold text-neutral-900">{{ __('registration.form.heading') }}</h1>
            <p class="mt-2 text-sm text-neutral-500">{{ __('registration.closed.message') }}</p>
            <p class="mt-1 text-xs text-neutral-400">{{ __('registration.closed.contact', ['league' => $branding->shortName]) }}</p>
            <p class="mt-4 text-xs text-neutral-500">
                {{ __('registration.form.already_registered') }}
                <a href="{{ route('public.player-registration.status') }}" class="font-medium theme-link hover:underline">{{ __('registration.form.check_status_link') }}</a>
            </p>
        </div>
    @else
        <div class="mb-4 rounded-lg border border-neutral-200 bg-white p-4">
            <h1 class="text-base font-semibold text-neutral-900">{{ __('registration.form.heading') }} &mdash; {{ $edition->name }}</h1>
            <p class="mt-1 text-xs text-neutral-500">
                {{ __('registration.form.fee_label') }} <span class="font-medium text-neutral-800">{{ money($edition->registration_fee) }}</span>
            </p>
            <p class="mt-2 text-xs text-neutral-500">
                {{ __('registration.form.payment_instructions', ['league' => $branding->shortName]) }}
            </p>
            <p class="mt-2 text-xs text-neutral-500">
                {{ __('registration.form.already_registered') }}
                <a href="{{ route('public.player-registration.status') }}" class="font-medium theme-link hover:underline">{{ __('registration.form.check_status_link') }}</a>
            </p>
        </div>

        <div class="max-w-lg rounded-lg border border-neutral-200 bg-white p-4">
            <form method="POST" action="{{ route('public.player-registration.store') }}" enctype="multipart/form-data" novalidate>
                @csrf

                <x-form.input name="name" :label="__('registration.fields.name')" :value="old('name')" required autofocus />
                <x-form.input name="phone" :label="__('registration.fields.phone')" :value="old('phone')" :placeholder="__('registration.fields.phone_placeholder')" required />
                <x-form.input name="email" :label="__('registration.fields.email')" type="email" :value="old('email')" />
                <x-form.input name="date_of_birth" :label="__('registration.fields.date_of_birth')" type="date" :value="old('date_of_birth')" required />

                <x-form.select
                    name="primary_role"
                    :label="__('registration.fields.player_type')"
                    :placeholder="__('registration.fields.player_type_placeholder')"
                    :options="[
                        'batter' => __('registration.roles.batter'),
                        'bowler' => __('registration.roles.bowler'),
                        'all_rounder' => __('registration.roles.all_rounder'),
                        'wicket_keeper' => __('registration.roles.wicket_keeper'),
                    ]"
                    :value="old('primary_role')"
                />

                <div class="mb-3.5">
                    <label for="aadhaar_document" class="mb-1 block text-xs font-medium text-neutral-700">{{ __('registration.fields.aadhaar_document') }}</label>
                    <input
                        id="aadhaar_document"
                        name="aadhaar_document"
                        type="file"
                        accept="image/jpeg,image/png,application/pdf"
                        class="block w-full text-xs text-neutral-600 file:mr-3 file:rounded-md file:border file:border-neutral-300 file:bg-white file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-neutral-700"
                    />
                    <p class="mt-1 text-[11px] text-neutral-400">{{ __('registration.fields.aadhaar_hint') }}</p>
                    @error('aadhaar_document')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mb-3.5">
                    <label for="payment_proof" class="mb-1 block text-xs font-medium text-neutral-700">{{ __('registration.fields.payment_proof') }}</label>
                    <input
                        id="payment_proof"
                        name="payment_proof"
                        type="file"
                        accept="image/jpeg,image/png"
                        class="block w-full text-xs text-neutral-600 file:mr-3 file:rounded-md file:border file:border-neutral-300 file:bg-white file:px-3 file:py-1.5 file:text-xs file:font-medium file:text-neutral-700"
                    />
                    <p class="mt-1 text-[11px] text-neutral-400">{{ __('registration.fields.payment_proof_hint') }}</p>
                    @error('payment_proof')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="w-full rounded-md theme-button px-3 py-2 text-[13px] font-medium">
                    {{ __('registration.form.submit') }}
                </button>
            </form>
        </div>
    @endif
@endsection
