{{-- Shared by create.blade.php and edit.blade.php. $team is null on create. --}}
@php
    $team = $team ?? null;
@endphp

<div class="grid gap-6 sm:grid-cols-[6.5rem_1fr]">
    <div>
        <x-form.image-upload
            name="logo"
            label="Logo"
            :current="$team?->logo_path"
            kind="image"
            shape="circle"
            stack
            empty-text="Click the picture to add a logo"
            help="JPG, PNG or WebP."
        />
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
