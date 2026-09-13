import { describe, expect, it } from 'vitest';
import { clusterStats } from './Pages/Map/useLeafletMap.js';

// Bubble math for the status cluster icons (Plan_ui.md Slice 7): count,
// dominant status, and its share. One runnable check on the pure helper.
describe('clusterStats', () => {
    it('counts sites and reports the dominant share', () => {
        expect(clusterStats(['UP', 'UP', 'DOWN'])).toMatchObject({
            count: 3,
            label: '3',
            dominant: 'UP',
            pct: 67,
        });
    });

    it('breaks ties toward the worse status', () => {
        expect(clusterStats(['UP', 'DOWN']).dominant).toBe('DOWN');
        expect(clusterStats(['UP', 'NO_DATA']).dominant).toBe('NO_DATA');
    });

    it('folds DOWN_SERVER into DOWN and unknowns into NO_DATA', () => {
        expect(clusterStats(['DOWN_SERVER', 'BOGUS']).dominant).toBe('DOWN');
    });

    it('compacts thousands and handles the empty case', () => {
        expect(clusterStats(Array(1500).fill('UP')).label).toBe('1.5k');
        expect(clusterStats([])).toMatchObject({ count: 0, pct: 0 });
    });
});
