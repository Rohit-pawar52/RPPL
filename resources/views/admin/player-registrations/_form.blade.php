{{-- Shared by create.blade.php and edit.blade.php. $registration is null on create.
     edition_id/player_id are immutable once a registration exists, so they only
     render as selects on create; on edit they show read-only. --}}
@php
    $registration = $registration ?? null;
@endphp

<div class="grid gap-4 sm:grid-cols-2">
    @if($registration)
        <div>
            <p class="mb-1 text-xs font-medium text-slate-700">Player</p>
            <p class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-[13px] text-slate-700">
                {{ $registration->player->name }}
                @unless($registration->player->is_active)
                    <span class="text-slate-400">(inactive)</span>
                @endunless
            </p>
        </div>
        <div>
            <p class="mb-1 text-xs font-medium text-slate-700">Edition</p>
            <p class="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-[13px] text-slate-700">
                {{ $registration->edition->name }}
            </p>
        </div>
    @else
        <x-form.select
            name="player_id"
            label="Player"
            placeholder="Select a player"
            :options="$players->pluck('name', 'id')"
            required
        />
        <x-form.select
            name="edition_id"
            label="Edition"
            placeholder="Select an edition"
            :options="$editions->pluck('name', 'id')"
            required
        />
    @endif
</div>

<div class="mt-4 grid gap-4 sm:grid-cols-3">
    <x-form.select
        name="payment_status"
        label="Payment status"
        :options="collect($paymentStatuses)->mapWithKeys(fn ($status) => [$status => ucfirst($status)])"
        :value="$registration->payment_status ?? 'pending'"
        required
    />
    <x-form.input
        name="registration_fee"
        label="Registration fee"
        type="number"
        step="0.01"
        min="0"
        :value="$registration->registration_fee ?? ''"
    />
    <x-form.input
        name="registered_at"
        label="Registered at"
        type="datetime-local"
        :value="$registration ? display_datetime($registration->registered_at, 'Y-m-d\TH:i') : display_datetime(now(), 'Y-m-d\TH:i')"
        :help="'Time in '.app(\App\Services\Settings\SettingsService::class)->get('system.display_timezone').'.'"
    />
</div>

@if($registration)
    {{-- Payment verification only applies to an existing registration;
         identity fields (edition/player) above are already read-only
         here, and document paths are never editable at all (see Phase
         3.39D — replacement/deletion is deliberately out of scope). --}}
    <div class="mt-4 grid gap-4 sm:grid-cols-2">
        <x-form.input
            name="payment_failure_reason"
            label="Failure reason"
            maxlength="255"
            :value="$registration->payment_failure_reason"
            placeholder="Reason shown to the player"
            help="Only kept while the payment status is Failed; cleared on save otherwise."
        />
        <x-form.input
            name="payment_reference"
            label="Payment reference / UTR (optional)"
            :value="old('payment_reference', $registration->payment_reference)"
            placeholder="e.g. UTR or transaction ID"
        />
    </div>

    {{-- What the player answered on the registration form. A Google Form CSV
         import fills these in; they stay editable so an imported registration
         can be corrected or completed later. Role and batting hand live on the
         player (Players → Edit). --}}
    <div class="mt-5 border-t border-slate-100 pt-4">
        <p class="text-xs font-medium text-slate-700">Details from the registration form (optional)</p>
        <p class="mb-3 mt-0.5 text-[11px] text-slate-400">
            Filled in automatically on the public form and when a sheet is imported. Role and batting hand are on the player
            (Players &rarr; Edit).
        </p>
        <div class="grid gap-x-4 sm:grid-cols-3">
            <x-form.input
                name="age"
                label="Age"
                type="number"
                :min="\App\Models\PlayerRegistration::AGE_MIN"
                :max="\App\Models\PlayerRegistration::AGE_MAX"
                :value="$registration->age"
            />
            <x-form.input name="village" label="Village (Gram)" maxlength="100" :value="$registration->village" />
            <x-form.input name="tehsil" label="Tehsil" maxlength="100" :value="$registration->tehsil" />
            <x-form.input name="district" label="District" maxlength="100" :value="$registration->district" />
            <x-form.input
                name="submitted_utr"
                label="UTR typed by the player"
                maxlength="100"
                :value="$registration->submitted_utr"
            />
        </div>
        {{-- The Google Drive links only exist on registrations that came from an
             imported sheet; for everything else (the public form uploads files
             directly) they would just be two empty, confusing boxes. --}}
        @if($registration->photo_url || $registration->payment_proof_url || old('photo_url') || old('payment_proof_url'))
        <div class="grid gap-x-4 sm:grid-cols-2">
            <x-form.input
                name="photo_url"
                label="Photo (Google Drive link)"
                type="url"
                maxlength="512"
                placeholder="https://drive.google.com/…"
                :value="$registration->photo_url"
            />
            <x-form.input
                name="payment_proof_url"
                label="Payment screenshot (Google Drive link)"
                type="url"
                maxlength="512"
                placeholder="https://drive.google.com/…"
                :value="$registration->payment_proof_url"
            />
        </div>
        @endif
    </div>
@endif
