@extends('layouts.public')

@section('title', __('registration.titles.registration').' · '.$branding->shortName)

@php
    $fileClass = 'block w-full rounded-lg border border-slate-300 bg-white text-xs text-slate-600 file:mr-3 file:h-11 file:cursor-pointer file:border-0 file:border-r file:border-slate-300 file:bg-slate-50 file:px-4 file:text-xs file:font-semibold file:text-slate-700';
@endphp

@section('content')
    @if(! $edition)
        <div class="pub-card mx-auto max-w-lg p-6 text-center">
            <h1 class="pub-h1">{{ __('registration.form.heading') }}</h1>
            @if($upcomingEdition ?? null)
                <p class="mt-2 text-sm text-slate-600">{{ __('registration.window.not_yet_open') }}</p>
                <p class="pub-meta mt-1">{{ __('registration.window.opens_on', ['date' => display_datetime($upcomingEdition->registration_opens_at, 'd M Y, h:i A')]) }}</p>
            @else
                <p class="mt-2 text-sm text-slate-600">{{ __('registration.closed.message') }}</p>
                <p class="pub-meta mt-1">{{ __('registration.closed.contact', ['league' => $branding->shortName]) }}</p>
            @endif
            <p class="mt-4 text-xs text-slate-500">
                {{ __('registration.form.already_registered') }}
                <a href="{{ route('public.player-registration.status') }}" class="pub-link">{{ __('registration.form.check_status_link') }}</a>
            </p>
        </div>
    @else
        <div class="mx-auto max-w-2xl">
            <div class="pub-card mb-4 p-4">
                <h1 class="pub-h1">{{ __('registration.form.heading') }} &mdash; {{ $edition->name }}</h1>
                <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1">
                    <p class="text-xs text-slate-500">
                        {{ __('registration.form.fee_label') }} <span class="font-semibold text-slate-900">{{ money($edition->registration_fee) }}</span>
                    </p>
                    @if($edition->registration_closes_at)
                        <p class="text-xs text-slate-500">{{ __('registration.window.closes_on', ['date' => display_datetime($edition->registration_closes_at, 'd M Y, h:i A')]) }}</p>
                    @endif
                </div>
                <p class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-xs leading-relaxed text-amber-800 ring-1 ring-inset ring-amber-200">
                    {{ __('registration.form.payment_instructions', ['league' => $branding->shortName]) }}
                </p>
                <p class="mt-3 text-xs text-slate-500">
                    {{ __('registration.form.already_registered') }}
                    <a href="{{ route('public.player-registration.status') }}" class="pub-link">{{ __('registration.form.check_status_link') }}</a>
                </p>
            </div>

            <div class="pub-card p-4 sm:p-5">
                <form method="POST" action="{{ route('public.player-registration.store') }}" enctype="multipart/form-data" novalidate>
                    @csrf

                    <div class="grid grid-cols-1 gap-x-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            @include('public.player-registration._field', ['name' => 'name', 'label' => __('registration.fields.name'), 'required' => true, 'autofocus' => true])
                        </div>
                        @include('public.player-registration._field', ['name' => 'phone', 'label' => __('registration.fields.phone'), 'placeholder' => __('registration.fields.phone_placeholder'), 'required' => true])
                        @include('public.player-registration._field', ['name' => 'email', 'label' => __('registration.fields.email'), 'type' => 'email'])
                        @include('public.player-registration._field', ['name' => 'date_of_birth', 'label' => __('registration.fields.date_of_birth'), 'type' => 'date', 'required' => true])
                        @include('public.player-registration._field', [
                            'name' => 'primary_role',
                            'label' => __('registration.fields.player_type'),
                            'selectPlaceholder' => __('registration.fields.player_type_placeholder'),
                            'options' => [
                                'batter' => __('registration.roles.batter'),
                                'bowler' => __('registration.roles.bowler'),
                                'all_rounder' => __('registration.roles.all_rounder'),
                                'wicket_keeper' => __('registration.roles.wicket_keeper'),
                            ],
                        ])
                    </div>

                    <div class="mb-4">
                        <label for="aadhaar_document" class="mb-1 block text-xs font-semibold text-slate-700">{{ __('registration.fields.aadhaar_document') }}</label>
                        <input
                            id="aadhaar_document"
                            name="aadhaar_document"
                            type="file"
                            accept="image/jpeg,image/png,application/pdf"
                            class="{{ $fileClass }}"
                        />
                        <p class="pub-meta mt-1">{{ __('registration.fields.aadhaar_hint') }}</p>
                        @error('aadhaar_document')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="mb-5">
                        <label for="payment_proof" class="mb-1 block text-xs font-semibold text-slate-700">{{ __('registration.fields.payment_proof') }}</label>
                        <input
                            id="payment_proof"
                            name="payment_proof"
                            type="file"
                            accept="image/jpeg,image/png"
                            class="{{ $fileClass }}"
                        />
                        <p class="pub-meta mt-1">{{ __('registration.fields.payment_proof_hint') }}</p>
                        @error('payment_proof')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit" class="pub-btn h-11 w-full text-sm">
                        {{ __('registration.form.submit') }}
                    </button>
                </form>
            </div>
        </div>
    @endif
@endsection
