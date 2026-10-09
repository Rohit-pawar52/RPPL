{{-- Shared by create.blade.php and edit.blade.php. $edition is null on create. --}}
@php
    $edition = $edition ?? null;
@endphp

<div class="space-y-4">
    <section class="ops-card">
        <div class="ops-card-head"><h3 class="ops-title">{{ __('The season') }}</h3></div>
        <div class="ops-card-body">
            <div class="grid gap-x-4 sm:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
                <x-form.input
                    name="name"
                    :label="__('Edition name')"
                    :value="$edition->name ?? ''"
                    :placeholder="__('e.g. RPPL 2027')"
                    required
                    autofocus
                />

                <x-form.input
                    name="year"
                    :label="__('Year')"
                    type="number"
                    :value="$edition->year ?? ''"
                    min="2000"
                    max="2100"
                    required
                />
            </div>

            <div class="grid gap-x-4 sm:grid-cols-2">
                <x-form.select
                    name="status"
                    :label="__('Status')"
                    :options="collect($statuses)->mapWithKeys(fn ($status) => [$status => ucfirst(__($status))])"
                    :value="$edition->status ?? 'upcoming'"
                    required
                />

                <x-form.select
                    name="registration_open"
                    :label="__('Public Registration')"
                    :options="['1' => __('Open'), '0' => __('Closed')]"
                    :value="old('registration_open', $edition->registration_open ?? false) ? '1' : '0'"
                    required
                />
            </div>

            <div class="sm:max-w-xs">
                <x-form.input
                    name="registration_fee"
                    :label="__('Registration Fee')"
                    type="number"
                    step="0.01"
                    min="0"
                    :value="$edition->registration_fee ?? ''"
                />
            </div>
            <p class="-mt-2.5 text-[11px] text-slate-400">{{ __('Required to open public registration. The payment QR code shown on the registration page is uploaded under Settings → Payments.') }}</p>
        </div>
    </section>

    {{--
        Optional registration window, entered in system.display_timezone.
        Both blank = the Public Registration switch above works exactly as
        before; when set, registration is only accepted inside the window.
    --}}
    <section class="ops-card">
        <div class="ops-card-head"><h3 class="ops-title">{{ __('Registration Period (optional)') }}</h3></div>
        <div class="ops-card-body">
            <div class="grid gap-x-4 sm:grid-cols-2">
                <x-form.input
                    name="registration_opens_at"
                    :label="__('Registration opens at')"
                    type="datetime-local"
                    :value="old('registration_opens_at', $edition?->registration_opens_at ? display_datetime($edition->registration_opens_at, 'Y-m-d\TH:i') : '')"
                />
                <x-form.input
                    name="registration_closes_at"
                    :label="__('Registration closes at')"
                    type="datetime-local"
                    :value="old('registration_closes_at', $edition?->registration_closes_at ? display_datetime($edition->registration_closes_at, 'Y-m-d\TH:i') : '')"
                />
            </div>
            <p class="-mt-1 text-[11px] text-slate-400">
                {{ __('Times are in the display timezone (:zone). Public Registration must also be set to Open.', ['zone' => app(\App\Services\Settings\SettingsService::class)->get('system.display_timezone')]) }}
            </p>

            @php
                $registrationReminderSent = $edition?->registration_reminder_dispatched_at !== null;
                $registrationReminderChecked = old('registration_reminder_enabled', $edition->registration_reminder_enabled ?? false);
            @endphp

            <div class="mt-5 border-t border-line pt-4">
                <p class="mb-2 text-xs font-semibold text-slate-700">{{ __('Closing Reminder') }}</p>

                @if($registrationReminderSent)
                    <p class="rounded-lg bg-slate-50 px-3 py-2 text-[12px] text-slate-500">
                        {{ __('The registration closing reminder for this edition has already been sent.') }}
                    </p>
                @else
                    <label class="flex min-h-10 items-center gap-2 text-[13px] font-medium text-slate-700">
                        <input
                            type="checkbox"
                            name="registration_reminder_enabled"
                            value="1"
                            {{ $registrationReminderChecked ? 'checked' : '' }}
                            class="h-4 w-4 rounded border-slate-300"
                        />
                        {{ __('Send a push reminder before registration closes') }}
                    </label>

                    <div class="mt-2 max-w-48">
                        <x-form.input
                            name="registration_reminder_minutes_before"
                            :label="__('Reminder before (minutes)')"
                            type="number"
                            min="1"
                            max="10080"
                            :value="old('registration_reminder_minutes_before', $edition->registration_reminder_minutes_before ?? 1440)"
                        />
                    </div>
                    <p class="-mt-2 text-[11px] text-slate-400">
                        {{ __('1440 minutes = 24 hours. Requires a closing time above.') }}
                    </p>
                @endif
            </div>
        </div>
    </section>
</div>
