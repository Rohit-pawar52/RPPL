import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

/**
 * Venue map (Leaflet + OpenStreetMap, no API key).
 *
 * Wires up every [data-venue-map] element on the page:
 *  - data-mode="picker": admin venue form. Click the map (or drag the pin)
 *    to set the location; it is written into the latitude/longitude number
 *    inputs named by data-lat-input / data-lng-input (element ids), which
 *    stay visible and editable so the form works without the map too.
 *  - data-mode="view" (default): public read-only map with a single pin.
 *
 * Inside the root: [data-map-canvas] is the map itself; the picker also uses
 * [data-map-search], [data-map-search-btn], [data-map-clear], [data-map-status].
 */

// Roughly Betul, MP: a neutral low-zoom start for a new venue.
const DEFAULT_CENTER = [21.9, 77.9];
const DEFAULT_ZOOM = 6;
const PIN_ZOOM = 16;

function pinIcon() {
    // A divIcon avoids Leaflet's default marker images, whose URLs Vite breaks.
    return L.divIcon({
        className: '',
        iconSize: [26, 26],
        iconAnchor: [13, 26],
        html: '<span style="display:block;width:22px;height:22px;margin:0 2px;border-radius:50% 50% 50% 0;'
            + 'transform:rotate(-45deg);background:#16a34a;border:3px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.5)"></span>',
    });
}

function createMap(canvas, root, { interactive }) {
    const map = L.map(canvas, {
        center: DEFAULT_CENTER,
        zoom: DEFAULT_ZOOM,
        // The public map sits inside a scrolling page: never hijack the wheel.
        scrollWheelZoom: interactive,
    });

    const street = L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap</a> contributors',
    });
    const satellite = L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', {
        maxZoom: 19,
        attribution: 'Tiles &copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community',
    });

    street.addTo(map);
    L.control.layers({
        [root.dataset.labelMap || 'Map']: street,
        [root.dataset.labelSatellite || 'Satellite']: satellite,
    }, {}, { collapsed: false }).addTo(map);

    // The map can be created while hidden (zero size); fix it up on resize.
    if (window.ResizeObserver) {
        new ResizeObserver(() => map.invalidateSize()).observe(canvas);
    }

    return map;
}

function parseCoord(value, min, max) {
    if (value === null || value === undefined || String(value).trim() === '') {
        return null;
    }
    const number = Number(value);

    return Number.isFinite(number) && number >= min && number <= max ? number : null;
}

function initViewMap(root) {
    const lat = parseCoord(root.dataset.lat, -90, 90);
    const lng = parseCoord(root.dataset.lng, -180, 180);
    const canvas = root.querySelector('[data-map-canvas]');

    if (lat === null || lng === null || !canvas) {
        return;
    }

    const map = createMap(canvas, root, { interactive: false });
    map.setView([lat, lng], PIN_ZOOM);
    L.marker([lat, lng], { icon: pinIcon(), keyboard: false, title: root.dataset.name || '' }).addTo(map);
}

function initPickerMap(root) {
    const canvas = root.querySelector('[data-map-canvas]');
    const latInput = document.getElementById(root.dataset.latInput);
    const lngInput = document.getElementById(root.dataset.lngInput);

    if (!canvas || !latInput || !lngInput) {
        return;
    }

    const searchInput = root.querySelector('[data-map-search]');
    const searchButton = root.querySelector('[data-map-search-btn]');
    const clearButton = root.querySelector('[data-map-clear]');
    const status = root.querySelector('[data-map-status]');

    const setStatus = (text) => {
        if (status) {
            status.textContent = text;
        }
    };

    const map = createMap(canvas, root, { interactive: true });
    let marker = null;

    const writeInputs = (latlng) => {
        latInput.value = latlng.lat.toFixed(7);
        lngInput.value = latlng.lng.toFixed(7);
    };

    const placeMarker = (latlng) => {
        if (marker) {
            marker.setLatLng(latlng);
        } else {
            marker = L.marker(latlng, { icon: pinIcon(), draggable: true }).addTo(map);
            marker.on('dragend', () => {
                writeInputs(marker.getLatLng());
                setStatus('Location set.');
            });
        }
    };

    const readInputs = () => {
        const lat = parseCoord(latInput.value, -90, 90);
        const lng = parseCoord(lngInput.value, -180, 180);

        return lat === null || lng === null ? null : L.latLng(lat, lng);
    };

    // Initial view: the saved pin, otherwise the default low-zoom view.
    const saved = readInputs();
    if (saved) {
        placeMarker(saved);
        map.setView(saved, PIN_ZOOM);
    }

    map.on('click', (event) => {
        placeMarker(event.latlng);
        writeInputs(event.latlng);
        setStatus('Location set. Drag the pin to adjust it.');
    });

    // Typing coordinates by hand also moves the pin.
    const syncFromInputs = () => {
        const latlng = readInputs();
        if (latlng) {
            placeMarker(latlng);
            map.setView(latlng, Math.max(map.getZoom(), PIN_ZOOM));
        }
    };
    latInput.addEventListener('change', syncFromInputs);
    lngInput.addEventListener('change', syncFromInputs);

    clearButton?.addEventListener('click', () => {
        if (marker) {
            marker.remove();
            marker = null;
        }
        latInput.value = '';
        lngInput.value = '';
        setStatus('Location cleared.');
    });

    // Search runs only when the admin asks for it (button or Enter).
    const search = async () => {
        const query = searchInput?.value.trim();
        if (!query) {
            return;
        }

        setStatus('Searching...');
        try {
            const response = await fetch(
                'https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' + encodeURIComponent(query),
                { headers: { Accept: 'application/json' } },
            );
            const results = await response.json();

            if (!Array.isArray(results) || results.length === 0) {
                setStatus('No place found. Try a different search.');
                return;
            }

            const target = L.latLng(Number(results[0].lat), Number(results[0].lon));
            map.setView(target, 15);
            setStatus('Found. Click the map to drop the pin on the exact spot.');
        } catch (error) {
            setStatus('Search is unavailable right now. You can still click the map or type the coordinates.');
        }
    };

    searchButton?.addEventListener('click', search);
    searchInput?.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            // Enter must search, not submit the venue form.
            event.preventDefault();
            search();
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-venue-map]').forEach((root) => {
        if (root.dataset.mode === 'picker') {
            initPickerMap(root);
        } else {
            initViewMap(root);
        }
    });
});
