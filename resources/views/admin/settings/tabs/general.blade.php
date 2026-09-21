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

    <div class="mb-3.5">
        <p class="mb-1 text-xs font-medium text-neutral-700">Logo</p>
        <div class="flex items-center gap-3">
            <div class="flex h-16 w-16 items-center justify-center overflow-hidden rounded-md border border-neutral-200 bg-neutral-50 text-neutral-300">
                @if($logoPath)
                    <img src="{{ Illuminate\Support\Facades\Storage::url($logoPath) }}" alt="Logo" class="h-full w-full object-contain" />
                @else
                    <x-icon name="camera" class="h-6 w-6" />
                @endif
            </div>
            <div>
                <label class="cursor-pointer text-[11px] font-medium theme-link">
                    {{ $logoPath ? 'Replace logo' : 'Upload logo' }}
                    <input
                        type="file"
                        name="logo"
                        accept="image/png,image/jpeg,image/webp"
                        class="hidden"
                        onchange="document.getElementById('logo-filename').textContent = this.files[0]?.name ?? ''"
                    />
                </label>
                <p id="logo-filename" class="text-[11px] text-neutral-400"></p>
                @if($logoPath)
                    <label class="mt-1 flex items-center gap-1 text-[11px] text-red-600">
                        <input type="checkbox" name="remove_logo" value="1" class="rounded border-neutral-300" />
                        Remove logo
                    </label>
                @endif
            </div>
        </div>
        @error('logo')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <div class="mb-3.5">
        <p class="mb-1 text-xs font-medium text-neutral-700">Favicon</p>
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center overflow-hidden rounded-md border border-neutral-200 bg-neutral-50 text-neutral-300">
                @if($faviconPath)
                    <img src="{{ Illuminate\Support\Facades\Storage::url($faviconPath) }}" alt="Favicon" class="h-full w-full object-contain" />
                @else
                    <x-icon name="camera" class="h-5 w-5" />
                @endif
            </div>
            <div>
                <label class="cursor-pointer text-[11px] font-medium theme-link">
                    {{ $faviconPath ? 'Replace favicon' : 'Upload favicon' }}
                    <input
                        type="file"
                        name="favicon"
                        accept=".ico,image/png"
                        class="hidden"
                        onchange="document.getElementById('favicon-filename').textContent = this.files[0]?.name ?? ''"
                    />
                </label>
                <p id="favicon-filename" class="text-[11px] text-neutral-400"></p>
                @if($faviconPath)
                    <label class="mt-1 flex items-center gap-1 text-[11px] text-red-600">
                        <input type="checkbox" name="remove_favicon" value="1" class="rounded border-neutral-300" />
                        Remove favicon
                    </label>
                @endif
            </div>
        </div>
        @error('favicon')
            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
        @enderror
    </div>

    <button type="submit" class="rounded-md theme-button px-3 py-2 text-[13px] font-medium">
        Save changes
    </button>
</form>
