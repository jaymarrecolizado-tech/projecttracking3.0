// Pure map helpers — deliberately Leaflet-free so Vitest can import them in
// node (leaflet touches `window` at import time). useLeafletMap.js owns the
// Leaflet wiring and imports from here.

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

// Cluster bubble math: how many sites, which status dominates, and its
// share. Ties break toward the worse status so a half-down area never
// reads green.
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

// Health bucket for the legend chips: the five daily statuses collapse to
// four legend buckets; unknowns read as NO_DATA.
export function healthBucket(status) {
    if (status === 'UP') return 'UP';
    if (status === 'DOWN' || status === 'DOWN_SERVER') return 'DOWN';
    if (status === 'NO_NMS') return 'NO_NMS';
    return 'NO_DATA';
}
