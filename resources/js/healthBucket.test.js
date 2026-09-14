import { describe, expect, it } from 'vitest';
import { healthBucket } from './Pages/Map/mapHelpers.js';

// Legend chip buckets (Plan_ui.md Slice 8): five daily statuses collapse
// to four chips; anything unexpected reads as NO_DATA, never vanishes.
describe('healthBucket', () => {
    it('passes UP and NO_NMS through', () => {
        expect(healthBucket('UP')).toBe('UP');
        expect(healthBucket('NO_NMS')).toBe('NO_NMS');
    });

    it('folds DOWN_SERVER into DOWN', () => {
        expect(healthBucket('DOWN')).toBe('DOWN');
        expect(healthBucket('DOWN_SERVER')).toBe('DOWN');
    });

    it('defaults unknowns to NO_DATA', () => {
        expect(healthBucket('NO_DATA')).toBe('NO_DATA');
        expect(healthBucket(undefined)).toBe('NO_DATA');
        expect(healthBucket('BOGUS')).toBe('NO_DATA');
    });
});
