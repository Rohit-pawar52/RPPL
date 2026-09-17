{{-- Shared by create.blade.php and edit.blade.php. $member is null on create. --}}
@php
    $member = $member ?? null;
@endphp

<x-form.input name="name" label="Name" :value="$member->name ?? ''" required autofocus />
<x-form.input name="phone" label="Phone" :value="$member->phone ?? ''" />

@if($member)
    {{-- Only shown on edit: a newly added member defaults to active
         without the admin having to choose it explicitly. --}}
    <x-form.select
        name="is_active"
        label="Status"
        :options="['1' => 'Active', '0' => 'Inactive']"
        :value="$member->is_active ? '1' : '0'"
    />
@endif
