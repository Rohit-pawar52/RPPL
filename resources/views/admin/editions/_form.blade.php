{{-- Shared by create.blade.php and edit.blade.php. $edition is null on create. --}}
@php
    $edition = $edition ?? null;
@endphp

<div class="grid gap-x-4 sm:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
    <x-form.input
        name="name"
        label="Edition name"
        :value="$edition->name ?? ''"
        placeholder="e.g. RPPL 2027"
        required
        autofocus
    />

    <x-form.input
        name="year"
        label="Year"
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
        label="Status"
        :options="collect($statuses)->mapWithKeys(fn ($status) => [$status => ucfirst($status)])"
        :value="$edition->status ?? 'upcoming'"
        required
    />

    <x-form.select
        name="registration_open"
        label="Public Registration"
        :options="['1' => 'Open', '0' => 'Closed']"
        :value="old('registration_open', $edition->registration_open ?? false) ? '1' : '0'"
        required
    />
</div>

<div class="sm:max-w-xs">
    <x-form.input
        name="registration_fee"
        label="Registration Fee"
        type="number"
        step="0.01"
        min="0"
        :value="$edition->registration_fee ?? ''"
    />
</div>
<p class="-mt-2.5 mb-3.5 text-[11px] text-slate-400">Required to open public registration. The payment QR code shown on the registration page is uploaded under Settings → Payments.</p>

{{--
    Optional registration window, entered in system.display_timezone.
    Both blank = the Public Registration switch above works exactly as
    before; when set, registration is only accepted inside the window.
--}}
<div class="mt-4 border-t border-slate-100 pt-3.5">
    <p class="mb-2 text-xs font-medium text-slate-700">Registration Period (optional)</p>

    <div class="grid gap-x-4 sm:grid-cols-2">
        <x-form.input
            name="registration_opens_at"
            label="Registration opens at"
            type="datetime-local"
            :value="old('registration_opens_at', $edition?->registration_opens_at ? display_datetime($edition->registration_opens_at, 'Y-m-d\TH:i') : '')"
        />
        <x-form.input
            name="registration_closes_at"
            label="Registration closes at"
            type="datetime-local"
            :value="old('registration_closes_at', $edition?->registration_closes_at ? display_datetime($edition->registration_closes_at, 'Y-m-d\TH:i') : '')"
        />
    </div>
    <p class="mt-1 text-[11px] text-slate-400">
        Times are in the display timezone ({{ app(\App\Services\Settings\SettingsService::class)->get('system.display_timezone') }}). Public Registration must also be set to Open.
    </p>

    @php
        $registrationReminderSent = $edition?->registration_reminder_dispatched_at !== null;
        $registrationReminderChecked = old('registration_reminder_enabled', $edition->registration_reminder_enabled ?? false);
    @endphp

    <p class="mb-2 mt-4 text-xs font-medium text-slate-700">Closing Reminder</p>

    @if($registrationReminderSent)
        <p class="rounded-md bg-slate-50 px-3 py-2 text-[11px] text-slate-500">
            The registration closing reminder for this edition has already been sent.
        </p>
    @else
        <label class="flex items-center gap-2 text-[13px] text-slate-700">
            <input
                type="checkbox"
                name="registration_reminder_enabled"
                value="1"
                {{ $registrationReminderChecked ? 'checked' : '' }}
                class="focus:border-green-500 focus:ring-green-100"
            />
            Send a push reminder before registration closes
        </label>

        <div class="mt-2 max-w-40">
            <x-form.input
                name="registration_reminder_minutes_before"
                label="Reminder before (minutes)"
                type="number"
                min="1"
                max="10080"
                :value="old('registration_reminder_minutes_before', $edition->registration_reminder_minutes_before ?? 1440)"
            />
        </div>
        <p class="mt-1 text-[11px] text-slate-400">
            1440 minutes = 24 hours. Requires a closing time above.
        </p>
    @endif
</div>
