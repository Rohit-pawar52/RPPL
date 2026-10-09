@extends('layouts.public')

@section('title', __('registration.titles.registration').' · '.$branding->shortName)

@section('content')
    @if(! $edition)
        <div class="pc-empty mx-auto max-w-lg bg-white !py-10">
            <span class="pc-empty-icon"><x-icon name="calendar" class="h-7 w-7" /></span>
            <h1 class="pub-h1">{{ __('registration.form.heading') }}</h1>
            @if($upcomingEdition ?? null)
                <p class="text-sm text-slate-600">{{ __('registration.window.not_yet_open') }}</p>
                <p class="mt-1 inline-block rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-800 ring-1 ring-inset ring-amber-200">{{ __('registration.window.opens_on', ['date' => display_datetime($upcomingEdition->registration_opens_at, 'd M Y, h:i A')]) }}</p>
            @else
                <p class="text-sm text-slate-600">{{ __('registration.closed.message') }}</p>
                <p class="pub-meta">{{ __('registration.closed.contact', ['league' => $branding->shortName]) }}</p>
            @endif
            <div class="mt-4 flex w-full flex-col gap-2 border-t border-line pt-5 sm:w-auto sm:flex-row">
                <a href="{{ route('public.player-registration.status') }}" class="btn btn-primary">{{ __('registration.form.check_status_link') }}</a>
                <a href="{{ route('public.home') }}" class="btn btn-secondary">{{ __('ux_public_content.news.go_home') }}</a>
            </div>
        </div>
    @else
        @php
            $maxSize = \App\Support\UploadLimits::megabytes($maxFileKb);
            $hasPaymentDetails = $payment['upi_id'] || $payment['qr_url'];
            $steps = [
                ['key' => 'fill', 'icon' => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM4 20a8 8 0 0116 0'],
                ['key' => 'pay', 'icon' => 'M3 7.5A2.5 2.5 0 015.5 5h13A2.5 2.5 0 0121 7.5v9a2.5 2.5 0 01-2.5 2.5h-13A2.5 2.5 0 013 16.5v-9zM3 10h18M16 14.5h2'],
                ['key' => 'upload', 'icon' => 'M12 16V5m0 0l-4 4m4-4l4 4M5 19h14'],
                ['key' => 'confirm', 'icon' => 'M5 13l4 4L19 7'],
            ];
        @endphp

        <div class="mx-auto max-w-5xl">
            {{-- The page's opening: what this is, what it costs, and how it works. --}}
            <section class="pc-hero" aria-labelledby="registration-title">
                <div class="px-4 py-4 sm:px-6 sm:py-5">
                    <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-3">
                        <div class="min-w-0">
                            <p class="pc-eyebrow">{{ $edition->name }}</p>
                            <h1 id="registration-title" class="mt-0.5 text-2xl font-bold leading-tight tracking-tight sm:text-3xl">{{ __('registration.form.heading') }}</h1>
                            <p class="mt-1 text-xs leading-relaxed text-slate-300">
                                @if($edition->registration_closes_at)
                                    {{ __('registration.window.closes_on', ['date' => display_datetime($edition->registration_closes_at, 'd M Y, h:i A')]) }} &middot;
                                @endif
                                {{ __('registration.form.already_registered') }}
                                <a href="{{ route('public.player-registration.status') }}" class="font-semibold text-accent-dark underline-offset-2 hover:underline">{{ __('registration.form.check_status_link') }}</a>
                            </p>
                        </div>

                        <div class="rounded-xl bg-white/10 px-4 py-2 text-right ring-1 ring-white/15">
                            <p class="text-[10px] font-medium uppercase tracking-wide text-slate-300">{{ __('registration.form.fee_title') }}</p>
                            <p class="text-xl font-bold leading-tight tracking-tight">{{ money($edition->registration_fee) }}</p>
                        </div>
                    </div>

                    {{-- The four steps, on a screen wide enough for them. --}}
                    <ol class="mt-4 hidden grid-cols-4 gap-2 sm:grid" aria-label="{{ __('registration.form.steps.heading') }}">
                        @foreach($steps as $step)
                            <li class="flex items-center gap-2 rounded-lg bg-white/5 px-2.5 py-2 ring-1 ring-white/10">
                                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-accent-dark text-navy-950" aria-hidden="true">
                                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="{{ $step['icon'] }}" /></svg>
                                </span>
                                <span class="min-w-0 text-xs font-semibold leading-tight">{{ __('registration.form.steps.'.$step['key']) }}</span>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </section>

            {{-- After a refused submission: say so up top and take the visitor there. --}}
            @if($errors->any())
                <div id="registration-errors" tabindex="-1" role="alert" class="mt-4 rounded-xl border border-red-200 bg-red-50 p-3 text-[13px] text-red-800 focus:outline-none sm:px-4">
                    <p class="flex items-center gap-2 font-semibold">
                        <svg class="h-4 w-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.3 3.4a2 2 0 013.4 0l6 10.5A2 2 0 0116 17H4a2 2 0 01-1.7-3.1l6-10.5zM10 7a1 1 0 00-1 1v3a1 1 0 102 0V8a1 1 0 00-1-1zm0 8a1.1 1.1 0 100-2.2 1.1 1.1 0 000 2.2z" clip-rule="evenodd" /></svg>
                        {{ __('registration.form.errors_title') }}
                    </p>
                    <ul class="mt-1.5 list-disc space-y-0.5 pl-8">
                        @foreach($errors->messages() as $field => $messages)
                            <li><a href="#{{ $field }}" class="font-medium underline underline-offset-2">{{ $messages[0] }}</a></li>
                        @endforeach
                    </ul>
                    <p class="mt-1.5 pl-8 text-xs text-red-700">{{ __('registration.form.errors_files') }}</p>
                </div>
            @endif

            <form
                id="player-registration-form"
                method="POST"
                action="{{ route('public.player-registration.store') }}"
                enctype="multipart/form-data"
                class="mt-4 grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_20rem]"
                data-submitting="{{ __('registration.form.submitting') }}"
                data-progress-text="{{ __('registration.form.progress', ['done' => ':done', 'total' => ':total']) }}"
                data-progress-ready="{{ __('registration.form.ready') }}"
            >
                @csrf

                {{-- 1 · About you: who, where, and a photo --}}
                <section class="pc-section lg:col-start-1">
                    <header class="pc-section-head">
                        <span class="pc-section-no" aria-hidden="true">1</span>
                        <h2 class="text-sm font-semibold tracking-tight text-slate-900">{{ __('registration.form.sections.you') }}</h2>
                        <p class="text-[11px] text-slate-500">{{ __('registration.form.required_note') }}</p>
                    </header>
                    <div class="px-4 pt-4">
                        <div class="grid grid-cols-2 gap-x-3 sm:gap-x-4">
                            <div class="col-span-2 sm:col-span-1">
                                @include('public.player-registration._field', ['name' => 'name', 'label' => __('registration.fields.name'), 'required' => true, 'autocomplete' => 'name'])
                            </div>
                            <div class="col-span-2 sm:col-span-1">
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
                            <div>
                                @include('public.player-registration._field', [
                                    'name' => 'age',
                                    'label' => __('registration.fields.age'),
                                    'type' => 'number',
                                    'inputmode' => 'numeric',
                                    'min' => \App\Models\PlayerRegistration::AGE_MIN,
                                    'max' => \App\Models\PlayerRegistration::AGE_MAX,
                                    'placeholder' => __('registration.fields.age_placeholder'),
                                ])
                            </div>
                            <div>
                                @include('public.player-registration._field', ['name' => 'email', 'label' => __('registration.fields.email'), 'type' => 'email', 'autocomplete' => 'email'])
                            </div>
                        </div>
                        <p class="-mt-1.5 mb-3 text-[11px] text-slate-500">{{ __('registration.form.section_hints.you') }}</p>

                        <p class="pc-eyebrow mb-2 mt-1 border-t border-line pt-3">{{ __('registration.form.sections.address') }}</p>
                        <div class="grid grid-cols-2 gap-x-3 sm:grid-cols-3 sm:gap-x-4">
                            @include('public.player-registration._field', ['name' => 'village', 'label' => __('registration.fields.village'), 'maxlength' => 100, 'required' => true])
                            @include('public.player-registration._field', ['name' => 'tehsil', 'label' => __('registration.fields.tehsil'), 'maxlength' => 100, 'required' => true])
                            <div class="col-span-2 sm:col-span-1">
                                @include('public.player-registration._field', ['name' => 'district', 'label' => __('registration.fields.district'), 'maxlength' => 100, 'required' => true])
                            </div>
                        </div>

                        @include('public.player-registration._upload', [
                            'name' => 'photo',
                            'label' => __('registration.fields.photo'),
                            'accept' => 'image/jpeg,image/png,image/webp',
                            'hint' => __('registration.fields.photo_hint', ['size' => $maxSize]),
                            'emptyText' => __('registration.upload.choose_photo'),
                            'kind' => 'user',
                            'maxBytes' => $maxFileKb * 1024,
                            'tooLarge' => __('registration.js.too_large', ['size' => $maxSize]),
                        ])
                    </div>
                </section>

                {{-- 2 · Playing details --}}
                <section class="pc-section lg:col-start-1">
                    <header class="pc-section-head">
                        <span class="pc-section-no" aria-hidden="true">2</span>
                        <h2 class="text-sm font-semibold tracking-tight text-slate-900">{{ __('registration.form.sections.playing') }}</h2>
                        <p class="text-[11px] text-slate-500">{{ __('registration.form.section_hints.playing') }}</p>
                    </header>
                    <div class="px-4 pt-4">
                        @include('public.player-registration._choice', [
                            'name' => 'primary_role',
                            'label' => __('registration.fields.player_type'),
                            'required' => true,
                            'options' => collect(\App\Models\Player::PRIMARY_ROLES)->mapWithKeys(fn ($role) => [$role => __('registration.roles.'.$role)])->all(),
                        ])
                        <div class="flex flex-wrap gap-x-8">
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
                        </div>
                    </div>
                </section>

                {{-- The payment: beside the form on a large screen (so the QR code is there from the start), between the details and the proof on a phone. --}}
                <aside class="pc-panel lg:sticky lg:top-20 lg:col-start-2 lg:row-span-4 lg:row-start-1" aria-labelledby="payment-title">
                    <div class="relative flex items-end justify-between gap-3 overflow-hidden bg-linear-to-br from-navy-900 to-navy-800 px-4 py-3.5 text-white">
                        <div class="pointer-events-none absolute -right-8 -top-10 h-28 w-28 rounded-full bg-brand/40 blur-2xl" aria-hidden="true"></div>
                        <div class="relative">
                            <p id="payment-title" class="text-[10px] font-semibold uppercase tracking-wider text-accent-dark">{{ __('registration.payment.title') }}</p>
                            <p class="text-[11px] text-slate-300">{{ __('registration.payment.amount') }}</p>
                        </div>
                        <p class="relative text-2xl font-bold leading-none tracking-tight tabular-nums">{{ money($edition->registration_fee) }}</p>
                    </div>

                    <div class="space-y-3 p-4">
                        <p class="rounded-lg bg-amber-50 px-3 py-2 text-[11px] leading-snug text-amber-900 ring-1 ring-inset ring-amber-200">
                            {{ __($hasPaymentDetails ? 'registration.form.payment_instructions_upi' : 'registration.form.payment_instructions', ['league' => $branding->shortName]) }}
                        </p>

                        @if($payment['qr_url'])
                            <div class="text-center">
                                <p class="mb-1.5 text-[11px] font-medium text-slate-600">{{ __('registration.payment.scan') }}</p>
                                <img src="{{ $payment['qr_url'] }}" alt="UPI QR" data-no-fallback onerror="this.parentElement.remove()" class="mx-auto h-44 w-44 max-w-full rounded-xl border border-line bg-white object-contain p-1.5 shadow-card" />
                            </div>
                        @endif

                        @if($payment['upi_id'])
                            <div class="flex items-center justify-between gap-2 rounded-lg bg-slate-50 px-3 py-2 ring-1 ring-inset ring-slate-200">
                                <div class="min-w-0">
                                    <p class="text-[10px] text-slate-500">{{ __('registration.payment.upi_id') }}</p>
                                    <p id="upi-id" class="break-all text-[13px] font-semibold leading-tight text-slate-900">{{ $payment['upi_id'] }}</p>
                                </div>
                                <button
                                    type="button"
                                    data-copy="#upi-id"
                                    data-copied="{{ __('registration.payment.copied') }}"
                                    class="btn btn-secondary btn-sm shrink-0"
                                >{{ __('registration.payment.copy') }}</button>
                            </div>

                            <div class="sm:hidden">
                                <a href="{{ $payment['pay_link'] }}" class="btn btn-primary btn-lg btn-block">{{ __('registration.payment.pay_with_app') }}</a>
                                <p class="pub-meta mt-1 text-[11px]">{{ __('registration.payment.pay_with_app_hint') }}</p>
                            </div>
                        @endif

                        <p class="flex items-start gap-1.5 text-[11px] leading-snug text-slate-500">
                            <svg class="mt-px h-3.5 w-3.5 shrink-0 text-brand" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" /></svg>
                            {{ __('registration.payment.after_paying') }}
                        </p>
                    </div>
                </aside>

                {{-- 3 · Payment proof --}}
                <section class="pc-section lg:col-start-1">
                    <header class="pc-section-head">
                        <span class="pc-section-no" aria-hidden="true">3</span>
                        <h2 class="text-sm font-semibold tracking-tight text-slate-900">{{ __('registration.form.sections.proof') }}</h2>
                        <p class="text-[11px] text-slate-500">{{ __('registration.form.section_hints.proof') }}</p>
                    </header>
                    <div class="px-4 pt-4">
                        <div class="grid grid-cols-1 gap-x-4 sm:grid-cols-2">
                            @include('public.player-registration._field', [
                                'name' => 'submitted_utr',
                                'label' => __('registration.fields.submitted_utr'),
                                'hint' => __('registration.fields.submitted_utr_hint'),
                                'autocomplete' => 'off',
                                'maxlength' => 40,
                            ])
                            @include('public.player-registration._upload', [
                                'name' => 'payment_proof',
                                'label' => __('registration.fields.payment_proof'),
                                'accept' => 'image/jpeg,image/png',
                                'hint' => __('registration.fields.payment_proof_hint', ['size' => $maxSize]),
                                'emptyText' => __('registration.upload.choose_proof'),
                                'kind' => 'image',
                                'maxBytes' => $maxFileKb * 1024,
                                'tooLarge' => __('registration.js.too_large', ['size' => $maxSize]),
                            ])
                        </div>
                        <p class="-mt-1 mb-3.5 flex items-start gap-1.5 text-[11px] leading-snug text-slate-500">
                            <svg class="mt-px h-3.5 w-3.5 shrink-0 text-slate-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" /></svg>
                            {{ __('registration.form.private_note', ['league' => $branding->shortName]) }}
                        </p>
                    </div>
                </section>

                {{-- Submit: stays at the bottom of the screen until the end of the form is reached. --}}
                <div class="pc-submitbar lg:col-start-1">
                    <div class="flex items-center gap-3">
                        <div class="min-w-0 flex-1">
                            <p data-progress-label class="text-xs font-medium leading-tight text-slate-600"></p>
                            <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-slate-100" aria-hidden="true">
                                <div data-progress-bar class="h-full w-0 rounded-full bg-brand transition-all duration-300"></div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary btn-lg shrink-0">
                            {{ __('registration.form.submit') }}
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <script>
            (function () {
                var form = document.getElementById('player-registration-form');
                if (!form) return;

                var submit = form.querySelector('button[type="submit"]');
                var submitLabel = submit.textContent;

                // After a refused submission, take the visitor to the reason;
                // on a large screen a fresh form starts in the first field
                // (not on a phone, where the keyboard would hide the page).
                var summary = document.getElementById('registration-errors');
                if (summary) {
                    summary.scrollIntoView({ block: 'center' });
                    summary.focus({ preventScroll: true });
                } else if (window.matchMedia('(min-width: 1024px)').matches) {
                    var first = document.getElementById('name');
                    if (first) { first.focus({ preventScroll: true }); }
                }

                // "3 of 9 required fields done", with a bar.
                var required = {};
                form.querySelectorAll('[required]').forEach(function (field) {
                    (required[field.name] = required[field.name] || []).push(field);
                });
                var names = Object.keys(required);
                var bar = form.querySelector('[data-progress-bar]');
                var label = form.querySelector('[data-progress-label]');

                var isDone = function (fields) {
                    return fields.some(function (field) {
                        if (field.type === 'radio' || field.type === 'checkbox') return field.checked;
                        if (field.type === 'file') return field.files && field.files.length > 0;
                        return field.value.trim() !== '';
                    });
                };

                var updateProgress = function () {
                    var done = names.filter(function (name) { return isDone(required[name]); }).length;
                    bar.style.width = (names.length ? Math.round(done / names.length * 100) : 0) + '%';
                    label.textContent = done === names.length
                        ? form.dataset.progressReady
                        : form.dataset.progressText.replace(':done', done).replace(':total', names.length);
                };

                form.addEventListener('input', updateProgress);
                form.addEventListener('change', updateProgress);
                updateProgress();

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
                    updateProgress();
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
