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
            colors: {
                'brand-navy': {
                    50: '#f4f6f9',
                    100: '#e2e7ef',
                    200: '#c5cdd8',
                    300: '#9aa3b2',
                    400: '#6c7382',
                    500: '#3771b8',
                    600: '#1c55a5',
                    700: '#2a3445',
                    800: '#1d2533',
                    900: '#11161f',
                    950: '#070a10',
                },
                'brand-orange': {
                    50: '#fff7ed',
                    100: '#ffe3b8',
                    300: '#fbc373',
                    500: '#f0941d',
                    600: '#d97d0a',
                    700: '#a85f06',
                },
            },
        },
    },

    plugins: [forms],
};
