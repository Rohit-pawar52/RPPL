{{-- Shared by create.blade.php and edit.blade.php. $venue is null on create. --}}
@php
    $venue = $venue ?? null;
@endphp

<x-form.input name="name" label="Venue name" :value="$venue->name ?? ''" required autofocus />

<div class="grid gap-4 sm:grid-cols-3">
    <x-form.input name="village" label="Village (Gram)" :value="$venue->village ?? ''" maxlength="100" />
    <x-form.input name="tehsil" label="Tehsil" :value="$venue->tehsil ?? ''" maxlength="100" />
    <x-form.input name="district" label="District" :value="$venue->district ?? ''" maxlength="100" />
</div>

{{-- Exact ground location. The two number inputs are the real fields and
     stay usable on their own; venue-map.js layers the map picker on top. --}}
<div class="mb-3.5" data-venue-map data-mode="picker" data-lat-input="latitude" data-lng-input="longitude">
    <span class="mb-1 block text-xs font-medium text-slate-700">Map location</span>

    <div class="mb-2 flex gap-2">
        <input
            type="text"
            data-map-search
            placeholder="Search a place, e.g. village name, district"
            aria-label="Search a place on the map"
            class="h-10 min-w-0 flex-1 rounded-md border border-slate-300 bg-white px-3 text-[13px] text-slate-800 placeholder:text-slate-400 focus:border-green-500 focus:outline-none focus:ring-2 focus:ring-green-100"
        />
        <button type="button" data-map-search-btn class="h-10 shrink-0 rounded-md border border-slate-300 bg-white px-3 text-[13px] font-medium text-slate-700 hover:bg-slate-50">Search</button>
    </div>

    <div data-map-canvas class="z-0 h-80 w-full rounded-md border border-slate-300 bg-slate-100"></div>

    <div class="mt-1.5 flex flex-wrap items-center justify-between gap-2">
        <p data-map-status class="text-[11px] text-slate-400" aria-live="polite">Click the map to drop the pin, then drag it to the exact spot.</p>
        <button type="button" data-map-clear class="text-[12px] font-medium text-red-600 hover:underline">Clear location</button>
    </div>

    <div class="mt-3 grid gap-4 sm:grid-cols-2">
        <x-form.input name="latitude" type="number" step="any" min="-90" max="90" label="Latitude" :value="$venue->latitude ?? ''" placeholder="e.g. 21.9000000" />
        <x-form.input name="longitude" type="number" step="any" min="-180" max="180" label="Longitude" :value="$venue->longitude ?? ''" placeholder="e.g. 77.9000000" />
    </div>
</div>

<x-form.checkbox
    name="is_default"
    label="Default venue"
    :checked="$venue?->is_default ?? false"
    help="New matches start with this venue selected (you can still pick another for a match). Only one venue is the default."
/>

@if($venue)
    {{-- Only shown on edit: a newly created venue defaults to active
         without the admin having to choose it explicitly. --}}
    <x-form.select
        name="is_active"
        label="Status"
        :options="['1' => 'Active', '0' => 'Inactive']"
        :value="$venue->is_active ? '1' : '0'"
    />
@endif

@vite('resources/js/venue-map.js')
