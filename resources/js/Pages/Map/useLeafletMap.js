import { ref } from 'vue';

// Leaflet wiring for Map View (Plan §Map 3): init, deployed-device markers
// with clustering, and the boundary polygon layer with highlight +
// click-to-filter. Leaflet loads globally via app.blade.php.
export function useLeafletMap(containerRef) {
    const mapRef = ref(null);
    let map = null;
    let markerLayer = null;
    let boundaryLayer = null;

    const statusColors = {
        UP: '#059669',
        DOWN: '#dc2626',
        DOWN_SERVER: '#dc2626',
        NO_NMS: '#d97706',
        NO_DATA: '#94a3b8',
    };

    function init() {
        if (map || !containerRef.value) {
            return;
        }
        map = L.map(containerRef.value, { zoomControl: true }).setView([16.9, 121.8], 7);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors',
            maxZoom: 18,
        }).addTo(map);
        mapRef.value = map;
        setTimeout(() => map?.invalidateSize(), 200);
    }

    function destroy() {
        map?.remove();
        map = null;
        markerLayer = null;
        boundaryLayer = null;
    }

    function clearMarkers() {
        if (markerLayer) {
            map.removeLayer(markerLayer);
            markerLayer = null;
        }
    }

    function renderMarkers(data, { typeLabel = {} } = {}) {
        clearMarkers();
        const style = { radius: 7, color: '#fff', weight: 2, opacity: 1, fillOpacity: 0.9 };

        const geoJson = L.geoJSON(data, {
            pointToLayer: (feature, latlng) => {
                const p = feature.properties;
                return L.circleMarker(latlng, {
                    ...style,
                    fillColor: statusColors[p.daily_status] ?? p.marker_color ?? '#2563eb',
                });
            },
            onEachFeature: (feature, layer) => {
                const p = feature.properties;
                const isDevice = p.device_count !== undefined;
                const where = [p.barangay, p.municipality, p.province].filter(Boolean).join(', ');
                const lines = isDevice
                    ? [
                        `<strong>${p.location_name}</strong>`,
                        `${typeLabel[p.site_type] ?? p.site_type ?? ''}`,
                        where,
                        `Site health: <span style="color:${statusColors[p.daily_status] ?? '#334155'};font-weight:600">${(p.daily_status ?? '').replace('_', ' ')}</span>`,
                        `<strong>${p.device_count}</strong> deployed unit${p.device_count === 1 ? '' : 's'}`,
                        ...(p.devices ?? []).map((d) => `&nbsp;· ${d.asset_tag} — ${d.model}`),
                        `<a href="${route('sites.show', p.site_id)}">Site</a>`,
                    ]
                    : [
                        `<strong>${p.location_name}</strong>`,
                        p.project_name,
                        `Status: ${p.status}`,
                        where,
                        `<a href="${route('sites.show', p.id)}">Site</a>`,
                    ];
                layer.bindPopup(`<div class="text-sm leading-snug">${lines.filter(Boolean).join('<br>')}</div>`, { sticky: true });
                // Hover previews the details; click pins the popup (touch-friendly).
                layer.on('mouseover', () => layer.openPopup());
                layer.on('mouseout', () => {
                    if (!layer.isPopupOpen() || !layer._clickPinned) layer.closePopup();
                });
                layer.on('click', () => {
                    layer._clickPinned = true;
                    layer.openPopup();
                });
                layer.getPopup().on('remove', () => (layer._clickPinned = false));
            },
        });

        // Cluster when markercluster is present; plain layer otherwise.
        markerLayer = typeof L.markerClusterGroup === 'function' ? L.markerClusterGroup() : L.layerGroup();
        markerLayer.addLayer(geoJson);
        map.addLayer(markerLayer);
    }

    function clearBoundaries() {
        if (boundaryLayer) {
            map.removeLayer(boundaryLayer);
            boundaryLayer = null;
        }
    }

    // level: which filter the polygons represent; selectedName: the active
    // filter value to highlight. onPick(name) implements click-to-filter.
    function renderBoundaries(featureCollection, { level, selectedName, onPick }) {
        clearBoundaries();
        if (!featureCollection?.features?.length) {
            return;
        }

        boundaryLayer = L.geoJSON(featureCollection, {
            style: (feature) => (feature.properties.name === selectedName
                ? { fillColor: '#0F1B2D', fillOpacity: 0.3, color: '#0F1B2D', weight: 2 }
                : { fill: false, color: '#64748b', weight: 1, opacity: 0.55 }),
            onEachFeature: (feature, layer) => {
                layer.bindTooltip(feature.properties.name, { sticky: true });
                layer.on('click', () => onPick(feature.properties.name));
                layer.on('mouseover', () => layer.setStyle({ weight: 2, opacity: 1 }));
                layer.on('mouseout', () => boundaryLayer.resetStyle(layer));
            },
        });
        boundaryLayer.level = level;
        // Under the point markers.
        boundaryLayer.addTo(map);
        boundaryLayer.eachLayer((l) => l.bringToBack?.());
    }

    function fitToBoundaries() {
        if (boundaryLayer) {
            map.fitBounds(boundaryLayer.getBounds(), { padding: [24, 24], maxZoom: 14 });
            return true;
        }
        return false;
    }

    // Fallback focus when no polygons exist for the current drill level.
    function fitToMarkers() {
        if (markerLayer?.getLayers?.().length) {
            map.fitBounds(markerLayer.getBounds(), { padding: [24, 24], maxZoom: 14 });
        }
    }

    return { mapRef, init, destroy, renderMarkers, clearMarkers, renderBoundaries, clearBoundaries, fitToBoundaries, fitToMarkers };
}
