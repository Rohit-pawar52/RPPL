{{-- Shared by create.blade.php and edit.blade.php. $contributor is null on create. --}}
@php
    $contributor = $contributor ?? null;
@endphp

<div class="crud-grid">
    <div class="crud-main">
        <x-admin.card title="Contributor details">
            <x-form.input name="name" label="Name" :value="$contributor->name ?? ''" required autofocus />
            <x-form.input name="phone" label="Phone" type="tel" inputmode="tel" :value="$contributor->phone ?? ''" />
        </x-admin.card>
    </div>

    <div class="crud-aside">
        <x-admin.card title="Photo (optional)">
            <x-form.image-upload
                name="photo"
                :current="$contributor?->photo_path"
                kind="user"
                shape="circle"
                box-class="h-32 w-32 rounded-full"
                stack
                empty-text="Click the picture to add a photo"
                help="JPG, PNG or WebP."
            />
        </x-admin.card>

        @if($contributor)
            {{-- Only shown on edit: a newly added contributor defaults to
                 active without the admin having to choose it explicitly. --}}
            <x-admin.card title="Status">
                <x-form.select
                    name="is_active"
                    label="Status"
                    :options="['1' => 'Active', '0' => 'Inactive']"
                    :value="$contributor->is_active ? '1' : '0'"
                />
            </x-admin.card>
        @else
            <p class="crud-note">New contributors start as <strong class="font-semibold text-slate-700">Active</strong>.</p>
        @endif
    </div>
</div>
