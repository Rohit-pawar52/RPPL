{{-- Shared by create.blade.php and edit.blade.php. $contributor is null on create. --}}
@php
    $contributor = $contributor ?? null;
@endphp

<x-form.image-upload
    name="photo"
    label="Photo (optional)"
    :current="$contributor?->photo_path"
    kind="user"
    shape="circle"
    stack
    empty-text="Click the picture to add a photo"
    help="JPG, PNG or WebP."
/>

<x-form.input name="name" label="Name" :value="$contributor->name ?? ''" required autofocus />
<x-form.input name="phone" label="Phone" :value="$contributor->phone ?? ''" />

@if($contributor)
    {{-- Only shown on edit: a newly added contributor defaults to
         active without the admin having to choose it explicitly. --}}
    <x-form.select
        name="is_active"
        label="Status"
        :options="['1' => 'Active', '0' => 'Inactive']"
        :value="$contributor->is_active ? '1' : '0'"
    />
@endif
