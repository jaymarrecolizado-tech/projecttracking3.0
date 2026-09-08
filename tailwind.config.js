import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            // `spacing` (not `width`) so both w-sidebar AND ml-sidebar utilities generate.
            spacing: {
                'sidebar': '16rem',
            },
            // Brand tokens (Plan_revision §Phase 5.1) — the only place the
            // literals live; utilities like bg-accent / text-accent-ink work
            // for every shade number.
            colors: {
                'accent': {
                    '50': '#e6f2f4',
                    '100': '#c2dce1',
                    '200': '#8fb9c2',
                    '300': '#5c96a3',
                    '400': '#2a7383',
                    '500': '#0E5E6F',
                    '600': '#0a414c',
                    '700': '#083440',
                    '800': '#062733',
                    '900': '#041a26',
                },
                'ink': '#0F1B2D',
            },
        },
    },

    plugins: [forms],
};
