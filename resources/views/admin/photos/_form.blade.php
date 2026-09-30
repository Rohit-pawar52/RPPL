{{-- Shared by create.blade.php and edit.blade.php. $photo is null on create. --}}
@php
    $photo = $photo ?? null;
@endphp

<x-form.input name="title" label="Title" :value="$photo->title ?? ''" maxlength="255" required autofocus />

<x-form.textarea name="description" label="Description (optional)" :value="$photo->description ?? ''" rows="3" maxlength="2000" />

<div class="mb-3.5">
    <p class="mb-1 text-xs font-medium text-slate-700">Photo</p>

    <div class="flex items-center gap-3">
        <div class="flex h-16 w-28 shrink-0 items-center justify-center overflow-hidden rounded-md border border-slate-200 bg-slate-50 text-slate-300">
            @if($photo?->photo_path)
                <img
                    src="{{ Illuminate\Support\Facades\Storage::url($photo->photo_path) }}"
                    alt="{{ $photo->title }}"
                    class="h-full w-full object-cover"
                />
            @else
                <x-icon name="camera" class="h-6 w-6" />
            @endif
        </div>

        <div class="min-w-0">
            <label class="cursor-pointer text-[12px] font-medium text-green-700">
                {{ $photo?->photo_path ? 'Replace photo' : 'Choose photo' }}
                <input
                    type="file"
                    name="photo"
                    accept="image/png,image/jpeg,image/webp"
                    class="hidden"
                    onchange="document.getElementById('photo-filename').textContent = this.files[0]?.name ?? ''"
                />
            </label>
            <p id="photo-filename" class="mt-1 max-w-[12rem] truncate text-[11px] text-slate-500"></p>
            <p class="mt-1 text-[11px] text-slate-400">
                JPG, PNG or WebP, up to 5 MB.
                @if($photo)
                    Leave empty to keep the current photo.
                @endif
            </p>
        </div>
    </div>

    @error('photo')
        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>

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
