{{-- Shared by create.blade.php and edit.blade.php. $team is null on create. --}}
@php
    $team = $team ?? null;
@endphp

<div class="grid gap-6 sm:grid-cols-[6.5rem_1fr]">
    <div>
        <p class="mb-1 text-xs font-medium text-neutral-700">Logo</p>

        <div class="flex flex-col items-center gap-2">
            <div class="flex h-20 w-20 items-center justify-center overflow-hidden rounded-full border border-neutral-200 bg-neutral-50 text-neutral-300">
                @if($team?->logo_path)
                    <img
                        src="{{ Illuminate\Support\Facades\Storage::url($team->logo_path) }}"
                        alt="{{ $team->name }}"
                        class="h-full w-full object-cover"
                    />
                @else
                    <x-icon name="shield" class="h-7 w-7" />
                @endif
            </div>

            <label class="cursor-pointer text-[11px] font-medium theme-link">
                {{ $team?->logo_path ? 'Replace logo' : 'Upload logo' }}
                <input
                    type="file"
                    name="logo"
                    accept="image/png,image/jpeg,image/webp"
                    class="hidden"
                    onchange="document.getElementById('logo-filename').textContent = this.files[0]?.name ?? ''"
                />
            </label>
            <p id="logo-filename" class="max-w-[7rem] truncate text-center text-[11px] text-neutral-400"></p>
        </div>

        @error('logo')
            <p class="mt-1 text-center text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <x-form.input name="name" label="Team name" :value="$team->name ?? ''" required autofocus />
        <x-form.input name="short_name" label="Short name" :value="$team->short_name ?? ''" maxlength="20" />

        @if($team)
            {{-- Only shown on edit: a newly created team defaults to active
                 without the admin having to choose it explicitly. --}}
            <x-form.select
                name="is_active"
                label="Status"
                :options="['1' => 'Active', '0' => 'Inactive']"
                :value="$team->is_active ? '1' : '0'"
            />
        @endif
    </div>
</div>
