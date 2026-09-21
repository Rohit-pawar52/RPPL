{{-- Shared by create.blade.php and edit.blade.php. $player is null on create. --}}
@php
    $player = $player ?? null;
@endphp

<div class="grid gap-6 sm:grid-cols-[6.5rem_1fr]">
    <div>
        <p class="mb-1 text-xs font-medium text-neutral-700">Photo</p>

        <div class="flex flex-col items-center gap-2">
            <div class="flex h-20 w-20 items-center justify-center overflow-hidden rounded-full border border-neutral-200 bg-neutral-50 text-neutral-300">
                @if($player?->photo_path)
                    <img
                        src="{{ Illuminate\Support\Facades\Storage::url($player->photo_path) }}"
                        alt="{{ $player->name }}"
                        class="h-full w-full object-cover"
                    />
                @else
                    <x-icon name="camera" class="h-7 w-7" />
                @endif
            </div>

            <label class="cursor-pointer text-[11px] font-medium theme-link">
                {{ $player?->photo_path ? 'Replace photo' : 'Upload photo' }}
                <input
                    type="file"
                    name="photo"
                    accept="image/png,image/jpeg,image/webp"
                    class="hidden"
                    onchange="document.getElementById('photo-filename').textContent = this.files[0]?.name ?? ''"
                />
            </label>
            <p id="photo-filename" class="max-w-[7rem] truncate text-center text-[11px] text-neutral-400"></p>
        </div>

        @error('photo')
            <p class="mt-1 text-center text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div>
        <p class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">Personal</p>

        <x-form.input name="name" label="Full name" :value="$player->name ?? ''" required autofocus />

        <div class="grid gap-x-3 sm:grid-cols-2">
            <x-form.input
                name="date_of_birth"
                label="Date of birth"
                type="date"
                :value="optional($player?->date_of_birth)->format('Y-m-d')"
                max="{{ now()->format('Y-m-d') }}"
            />
            <x-form.input name="phone" label="Phone" :value="$player->phone ?? ''" maxlength="20" />
        </div>

        <x-form.input name="email" label="Email" type="email" :value="$player->email ?? ''" />

        @if($player)
            {{-- Only shown on edit: a newly created player defaults to
                 active without the admin having to choose it explicitly. --}}
            <x-form.select
                name="is_active"
                label="Status"
                :options="['1' => 'Active', '0' => 'Inactive']"
                :value="$player->is_active ? '1' : '0'"
            />
        @endif

        <p class="mb-2 mt-4 text-[11px] font-semibold uppercase tracking-wide text-neutral-400">Cricket Profile</p>

        <div class="grid gap-x-3 sm:grid-cols-3">
            <x-form.select
                name="primary_role"
                label="Primary role"
                placeholder="Not set"
                :options="collect(\App\Models\Player::PRIMARY_ROLES)->mapWithKeys(fn ($role) => [$role => ucwords(str_replace('_', ' ', $role))])"
                :value="$player->primary_role ?? ''"
            />
            <x-form.select
                name="batting_style"
                label="Batting style"
                placeholder="Not set"
                :options="collect(\App\Models\Player::BATTING_STYLES)->mapWithKeys(fn ($style) => [$style => ucwords(str_replace('_', ' ', $style))])"
                :value="$player->batting_style ?? ''"
            />
            <x-form.select
                name="bowling_style"
                label="Bowling style"
                placeholder="Not set"
                :options="collect(\App\Models\Player::BOWLING_STYLES)->mapWithKeys(fn ($style) => [$style => ucwords(str_replace('_', ' ', $style))])"
                :value="$player->bowling_style ?? ''"
            />
        </div>
    </div>
</div>
