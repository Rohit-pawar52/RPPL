@php
    $logoPath = $settings->get('general.logo_path');
    $faviconPath = $settings->get('general.favicon_path');
@endphp

<form method="POST" action="{{ route('admin.settings.general.update') }}" enctype="multipart/form-data" novalidate>
    @csrf
    @method('PUT')

    <x-form.input name="application_name" label="Application Name" :value="$settings->get('general.application_name')" required maxlength="150" />
    <x-form.input name="short_name" label="Short Name" :value="$settings->get('general.short_name')" required maxlength="30" />
    <x-form.input name="tagline" label="Tagline" :value="$settings->get('general.tagline')" maxlength="255" />

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <x-form.color name="primary_color" label="Primary Color" :value="$settings->get('general.primary_color')" />
        <x-form.color name="secondary_color" label="Secondary Color" :value="$settings->get('general.secondary_color')" />
        <x-form.color name="button_color" label="Button Color" :value="$settings->get('general.button_color')" />
    </div>

    <p class="mb-1 text-[11px] font-medium text-neutral-500">Announcement ticker (public website)</p>
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <x-form.color name="announcement_background_color" label="Announcement Background Color" :value="$settings->get('general.announcement_background_color')" />
        <x-form.color name="announcement_text_color" label="Announcement Text Color" :value="$settings->get('general.announcement_text_color')" />
    </div>

    <x-form.image-upload
        name="logo"
        label="Logo"
        :current="$logoPath"
        kind="image"
        box-class="h-20 w-20 rounded-xl"
        empty-text="Click the picture to add a logo"
        remove-name="remove_logo"
        remove-label="Remove logo"
        help="PNG, JPG or WebP. Shown in the site header."
    />

    <x-form.image-upload
        name="favicon"
        label="Favicon"
        :current="$faviconPath"
        kind="image"
        box-class="h-14 w-14 rounded-lg"
        accept=".ico,image/png"
        empty-text="Click the picture to add a favicon"
        remove-name="remove_favicon"
        remove-label="Remove favicon"
        help="An .ico or PNG file. Shown in the browser tab."
    />

    <button type="submit" class="rounded-md theme-button px-3 py-2 text-[13px] font-medium">
        Save changes
    </button>
</form>
