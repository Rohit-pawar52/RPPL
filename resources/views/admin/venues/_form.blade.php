{{-- Shared by create.blade.php and edit.blade.php. $venue is null on create. --}}
@php
    $venue = $venue ?? null;
@endphp

<x-form.input name="name" label="Venue name" :value="$venue->name ?? ''" required autofocus />

<div class="grid gap-4 sm:grid-cols-2">
    <x-form.input name="city" label="City" :value="$venue->city ?? ''" />
    <x-form.input name="country" label="Country" :value="$venue->country ?? ''" />
</div>

@if($venue)
    {{-- Only shown on edit: a newly created venue defaults to active
         without the admin having to choose it explicitly. --}}
    <x-form.select
        name="is_active"
        label="Status"
        :options="['1' => 'Active', '0' => 'Inactive']"
        :value="$venue->is_active ? '1' : '0'"
    />
@endif
