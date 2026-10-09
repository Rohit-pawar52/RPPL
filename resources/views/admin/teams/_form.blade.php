{{-- Shared by create.blade.php and edit.blade.php. $team is null on create. --}}
@php
    $team = $team ?? null;
@endphp

<div class="crud-grid">
    <div class="crud-main">
        <x-admin.card :title="__('Team details')">
            <x-form.input name="name" :label="__('Team name')" :value="$team->name ?? ''" required autofocus />
            <x-form.input name="short_name" :label="__('Short name')" :value="$team->short_name ?? ''" maxlength="20" :help="__('Shown where space is tight, like a scoreboard (for example CSK).')" />
        </x-admin.card>
    </div>

    <div class="crud-aside">
        <x-admin.card :title="__('Logo')">
            <x-form.image-upload
                name="logo"
                :current="$team?->logo_path"
                kind="image"
                shape="circle"
                box-class="h-32 w-32 rounded-full"
                stack
                :empty-text="__('Click the picture to add a logo')"
                :help="__('JPG, PNG or WebP.')"
            />
        </x-admin.card>

        @if($team)
            {{-- Only shown on edit: a newly created team defaults to active
                 without the admin having to choose it explicitly. --}}
            <x-admin.card :title="__('Status')">
                <x-form.select
                    name="is_active"
                    :label="__('Status')"
                    :options="['1' => __('Active'), '0' => __('Inactive')]"
                    :value="$team->is_active ? '1' : '0'"
                    :help="__('Inactive teams cannot be added to a new edition.')"
                />
            </x-admin.card>
        @else
            <p class="crud-note">{!! __('New teams start as :active. You can switch them off later from the edit page.', ['active' => '<strong class="font-semibold text-slate-700">'.e(__('Active')).'</strong>']) !!}</p>
        @endif
    </div>
</div>
