{{-- Shared by create.blade.php and edit.blade.php. $edition is null on create. --}}
@php
    $edition = $edition ?? null;
@endphp

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

<x-form.input
    name="registration_fee"
    label="Registration Fee (optional)"
    type="number"
    step="0.01"
    min="0"
    :value="$edition->registration_fee ?? ''"
/>
