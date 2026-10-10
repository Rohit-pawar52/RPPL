@extends('layouts.public')

@section('title', __('registration.titles.success').' · '.$branding->shortName)

{{--
    Shown once, right after a registration. The Registration Number on it is the key to the player's status, and the
    page is kept only in this browser's session: it can be gone after a refresh, Back, closing the browser or a long
    wait. So the page asks for a screenshot first, offers "Download as image" (a card drawn in the browser), and warns
    before the tab is left until the player says it is saved. The number can always be found again on the status page
    with the phone number.
    Expects: registration_number, edition_name, player_name, registration_fee, and (new) registered_at.
--}}
@section('content')
    @php
        $registeredAt = ! empty($registered_at ?? null) ? display_datetime(\Illuminate\Support\Carbon::parse($registered_at), 'd M Y, h:i A') : null;
    @endphp
    <div class="mx-auto max-w-xl space-y-4">
        {{-- First thing seen: take the screenshot. --}}
        <div id="reg-warning" class="flex items-start gap-3 rounded-2xl border-2 border-amber-400 bg-amber-50 p-4 text-amber-950 shadow-card" role="alert">
            <span class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-400 text-amber-950" aria-hidden="true">
                <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 8a2 2 0 012-2h1.5l1.2-1.8A1 1 0 019.5 4h5a1 1 0 01.8.2L16.5 6H18a2 2 0 012 2v9a2 2 0 01-2 2H6a2 2 0 01-2-2V8z" /><circle cx="12" cy="12.5" r="3.5" /></svg>
            </span>
            <div class="min-w-0">
                <p class="text-base font-bold leading-snug">{{ __('registration.success.warning_title') }}</p>
                <p class="mt-1 text-[13px] leading-relaxed">{{ __('registration.success.warning_text') }}</p>
            </div>
        </div>

        {{-- The card to screenshot: everything the player needs, with the league name and the date. --}}
        <div
            id="reg-card"
            class="pc-panel"
            data-league="{{ $branding->shortName }}"
            data-league-full="{{ $branding->applicationName }}"
            data-number="{{ $registration_number }}"
            data-player="{{ $player_name }}"
            data-edition="{{ $edition_name }}"
            data-fee="{{ $registration_fee !== null ? money($registration_fee) : '' }}"
            data-status="{{ __('registration.success.pending') }}"
            data-date="{{ $registeredAt }}"
            data-site="{{ request()->getHost() }}"
        >
            <div class="pc-hero rounded-none px-5 py-7 text-center shadow-none sm:px-8">
                <div class="pc-confetti" aria-hidden="true">
                    <span></span><span></span><span></span><span></span><span></span><span></span>
                    <span></span><span></span><span></span><span></span><span></span><span></span>
                </div>
                <span class="relative mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-accent-dark text-navy-950 shadow-pop ring-8 ring-white/10" aria-hidden="true">
                    <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M5 13l4 4L19 7" /></svg>
                </span>
                <h1 class="relative mt-4 text-2xl font-bold tracking-tight sm:text-3xl">{{ __('registration.success.heading') }}</h1>
                <p class="relative mt-1 text-sm text-slate-300">{{ $branding->shortName }} &middot; {{ $edition_name }}</p>
            </div>

            <div class="p-5 sm:p-7">
                <div class="rounded-xl border-2 border-dashed border-brand/50 bg-brand-soft px-4 py-5 text-center">
                    <p class="pc-eyebrow !text-brand">{{ __('registration.success.registration_number') }}</p>
                    <p id="registration-number" class="mt-1 whitespace-nowrap text-[clamp(1.25rem,6.4vw,2.25rem)] font-extrabold tracking-wide text-slate-900">{{ $registration_number }}</p>
                    <button
                        type="button"
                        data-copy="#registration-number"
                        data-copied="{{ __('registration.payment.copied') }}"
                        class="btn btn-secondary btn-sm mt-3"
                    >
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="11" height="11" rx="2" /><path d="M5 15V6a2 2 0 012-2h9" /></svg>
                        <span data-copy-label>{{ __('registration.payment.copy') }}</span>
                    </button>
                </div>

                <dl class="mt-5 grid grid-cols-2 gap-x-4 gap-y-4 rounded-xl border border-line bg-slate-50 p-4 text-left">
                    <div class="col-span-2">
                        <dt class="pc-eyebrow !text-slate-400">{{ __('registration.success.player_name') }}</dt>
                        <dd class="mt-0.5 break-words text-base font-semibold text-slate-900">{{ $player_name }}</dd>
                    </div>
                    <div>
                        <dt class="pc-eyebrow !text-slate-400">{{ __('registration.success.edition') }}</dt>
                        <dd class="mt-0.5 text-sm font-semibold text-slate-900">{{ $edition_name }}</dd>
                    </div>
                    @if($registration_fee !== null)
                        <div>
                            <dt class="pc-eyebrow !text-slate-400">{{ __('registration.success.registration_fee') }}</dt>
                            <dd class="mt-0.5 text-sm font-semibold tabular-nums text-slate-900">{{ money($registration_fee) }}</dd>
                        </div>
                    @endif
                    <div>
                        <dt class="pc-eyebrow !text-slate-400">{{ __('registration.success.payment_status') }}</dt>
                        <dd class="mt-1"><span class="pub-pill pub-pill-warn">{{ __('registration.success.pending') }}</span></dd>
                    </div>
                    @if($registeredAt)
                        <div>
                            <dt class="pc-eyebrow !text-slate-400">{{ __('registration.success.registered_on') }}</dt>
                            <dd class="mt-0.5 text-sm font-semibold text-slate-900">{{ $registeredAt }}</dd>
                        </div>
                    @endif
                </dl>

                <p class="mt-3 text-center text-xs font-medium text-red-600">{{ __('registration.success.save_notice') }}</p>
            </div>
        </div>

        {{-- Keep it: a picture first, the screenshot is the fallback. --}}
        <div class="pc-panel p-4 sm:p-5">
            <button type="button" id="reg-download" class="btn btn-primary btn-lg w-full">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4v11m0 0l-4-4m4 4l4-4M5 19h14" /></svg>
                {{ __('registration.success.download_image') }}
            </button>
            <button type="button" id="reg-saved" class="btn btn-secondary btn-lg mt-2 w-full">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7" /></svg>
                <span data-label>{{ __('registration.success.saved_it') }}</span>
            </button>
            <p id="reg-saved-note" class="mt-2 hidden text-center text-xs font-semibold text-green-700" role="status">{{ __('registration.success.saved_ok') }}</p>
        </div>

        {{-- What happens now. --}}
        <div class="pc-panel p-5 sm:p-7">
            <h2 class="text-sm font-semibold text-slate-900">{{ __('registration.success.next_title') }}</h2>
            <ol class="mt-3 space-y-3">
                <li class="flex items-start gap-3">
                    <span class="pc-section-no" aria-hidden="true">1</span>
                    <p class="text-[13px] leading-relaxed text-slate-600">{{ __('registration.success.verification_notice', ['league' => $branding->shortName]) }}</p>
                </li>
                <li class="flex items-start gap-3">
                    <span class="pc-section-no" aria-hidden="true">2</span>
                    <p class="text-[13px] leading-relaxed text-slate-600">{{ __('registration.success.next_confirmed') }}</p>
                </li>
            </ol>

            <p class="mt-6 text-center text-xs text-slate-500">{{ __('registration.success.return_notice') }}</p>
            <div class="mt-3 flex flex-col gap-2 sm:flex-row">
                <a href="{{ route('public.player-registration.status') }}" class="btn btn-primary btn-lg flex-1">{{ __('registration.form.check_status_link') }}</a>
                <a href="{{ route('public.home') }}" class="btn btn-secondary btn-lg flex-1">&larr; {{ __('registration.success.back_home', ['league' => $branding->shortName]) }}</a>
            </div>
        </div>
    </div>

    <script>
        (function () {
            // Until the player says it is saved (or downloads the picture), refresh / Back / closing asks first.
            var unsaved = true;
            window.addEventListener('beforeunload', function (event) {
                if (!unsaved) return;
                event.preventDefault();
                event.returnValue = '';
            });

            var markSaved = function () {
                unsaved = false;
                var button = document.getElementById('reg-saved');
                button.classList.add('opacity-60');
                button.querySelector('[data-label]').textContent = {{ Illuminate\Support\Js::from(__('registration.success.saved_done')) }};
                document.getElementById('reg-saved-note').classList.remove('hidden');
                var warning = document.getElementById('reg-warning');
                warning.classList.remove('border-amber-400', 'bg-amber-50', 'text-amber-950');
                warning.classList.add('border-green-300', 'bg-green-50', 'text-green-900');
            };
            document.getElementById('reg-saved').addEventListener('click', markSaved);

            document.querySelectorAll('[data-copy]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var source = document.querySelector(button.dataset.copy);
                    if (!source) return;

                    var target = button.querySelector('[data-copy-label]') || button;
                    var label = target.textContent;
                    var done = function () {
                        target.textContent = button.dataset.copied;
                        setTimeout(function () { target.textContent = label; }, 1500);
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

            // "Download as image": the same details drawn on a canvas, saved as a PNG (works without any library).
            var roundRect = function (ctx, x, y, w, h, r) {
                ctx.beginPath();
                ctx.moveTo(x + r, y);
                ctx.arcTo(x + w, y, x + w, y + h, r);
                ctx.arcTo(x + w, y + h, x, y + h, r);
                ctx.arcTo(x, y + h, x, y, r);
                ctx.arcTo(x, y, x + w, y, r);
                ctx.closePath();
            };

            var drawCard = function (card) {
                var data = card.dataset;
                var styles = getComputedStyle(document.documentElement);
                var header = (styles.getPropertyValue('--rppl-header') || '#0b2e3f').trim();
                var brand = (styles.getPropertyValue('--rppl-primary') || '#15803d').trim();
                var font = 'system-ui, -apple-system, "Segoe UI", "Noto Sans Devanagari", "Nirmala UI", sans-serif';
                var W = 900, H = 700, scale = 2;
                var canvas = document.createElement('canvas');
                canvas.width = W * scale;
                canvas.height = H * scale;
                var ctx = canvas.getContext('2d');
                ctx.scale(scale, scale);

                ctx.fillStyle = '#f1f5f9';
                ctx.fillRect(0, 0, W, H);

                roundRect(ctx, 30, 30, W - 60, H - 60, 24);
                ctx.fillStyle = '#ffffff';
                ctx.fill();

                ctx.save();
                roundRect(ctx, 30, 30, W - 60, H - 60, 24);
                ctx.clip();
                ctx.fillStyle = header;
                ctx.fillRect(30, 30, W - 60, 130);
                ctx.restore();

                ctx.textAlign = 'center';
                ctx.fillStyle = '#ffffff';
                ctx.font = 'bold 34px ' + font;
                ctx.fillText(data.leagueFull || data.league, W / 2, 90);
                ctx.fillStyle = '#cbd5e1';
                ctx.font = '600 22px ' + font;
                ctx.fillText({{ Illuminate\Support\Js::from(__('registration.success.heading')) }} + ' · ' + data.edition, W / 2, 130);

                roundRect(ctx, 70, 190, W - 140, 150, 16);
                ctx.fillStyle = '#f0fdf4';
                ctx.fill();
                ctx.setLineDash([10, 8]);
                ctx.lineWidth = 2.5;
                ctx.strokeStyle = brand;
                ctx.stroke();
                ctx.setLineDash([]);

                ctx.fillStyle = brand;
                ctx.font = '700 18px ' + font;
                ctx.fillText({{ Illuminate\Support\Js::from(mb_strtoupper(__('registration.success.registration_number'))) }}, W / 2, 228);

                var size = 64;
                ctx.fillStyle = '#0f172a';
                do {
                    ctx.font = '800 ' + size + 'px ' + font;
                    size -= 2;
                } while (ctx.measureText(data.number).width > W - 180 && size > 24);
                ctx.fillText(data.number, W / 2, 305);

                var rows = [
                    [{{ Illuminate\Support\Js::from(__('registration.success.player_name')) }}, data.player],
                    [{{ Illuminate\Support\Js::from(__('registration.success.edition')) }}, data.edition],
                    [{{ Illuminate\Support\Js::from(__('registration.success.payment_status')) }}, data.status],
                ];
                if (data.fee) rows.push([{{ Illuminate\Support\Js::from(__('registration.success.registration_fee')) }}, data.fee]);
                if (data.date) rows.push([{{ Illuminate\Support\Js::from(__('registration.success.registered_on')) }}, data.date]);

                var y = 392;
                ctx.textAlign = 'left';
                rows.forEach(function (row) {
                    ctx.fillStyle = '#64748b';
                    ctx.font = '600 19px ' + font;
                    ctx.fillText(row[0], 80, y);
                    ctx.fillStyle = '#0f172a';
                    ctx.textAlign = 'right';
                    ctx.font = '700 22px ' + font;
                    ctx.fillText(row[1], W - 80, y);
                    ctx.textAlign = 'left';
                    ctx.strokeStyle = '#e2e8f0';
                    ctx.lineWidth = 1;
                    ctx.beginPath();
                    ctx.moveTo(80, y + 14);
                    ctx.lineTo(W - 80, y + 14);
                    ctx.stroke();
                    y += 48;
                });

                ctx.textAlign = 'center';
                ctx.fillStyle = '#b91c1c';
                ctx.font = '600 18px ' + font;
                ctx.fillText({{ Illuminate\Support\Js::from(__('registration.success.image_footer')) }}, W / 2, H - 70);
                ctx.fillStyle = '#94a3b8';
                ctx.font = '500 16px ' + font;
                ctx.fillText(data.site, W / 2, H - 46);

                return canvas;
            };

            document.getElementById('reg-download').addEventListener('click', function () {
                var card = document.getElementById('reg-card');
                var canvas = drawCard(card);
                canvas.toBlob(function (blob) {
                    if (!blob) return;
                    var link = document.createElement('a');
                    link.href = URL.createObjectURL(blob);
                    link.download = 'registration-' + card.dataset.number + '.png';
                    document.body.appendChild(link);
                    link.click();
                    link.remove();
                    setTimeout(function () { URL.revokeObjectURL(link.href); }, 2000);
                    markSaved();
                }, 'image/png');
            });
        })();
    </script>
@endsection
