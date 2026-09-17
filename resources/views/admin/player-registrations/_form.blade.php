{{-- Shared by create.blade.php and edit.blade.php. $registration is null on create.
     edition_id/player_id are immutable once a registration exists, so they only
     render as selects on create; on edit they show read-only. --}}
@php
    $registration = $registration ?? null;
@endphp

<div class="grid gap-4 sm:grid-cols-2">
    @if($registration)
        <div>
            <p class="mb-1 text-xs font-medium text-neutral-700">Player</p>
            <p class="rounded-md border border-neutral-200 bg-neutral-50 px-3 py-2 text-[13px] text-neutral-700">
                {{ $registration->player->name }}
                @unless($registration->player->is_active)
                    <span class="text-neutral-400">(inactive)</span>
                @endunless
            </p>
        </div>
        <div>
            <p class="mb-1 text-xs font-medium text-neutral-700">Edition</p>
            <p class="rounded-md border border-neutral-200 bg-neutral-50 px-3 py-2 text-[13px] text-neutral-700">
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
        :value="$registration ? $registration->registered_at?->format('Y-m-d\TH:i') : now()->format('Y-m-d\TH:i')"
    />
</div>

@if($registration)
    {{-- Payment verification only applies to an existing registration;
         identity fields (edition/player) above are already read-only
         here, and document paths are never editable at all (see Phase
         3.39D — replacement/deletion is deliberately out of scope). --}}
    <div class="mt-4 grid gap-4 sm:grid-cols-3">
        <x-form.input
            name="payment_reference"
            label="Payment reference / UTR (optional)"
            :value="old('payment_reference', $registration->payment_reference)"
            placeholder="e.g. UTR or transaction ID"
        />
    </div>
@endif
