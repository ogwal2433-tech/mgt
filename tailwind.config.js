import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.{ts,tsx}',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                display: ['"Plus Jakarta Sans"', 'Inter', ...defaultTheme.fontFamily.sans],
                mono: ['"JetBrains Mono"', ...defaultTheme.fontFamily.mono],
            },
            fontSize: {
                '2xs': ['0.6875rem', { lineHeight: '1rem', letterSpacing: '0.02em' }],
                xs: ['0.75rem', { lineHeight: '1.125rem', letterSpacing: '0.01em' }],
                sm: ['0.8125rem', { lineHeight: '1.25rem' }],
                base: ['0.875rem', { lineHeight: '1.5rem' }],
                lg: ['1rem', { lineHeight: '1.625rem' }],
                xl: ['1.125rem', { lineHeight: '1.6875rem', letterSpacing: '-0.011em' }],
                '2xl': ['1.375rem', { lineHeight: '1.875rem', letterSpacing: '-0.014em' }],
                '3xl': ['1.75rem', { lineHeight: '2.125rem', letterSpacing: '-0.02em' }],
                '4xl': ['2.125rem', { lineHeight: '2.5rem', letterSpacing: '-0.022em' }],
                '5xl': ['2.75rem', { lineHeight: '3.125rem', letterSpacing: '-0.025em' }],
            },
            letterSpacing: {
                tighter: '-0.022em',
                tight: '-0.014em',
                normal: '-0.006em',
                wide: '0.02em',
                wider: '0.055em',
                widest: '0.09em',
            },
            fontWeight: {
                normal: '400',
                medium: '500',
                semibold: '600',
                bold: '700',
                extrabold: '800',
            },
            colors: {
                ink: {
                    50: '#f7f8f8',
                    100: '#eef0f1',
                    200: '#dfe3e5',
                    300: '#c3cbd0',
                    400: '#8b98a1',
                    500: '#61707c',
                    600: '#47535d',
                    700: '#37414a',
                    800: '#232b32',
                    900: '#141a1f',
                    950: '#0b1014',
                },
                brand: {
                    50: '#f0fdf4',
                    100: '#dcfce7',
                    200: '#bbf7d0',
                    300: '#86efac',
                    400: '#4ade80',
                    500: '#22c55e',
                    600: '#16a34a',
                    700: '#15803d',
                    800: '#166534',
                    900: '#14532d',
                    950: '#052e16',
                },
            },
            typography: () => ({}),
        },
    },

    plugins: [forms],
};
