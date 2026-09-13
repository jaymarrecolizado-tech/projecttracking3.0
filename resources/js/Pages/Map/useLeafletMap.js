import { ref } from 'vue';
import { INK, STATUS_COLORS } from '../../theme';
import './clusters.css';

// Leaflet popups render HTML strings, so every database-derived value is
// escaped first — site/device names arrive from manual entry and Excel
// imports and must never become markup.
export function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#39;');
}

// Cluster bubble math (pure — covered by Vitest): how many sites, which
// status dominates, and its share. Ties break toward the worse status so a
// half-down area never reads green.
const SEVERITY_ORDER = ['DOWN', 'NO_NMS', 'NO_DATA', 'UP'];

export function clusterStats(statuses) {
    const counts = { UP: 0, DOWN: 0, NO_NMS: 0, NO_DATA: 0 };
    for (const raw of statuses) {
        const s = raw === 'DOWN_SERVER' ? 'DOWN' : raw;
        counts[s in counts ? s : 'NO_DATA']++;
    }
    const total = statuses.length;
    let dominant = 'NO_DATA';
    let best = -1;
    for (const s of SEVERITY_ORDER) {
        if (counts[s] > best) {
            best = counts[s];
            dominant = s;
        }
    }
    return {
        count: total,
        label: total >= 1000 ? `${(total / 1000).toFixed(1)}k` : `${total}`,
        dominant,
        pct: total > 0 ? Math.round((best / total) * 100) : 0,
    };
}

// Health bucket for the legend chips (pure — covered by Vitest): the five
// daily statuses collapse to four legend buckets; unknowns read as NO_DATA.
export function healthBucket(status) {
    if (status === 'UP') return 'UP';
    if (status === 'DOWN' || status === 'DOWN_SERVER') return 'DOWN';
    if (status === 'NO_NMS') return 'NO_NMS';
    return 'NO_DATA';
}

// Leaflet wiring for Map View (Plan §Map 3): init, deployed-device markers
// with clustering, and the boundary polygon layer with highlight +
// click-to-filter. Leaflet loads globally via app.blade.php.
export function useLeafletMap(containerRef) {
    const mapRef = ref(null);
    let map = null;
    let markerLayer = null;
    let boundaryLayer = null;

    // Status hues live in theme.js — one source for dots, bubbles, legend.
    const statusColors = STATUS_COLORS;

    // Cluster bubble icon: count + dominant-status % (Slice 7). Reads the
    // child markers' GeoJSON daily_status — no backend change.
    function bubbleIcon(cluster) {
        const { label, dominant, pct } = clusterStats(
            cluster.getAllChildMarkers().map((m) => m.feature?.properties?.daily_status),
        );
        const count = cluster.getChildCount();
        const size = count >= 100 ? 60 : count >= 10 ? 50 : 40;
        const cls = dominant.toLowerCase();
        return L.divIcon({
            html: `<div class="map-cluster st-${cls} ${size >= 60 ? 'sz-l' : size >= 50 ? 'sz-m' : 'sz-s'}"><span class="n">${label}</span><span class="pct">${pct}%</span></div>`,
            className: 'map-cluster-wrap',
            iconSize: L.point(size, size),
        });
    }

    function init() {
        if (map || !containerRef.value) {
            return;
        }
        map = L.map(containerRef.value, { zoomControl: true }).setView([16.9, 121.8], 7);
        L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors &copy; <a href="https://carto.com/attributions">CARTO</a>',
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

    function renderMarkers(data, { typeLabel = {}, maxClusterRadius = 80 } = {}) {
        clearMarkers();
        const style = { radius: 10, color: '#fff', weight: 2, opacity: 1, fillOpacity: 0.9 };

        const geoJson = L.geoJSON(data, {
            pointToLayer: (feature, latlng) => {
                const p = feature.properties;
                return L.circleMarker(latlng, {
                    ...style,
                    fillColor: statusColors[p.daily_status] ?? p.marker_color ?? '#64748b',
                });
            },
            onEachFeature: (feature, layer) => {
                const p = feature.properties;
                const isDevice = p.device_count !== undefined;
                const where = [p.barangay, p.municipality, p.province].filter(Boolean).map(escapeHtml).join(', ');
                const status = escapeHtml((p.daily_status ?? '').replace('_', ' '));
                const lines = isDevice
                    ? [
                        `<strong>${escapeHtml(p.location_name)}</strong>`,
                        `${escapeHtml(typeLabel[p.site_type] ?? p.site_type ?? '')}`,
                        where,
                        `Site health: <span style="color:${statusColors[p.daily_status] ?? '#334155'};font-weight:600">${status}</span>`,
                        `<strong>${p.device_count}</strong> deployed unit${p.device_count === 1 ? '' : 's'}`,
                        ...(p.devices ?? []).map((d) => `&nbsp;· ${escapeHtml(d.asset_tag)} — ${escapeHtml(d.model)}`),
                        `<a href="${route('sites.show', p.site_id)}">Site</a>`,
                    ]
                    : [
                        `<strong>${escapeHtml(p.location_name)}</strong>`,
                        escapeHtml(p.project_name),
                        `Status: ${escapeHtml(p.status)}`,
                        where,
                        `<a href="${route('sites.show', p.id)}">Site</a>`,
                    ];
                layer.bindPopup(`<div class="text-sm leading-snug"><div style="height:6px;border-radius:9999px;background:${statusColors[healthBucket(p.daily_status)]};margin-bottom:8px" aria-hidden="true"></div>${lines.filter(Boolean).join('<br>')}</div>`, { sticky: true });
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
        markerLayer = typeof L.markerClusterGroup === 'function'
            ? L.markerClusterGroup({ iconCreateFunction: bubbleIcon, maxClusterRadius })
            : L.layerGroup();
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
                ? { fillColor: INK, fillOpacity: 0.3, color: INK, weight: 2 }
                : { fill: false, color: '#cbd5e1', weight: 1, opacity: 0.8 }),
            onEachFeature: (feature, layer) => {
                layer.bindTooltip(escapeHtml(feature.properties.name), { sticky: true });
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
