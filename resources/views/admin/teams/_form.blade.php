{{-- Shared by create.blade.php and edit.blade.php. $team is null on create. --}}
@php
    $team = $team ?? null;
@endphp

<div class="crud-grid">
    <div class="crud-main">
        <x-admin.card title="Team details">
            <x-form.input name="name" label="Team name" :value="$team->name ?? ''" required autofocus />
            <x-form.input name="short_name" label="Short name" :value="$team->short_name ?? ''" maxlength="20" help="Shown where space is tight, like a scoreboard (for example CSK)." />
        </x-admin.card>
    </div>

    <div class="crud-aside">
        <x-admin.card title="Logo">
            <x-form.image-upload
                name="logo"
                :current="$team?->logo_path"
                kind="image"
                shape="circle"
                box-class="h-32 w-32 rounded-full"
                stack
                empty-text="Click the picture to add a logo"
                help="JPG, PNG or WebP."
            />
        </x-admin.card>

        @if($team)
            {{-- Only shown on edit: a newly created team defaults to active
                 without the admin having to choose it explicitly. --}}
            <x-admin.card title="Status">
                <x-form.select
                    name="is_active"
                    label="Status"
                    :options="['1' => 'Active', '0' => 'Inactive']"
                    :value="$team->is_active ? '1' : '0'"
                    help="Inactive teams cannot be added to a new edition."
                />
            </x-admin.card>
        @else
            <p class="crud-note">New teams start as <strong class="font-semibold text-slate-700">Active</strong>. You can switch them off later from the edit page.</p>
        @endif
    </div>
</div>
