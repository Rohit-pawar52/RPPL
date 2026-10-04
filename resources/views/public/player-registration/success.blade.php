@extends('layouts.public')

@section('title', __('registration.titles.success').' · '.$branding->shortName)

@section('content')
    <div class="mx-auto max-w-xl">
        <div class="overflow-hidden rounded-2xl border border-line bg-white shadow-sm">
            {{-- The good news first. --}}
            <div class="relative overflow-hidden bg-navy-900 px-5 py-7 text-center text-white sm:px-8">
                <div class="pointer-events-none absolute -right-16 -top-20 h-52 w-52 rounded-full bg-green-500/25 blur-3xl" aria-hidden="true"></div>
                <span class="relative mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-green-500 text-navy-950 shadow-lg shadow-green-500/30" aria-hidden="true">
                    <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7" /></svg>
                </span>
                <h1 class="relative mt-3 text-2xl font-bold tracking-tight">{{ __('registration.success.heading') }}</h1>
                <p class="relative mt-1 text-sm text-slate-300">{{ $player_name }} &middot; {{ $edition_name }}</p>
            </div>

            <div class="p-5 sm:p-7">
                {{-- The number to keep. --}}
                <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-4 text-center">
                    <p class="pub-eyebrow !text-green-700">{{ __('registration.success.registration_number') }}</p>
                    <p id="registration-number" class="mt-1 break-all text-2xl font-bold tracking-wide text-slate-900">{{ $registration_number }}</p>
                    <button
                        type="button"
                        data-copy="#registration-number"
                        data-copied="{{ __('registration.payment.copied') }}"
                        class="pub-btn-outline pub-btn-sm mt-3"
                    >{{ __('registration.payment.copy') }}</button>
                </div>
                <p class="mt-2.5 text-center text-xs font-medium text-red-600">{{ __('registration.success.save_notice') }}</p>

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

                {{-- What happens now. --}}
                <div class="mt-6">
                    <h2 class="text-sm font-semibold text-slate-900">{{ __('registration.success.next_title') }}</h2>
                    <ol class="mt-3 space-y-3">
                        <li class="flex items-start gap-3">
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-navy-900 text-[11px] font-bold text-white" aria-hidden="true">1</span>
                            <p class="text-[13px] leading-relaxed text-slate-600">{{ __('registration.success.verification_notice', ['league' => $branding->shortName]) }}</p>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-navy-900 text-[11px] font-bold text-white" aria-hidden="true">2</span>
                            <p class="text-[13px] leading-relaxed text-slate-600">{{ __('registration.success.next_confirmed') }}</p>
                        </li>
                    </ol>
                </div>

                <p class="mt-6 text-center text-xs text-slate-500">{{ __('registration.success.return_notice') }}</p>
                <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                    <a href="{{ route('public.player-registration.status') }}" class="pub-btn h-11 flex-1 text-sm">{{ __('registration.form.check_status_link') }}</a>
                    <a href="{{ route('public.home') }}" class="pub-btn-outline h-11 flex-1 text-sm">&larr; {{ __('registration.success.back_home', ['league' => $branding->shortName]) }}</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
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
@endsection
