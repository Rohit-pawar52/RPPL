{{-- Read-only map + "Get directions" for a venue with coordinates. Renders
     nothing otherwise. Expects $venue. venue-map.js is loaded only here. --}}
@if($venue->hasCoordinates())
    <div
        data-venue-map
        data-mode="view"
        data-lat="{{ $venue->latitude }}"
        data-lng="{{ $venue->longitude }}"
        data-name="{{ $venue->name }}"
        data-label-map="{{ __('directory.venues.map_view') }}"
        data-label-satellite="{{ __('directory.venues.satellite_view') }}"
    >
        <div data-map-canvas class="z-0 h-56 w-full overflow-hidden rounded-lg border border-line bg-slate-100 sm:h-64"></div>
        <a
            href="{{ $venue->directionsUrl() }}"
            target="_blank"
            rel="noopener noreferrer"
            aria-label="{{ __('directory.venues.directions_to', ['venue' => $venue->name]) }}"
            class="pub-btn mt-2 inline-flex items-center gap-1.5"
        >
            <x-icon name="map-pin" class="h-4 w-4" />
            {{ __('directory.venues.get_directions') }}
        </a>
    </div>

    @vite('resources/js/venue-map.js')
@endif
