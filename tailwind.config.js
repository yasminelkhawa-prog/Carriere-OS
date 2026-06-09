import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/**/*.blade.php',
        './resources/**/*.js',
        './resources/**/*.vue',
    ],
    theme: {
        extend: {
            colors: {
                primary: {
                    50: '#f3f0ff',
                    100: '#e7ddff',
                    200: '#cfbeff',
                    300: '#b091ff',
                    400: '#8b5cf6',
                    500: '#7c3aed',
                    600: '#6d28d9',
                    700: '#5b21b6',
                    800: '#4c1d95',
                    900: '#3f1b80',
                },
                success: {
                    50: '#ecfdf3',
                    100: '#d1fae5',
                    200: '#a7f3d0',
                    300: '#6ee7b7',
                    400: '#34d399',
                    500: '#10b981',
                    600: '#059669',
                    700: '#047857',
                    800: '#065f46',
                    900: '#064e3b',
                },
                danger: {
                    50: '#fef2f2',
                    100: '#fee2e2',
                    200: '#fecaca',
                    300: '#fca5a5',
                    400: '#f87171',
                    500: '#ef4444',
                    600: '#dc2626',
                    700: '#b91c1c',
                    800: '#991b1b',
                    900: '#7f1d1d',
                },
                aura: {
                    50: '#f3f0ff',
                    100: '#e7ddff',
                    200: '#cfbeff',
                    300: '#b091ff',
                    400: '#8b5cf6',
                    500: '#7c3aed',
                    600: '#6d28d9',
                    700: '#5b21b6',
                    800: '#4c1d95',
                    900: '#3f1b80',
                },
            },
            boxShadow: {
                aura: '0 20px 55px rgba(109, 40, 217, 0.35)',
            },
        },
    },
    plugins: [forms],
};
