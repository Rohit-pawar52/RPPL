{{-- Shared by create.blade.php and edit.blade.php. $contributor is null on create. --}}
@php
    $contributor = $contributor ?? null;
@endphp

<div class="mb-4 flex flex-col items-center gap-2">
    <div class="flex h-20 w-20 items-center justify-center overflow-hidden rounded-full border border-neutral-200 bg-neutral-50 text-neutral-300">
        @if($contributor?->photo_path)
            <img
                src="{{ Illuminate\Support\Facades\Storage::url($contributor->photo_path) }}"
                alt="{{ $contributor->name }}"
                class="h-full w-full object-cover"
            />
        @else
            <x-icon name="camera" class="h-7 w-7" />
        @endif
    </div>

    <label class="cursor-pointer text-[11px] font-medium theme-link">
        {{ $contributor?->photo_path ? 'Replace photo' : 'Upload photo (optional)' }}
        <input
            type="file"
            name="photo"
            accept="image/png,image/jpeg,image/webp"
            class="hidden"
            onchange="document.getElementById('contributor-photo-filename').textContent = this.files[0]?.name ?? ''"
        />
    </label>
    <p id="contributor-photo-filename" class="max-w-[10rem] truncate text-center text-[11px] text-neutral-400"></p>

    @error('photo')
        <p class="text-center text-xs text-red-600">{{ $message }}</p>
    @enderror
</div>

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
