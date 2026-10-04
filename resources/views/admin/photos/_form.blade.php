{{-- Shared by create.blade.php and edit.blade.php. $photo is null on create. --}}
@php
    $photo = $photo ?? null;
@endphp

<x-form.input name="title" label="Title" :value="$photo->title ?? ''" maxlength="255" required autofocus />

<x-form.textarea name="description" label="Description (optional)" :value="$photo->description ?? ''" rows="3" maxlength="2000" />

<x-form.image-upload
    name="photo"
    label="Photo"
    :current="$photo?->photo_path"
    kind="image"
    shape="wide"
    empty-text="Click the picture to choose a photo"
    :help="'JPG, PNG or WebP, up to 5 MB.'.($photo ? ' Leave it alone to keep the current photo.' : '')"
/>

<div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
    <x-form.select
        name="status"
        label="Status"
        :options="['active' => 'Active', 'inactive' => 'Inactive']"
        :value="$photo->status ?? 'active'"
    />
    <x-form.input
        name="priority"
        label="Priority"
        type="number"
        min="1"
        max="9999"
        :value="$photo->priority ?? 100"
    />
</div>
<p class="-mt-2.5 mb-3.5 text-[11px] text-slate-400">
    Only Active photos appear on the public website. Lower priority numbers are shown first.
</p>
