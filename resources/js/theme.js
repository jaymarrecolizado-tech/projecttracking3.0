// Brand tokens for JS-land (Inertia progress bar, charts, Leaflet styles).
// Keep in sync with the `accent`/`ink` scales in tailwind.config.js — the
// class-based utilities live there; these are the raw values non-CSS code
// needs (Plan_revision §Phase 5.1).
export const ACCENT = '#0E5E6F';
export const ACCENT_DARK = '#0a414c';
export const INK = '#0F1B2D';

// Site-health hues are data, not decoration: map dots, cluster bubbles and
// the legend all read from here (Plan_ui.md Slice 7).
export const STATUS_COLORS = {
    UP: '#059669',
    DOWN: '#dc2626',
    DOWN_SERVER: '#dc2626',
    NO_NMS: '#d97706',
    NO_DATA: '#94a3b8',
};
