{{-- Shared by create.blade.php and edit.blade.php. $player is null on create. --}}
@php
    $player = $player ?? null;
@endphp

<div class="grid gap-6 sm:grid-cols-[6.5rem_1fr]">
    <div>
        <x-form.image-upload
            name="photo"
            label="Photo"
            :current="$player?->photo_path"
            kind="user"
            shape="circle"
            stack
            empty-text="Click the picture to add a photo"
            help="JPG, PNG or WebP."
        />
    </div>

    <div>
        <p class="mb-2 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Personal</p>

        <x-form.input name="name" label="Full name" :value="$player->name ?? ''" required autofocus />

        <div class="grid gap-x-3 sm:grid-cols-2">
            <x-form.input
                name="date_of_birth"
                label="Date of birth"
                type="date"
                :value="optional($player?->date_of_birth)->format('Y-m-d')"
                max="{{ display_datetime(now(), 'Y-m-d') }}"
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

        <p class="mb-2 mt-4 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Cricket Profile</p>

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
