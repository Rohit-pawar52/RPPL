@extends('layouts.public')

@section('title', __('registration.titles.registration').' · '.$branding->shortName)

@php
    $fileClass = 'block w-full rounded-lg border border-slate-300 bg-white text-xs text-slate-600 file:mr-3 file:h-11 file:cursor-pointer file:border-0 file:border-r file:border-slate-300 file:bg-slate-50 file:px-4 file:text-xs file:font-semibold file:text-slate-700';
    $sectionClass = 'pub-eyebrow mb-3 mt-6 border-t border-line pt-4';
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
        @php
            $maxSize = \App\Support\UploadLimits::megabytes($maxFileKb);
            $hasPaymentDetails = $payment['upi_id'] || $payment['qr_url'];
        @endphp

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
                    {{ __($hasPaymentDetails ? 'registration.form.payment_instructions_upi' : 'registration.form.payment_instructions', ['league' => $branding->shortName]) }}
                </p>
                <p class="mt-3 text-xs text-slate-500">
                    {{ __('registration.form.already_registered') }}
                    <a href="{{ route('public.player-registration.status') }}" class="pub-link">{{ __('registration.form.check_status_link') }}</a>
                </p>
            </div>

            <div class="pub-card p-4 sm:p-5">
                <form
                    id="player-registration-form"
                    method="POST"
                    action="{{ route('public.player-registration.store') }}"
                    enctype="multipart/form-data"
                    data-max-bytes="{{ $maxFileKb * 1024 }}"
                    data-too-large="{{ __('registration.js.too_large', ['size' => $maxSize]) }}"
                    data-submitting="{{ __('registration.form.submitting') }}"
                >
                    @csrf

                    <p class="mb-4 text-[11px] text-slate-500">{{ __('registration.form.required_note') }}</p>

                    {{-- About you --}}
                    <h2 class="pub-eyebrow mb-3">{{ __('registration.form.sections.you') }}</h2>
                    @include('public.player-registration._field', ['name' => 'name', 'label' => __('registration.fields.name'), 'required' => true, 'autofocus' => true, 'autocomplete' => 'name'])
                    <div class="grid grid-cols-1 gap-x-4 sm:grid-cols-2">
                        @include('public.player-registration._field', [
                            'name' => 'age',
                            'label' => __('registration.fields.age'),
                            'type' => 'number',
                            'inputmode' => 'numeric',
                            'min' => \App\Models\PlayerRegistration::AGE_MIN,
                            'max' => \App\Models\PlayerRegistration::AGE_MAX,
                            'placeholder' => __('registration.fields.age_placeholder'),
                        ])
                        @include('public.player-registration._field', [
                            'name' => 'phone',
                            'label' => __('registration.fields.phone'),
                            'type' => 'tel',
                            'inputmode' => 'tel',
                            'maxlength' => 20,
                            'autocomplete' => 'tel',
                            'placeholder' => __('registration.fields.phone_placeholder'),
                            'required' => true,
                        ])
                    </div>
                    @include('public.player-registration._field', ['name' => 'email', 'label' => __('registration.fields.email'), 'type' => 'email', 'autocomplete' => 'email'])

                    {{-- Playing details --}}
                    <h2 class="{{ $sectionClass }}">{{ __('registration.form.sections.playing') }}</h2>
                    @include('public.player-registration._choice', [
                        'name' => 'primary_role',
                        'label' => __('registration.fields.player_type'),
                        'required' => true,
                        'options' => collect(\App\Models\Player::PRIMARY_ROLES)->mapWithKeys(fn ($role) => [$role => __('registration.roles.'.$role)])->all(),
                    ])
                    @include('public.player-registration._choice', [
                        'name' => 'batting_style',
                        'label' => __('registration.fields.batting_style'),
                        'required' => true,
                        'options' => collect(\App\Models\Player::BATTING_STYLES)->mapWithKeys(fn ($style) => [$style => __('registration.batting_styles.'.$style)])->all(),
                    ])
                    @include('public.player-registration._choice', [
                        'name' => 'bowling_style',
                        'label' => __('registration.fields.bowling_style'),
                        'required' => true,
                        'options' => collect(\App\Models\Player::BOWLING_STYLES)->mapWithKeys(fn ($style) => [$style => __('registration.bowling_styles.'.$style)])->all(),
                    ])

                    {{-- Address --}}
                    <h2 class="{{ $sectionClass }}">{{ __('registration.form.sections.address') }}</h2>
                    <div class="grid grid-cols-1 gap-x-4 sm:grid-cols-3">
                        @include('public.player-registration._field', ['name' => 'village', 'label' => __('registration.fields.village'), 'maxlength' => 100, 'required' => true])
                        @include('public.player-registration._field', ['name' => 'tehsil', 'label' => __('registration.fields.tehsil'), 'maxlength' => 100, 'required' => true])
                        @include('public.player-registration._field', ['name' => 'district', 'label' => __('registration.fields.district'), 'maxlength' => 100, 'required' => true])
                    </div>

                    {{-- Photo --}}
                    <h2 class="{{ $sectionClass }}">{{ __('registration.form.sections.photo') }}</h2>
                    <div class="mb-4">
                        <label for="photo" class="mb-1 block text-xs font-semibold text-slate-700">{{ __('registration.fields.photo') }}<span class="text-red-500" aria-hidden="true"> *</span></label>
                        <input
                            id="photo"
                            name="photo"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            class="{{ $fileClass }}"
                            required
                        />
                        <p class="pub-meta mt-1">{{ __('registration.fields.photo_hint', ['size' => $maxSize]) }}</p>
                        <p class="mt-1 text-xs text-red-600" data-file-note="photo"></p>
                        <img data-file-preview="photo" class="mt-2 hidden h-24 w-24 rounded-lg border border-line object-cover" alt="" />
                        @error('photo')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Payment --}}
                    <h2 class="{{ $sectionClass }}">{{ __('registration.form.sections.payment') }}</h2>
                    <div class="mb-4 rounded-xl border border-line bg-slate-50 p-4">
                        <p class="text-xs text-slate-500">{{ __('registration.payment.amount') }}</p>
                        <p class="text-2xl font-bold text-slate-900">{{ money($edition->registration_fee) }}</p>

                        @if($payment['qr_url'])
                            <div class="mt-3 text-center">
                                <p class="mb-2 text-xs text-slate-600">{{ __('registration.payment.scan') }}</p>
                                <img src="{{ $payment['qr_url'] }}" alt="UPI QR" class="mx-auto h-52 w-52 rounded-lg border border-line bg-white object-contain p-2" />
                            </div>
                        @endif

                        @if($payment['upi_id'])
                            <div class="mt-3 flex items-center justify-between gap-2 rounded-lg bg-white px-3 py-2 ring-1 ring-inset ring-slate-200">
                                <div class="min-w-0">
                                    <p class="text-[11px] text-slate-500">{{ __('registration.payment.upi_id') }}</p>
                                    <p id="upi-id" class="break-all text-sm font-semibold text-slate-900">{{ $payment['upi_id'] }}</p>
                                </div>
                                <button
                                    type="button"
                                    data-copy="#upi-id"
                                    data-copied="{{ __('registration.payment.copied') }}"
                                    class="pub-btn-outline pub-btn-sm shrink-0"
                                >{{ __('registration.payment.copy') }}</button>
                            </div>

                            <a href="{{ $payment['pay_link'] }}" class="pub-btn mt-3 h-11 w-full text-sm sm:hidden">{{ __('registration.payment.pay_with_app') }}</a>
                            <p class="pub-meta mt-1 sm:hidden">{{ __('registration.payment.pay_with_app_hint') }}</p>
                        @endif
                    </div>

                    @include('public.player-registration._field', [
                        'name' => 'submitted_utr',
                        'label' => __('registration.fields.submitted_utr'),
                        'hint' => __('registration.fields.submitted_utr_hint'),
                        'autocomplete' => 'off',
                        'maxlength' => 40,
                    ])

                    <div class="mb-5">
                        <label for="payment_proof" class="mb-1 block text-xs font-semibold text-slate-700">{{ __('registration.fields.payment_proof') }}<span class="text-red-500" aria-hidden="true"> *</span></label>
                        <input
                            id="payment_proof"
                            name="payment_proof"
                            type="file"
                            accept="image/jpeg,image/png"
                            class="{{ $fileClass }}"
                            required
                        />
                        <p class="pub-meta mt-1">{{ __('registration.fields.payment_proof_hint', ['size' => $maxSize]) }}</p>
                        <p class="mt-1 text-xs text-red-600" data-file-note="payment_proof"></p>
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

        <script>
            (function () {
                var form = document.getElementById('player-registration-form');
                if (!form) return;

                var maxBytes = parseInt(form.dataset.maxBytes, 10);
                var submit = form.querySelector('button[type="submit"]');
                var submitLabel = submit.textContent;

                // A file that is too large is caught here, before a slow
                // upload on mobile data, instead of after it.
                form.querySelectorAll('input[type="file"]').forEach(function (input) {
                    var note = form.querySelector('[data-file-note="' + input.name + '"]');
                    var preview = form.querySelector('[data-file-preview="' + input.name + '"]');

                    input.addEventListener('change', function () {
                        var file = input.files && input.files[0];

                        if (note) note.textContent = '';
                        if (preview) { preview.classList.add('hidden'); preview.removeAttribute('src'); }
                        if (!file) return;

                        if (file.size > maxBytes) {
                            input.value = '';
                            if (note) note.textContent = form.dataset.tooLarge;
                            return;
                        }

                        if (preview && file.type.indexOf('image/') === 0) {
                            preview.src = URL.createObjectURL(file);
                            preview.classList.remove('hidden');
                        }
                    });
                });

                // One tap, one submission: a slow upload invites a second
                // tap, which would only be told "already registered".
                form.addEventListener('submit', function () {
                    submit.disabled = true;
                    submit.textContent = form.dataset.submitting;
                });

                // Coming back with the browser's back button must not leave
                // the button stuck on "Submitting…".
                window.addEventListener('pageshow', function () {
                    submit.disabled = false;
                    submit.textContent = submitLabel;
                });

                document.querySelectorAll('[data-copy]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        var source = document.querySelector(button.dataset.copy);
                        if (!source) return;

                        var label = button.textContent;
                        var done = function () {
                            button.textContent = button.dataset.copied;
                            setTimeout(function () { button.textContent = label; }, 1500);
                        };

                        if (navigator.clipboard && navigator.clipboard.writeText) {
                            navigator.clipboard.writeText(source.textContent.trim()).then(done);
                            return;
                        }

                        var range = document.createRange();
                        range.selectNodeContents(source);
                        var selection = window.getSelection();
                        selection.removeAllRanges();
                        selection.addRange(range);
                        try { document.execCommand('copy'); done(); } catch (error) {}
                        selection.removeAllRanges();
                    });
                });
            })();
        </script>
    @endif
@endsection
