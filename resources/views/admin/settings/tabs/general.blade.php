@php
    $logoPath = $settings->get('general.logo_path');
    $faviconPath = $settings->get('general.favicon_path');
@endphp

<form method="POST" action="{{ route('admin.settings.general.update') }}" enctype="multipart/form-data" novalidate class="space-y-5">
    @csrf
    @method('PUT')

    {{-- Who the site is --}}
    <section class="rounded-xl border border-line bg-white">
        <div class="border-b border-line px-4 py-3">
            <h2 class="text-sm font-semibold text-slate-900">Identity</h2>
            <p class="mt-0.5 text-[12px] text-slate-500">The name, tagline, logo and favicon shown across the website and the admin panel.</p>
        </div>
        <div class="grid gap-x-6 p-4 lg:grid-cols-2">
            <div>
                <x-form.input name="application_name" label="Application Name" :value="$settings->get('general.application_name')" required maxlength="150" />
                <x-form.input name="short_name" label="Short Name" :value="$settings->get('general.short_name')" required maxlength="30" />
                <x-form.input name="tagline" label="Tagline" :value="$settings->get('general.tagline')" maxlength="255" />
            </div>
            <div class="grid grid-cols-1 gap-x-6 sm:grid-cols-2">
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
            </div>
        </div>
    </section>

    {{-- How the site looks: every colour, the hover colours and the button shape --}}
    @include('admin.settings.tabs._theme')

    <div class="sticky bottom-3 z-10 flex justify-end">
        <button type="submit" class="btn btn-primary btn-lg shadow-raised">Save changes</button>
    </div>
</form>
