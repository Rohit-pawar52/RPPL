{{-- Shared by create.blade.php and edit.blade.php. $photo is null on create. --}}
@php
    $photo = $photo ?? null;
@endphp

<div class="crud-grid">
    <div class="crud-main">
        <x-admin.card :title="__('Photo')">
            <x-form.image-upload
                name="photo"
                :current="$photo?->photo_path"
                kind="image"
                shape="wide"
                box-class="h-44 w-full max-w-md rounded-xl sm:h-56"
                stack
                :empty-text="__('Click the picture to choose a photo')"
                :help="__('JPG, PNG or WebP, up to 5 MB.').($photo ? ' '.__('Leave it alone to keep the current photo.') : '')"
            />
        </x-admin.card>

        <x-admin.card :title="__('Details')">
            <x-form.input name="title" :label="__('Title')" :value="$photo->title ?? ''" maxlength="255" required autofocus />
            <x-form.textarea name="description" :label="__('Description (optional)')" :value="$photo->description ?? ''" rows="3" maxlength="2000" />
        </x-admin.card>
    </div>

    <div class="crud-aside">
        <x-admin.card :title="__('Publishing')">
            <x-form.select
                name="status"
                :label="__('Status')"
                :options="['active' => __('Active'), 'inactive' => __('Inactive')]"
                :value="$photo->status ?? 'active'"
                :help="__('Only Active photos appear on the public website.')"
            />
            <x-form.input
                name="priority"
                :label="__('Priority')"
                type="number"
                min="1"
                max="9999"
                :value="$photo->priority ?? 100"
                :help="__('Lower numbers are shown first.')"
            />
        </x-admin.card>
    </div>
</div>
