{{-- Shared by create.blade.php and edit.blade.php. $venue is null on create. --}}
@php
    $venue = $venue ?? null;
@endphp

<div class="crud-grid">
    <div class="crud-main">
        <x-admin.card :title="__('Venue')">
            <x-form.input name="name" :label="__('Venue name')" :value="$venue->name ?? ''" required autofocus />

            <div class="crud-cols-3">
                <x-form.input name="village" :label="__('Village (Gram)')" :value="$venue->village ?? ''" maxlength="100" />
                <x-form.input name="tehsil" :label="__('Tehsil')" :value="$venue->tehsil ?? ''" maxlength="100" />
                <x-form.input name="district" :label="__('District')" :value="$venue->district ?? ''" maxlength="100" />
            </div>
        </x-admin.card>

        {{-- Exact ground location. The two number inputs are the real fields and
             stay usable on their own; venue-map.js layers the map picker on top. --}}
        <x-admin.card :title="__('Map location')">
            <div data-venue-map data-mode="picker" data-lat-input="latitude" data-lng-input="longitude" data-label-map="{{ __('Map') }}" data-label-satellite="{{ __('Satellite') }}">
                <div class="mb-2 flex gap-2">
                    <input
                        type="text"
                        data-map-search
                        placeholder="{{ __('Search a place, e.g. village name, district') }}"
                        aria-label="{{ __('Search a place on the map') }}"
                        class="h-10 min-w-0 flex-1 rounded-md border border-slate-300 bg-white px-3 text-[13px] text-slate-800 placeholder:text-slate-400 focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/25"
                    />
                    <button type="button" data-map-search-btn class="h-10 shrink-0 rounded-md border border-slate-300 bg-white px-3 text-[13px] font-medium text-slate-700 hover:bg-hover">{{ __('Search') }}</button>
                </div>

                <div data-map-canvas class="z-0 h-72 w-full rounded-xl border border-slate-300 bg-slate-100 sm:h-96"></div>

                <div class="mt-1.5 flex flex-wrap items-center justify-between gap-2">
                    <p data-map-status class="text-xs text-slate-500" aria-live="polite">{{ __('Click the map to drop the pin, then drag it to the exact spot.') }}</p>
                    <button type="button" data-map-clear class="text-[12px] font-medium text-red-600 hover:underline">{{ __('Clear location') }}</button>
                </div>

                <div class="mt-3 grid gap-x-4 sm:grid-cols-2">
                    <x-form.input name="latitude" type="number" step="any" min="-90" max="90" :label="__('Latitude')" :value="$venue->latitude ?? ''" :placeholder="__('e.g. :value', ['value' => '21.9000000'])" />
                    <x-form.input name="longitude" type="number" step="any" min="-180" max="180" :label="__('Longitude')" :value="$venue->longitude ?? ''" :placeholder="__('e.g. :value', ['value' => '77.9000000'])" />
                </div>
            </div>
        </x-admin.card>
    </div>

    <div class="crud-aside">
        <x-admin.card :title="__('Defaults')">
            <x-form.checkbox
                name="is_default"
                :label="__('Default venue')"
                :checked="$venue?->is_default ?? false"
                :help="__('New matches start with this venue selected (you can still pick another for a match). Only one venue is the default.')"
            />

            @if($venue)
                {{-- Only shown on edit: a newly created venue defaults to active
                     without the admin having to choose it explicitly. --}}
                <x-form.select
                    name="is_active"
                    :label="__('Status')"
                    :options="['1' => __('Active'), '0' => __('Inactive')]"
                    :value="$venue->is_active ? '1' : '0'"
                />
            @endif
        </x-admin.card>
    </div>
</div>

@vite('resources/js/venue-map.js')
