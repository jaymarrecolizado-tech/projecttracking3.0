import { describe, expect, it } from 'vitest';
import { escapeHtml } from './Pages/Map/mapHelpers.js';

// Leaflet popups render HTML strings: unescaped site/device names become
// markup (stored XSS via manual entry or Excel imports). One runnable check.
describe('escapeHtml', () => {
    it('escapes the five markup-significant characters', () => {
        expect(escapeHtml(`<a href="x">&'y'</a>`)).toBe(
            '&lt;a href=&quot;x&quot;&gt;&amp;&#39;y&#39;&lt;/a&gt;',
        );
    });

    it('leaves plain names untouched', () => {
        expect(escapeHtml('Aparri West')).toBe('Aparri West');
    });

    it('coerces nullish values to an empty string', () => {
        expect(escapeHtml(null)).toBe('');
        expect(escapeHtml(undefined)).toBe('');
    });

    it('neutralizes script injection payloads', () => {
        expect(escapeHtml('<script>alert(1)</script>')).not.toContain('<script>');
    });
});
