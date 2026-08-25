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
                sans: ['Manrope', 'Segoe UI', ...defaultTheme.fontFamily.sans],
            },
            boxShadow: {
                panel: '0 20px 40px -20px rgb(28 85 165 / 0.18)',
                soft: '0 10px 30px -12px rgb(15 23 42 / 0.12)',
            },
            colors: {
                'brand-navy': {
                    50: '#f3f7fc',
                    100: '#e4eef8',
                    200: '#c5daf0',
                    300: '#93b9e0',
                    400: '#5a92cb',
                    500: '#3771b8',
                    600: '#1c55a5',
                    700: '#164789',
                    800: '#153b70',
                    900: '#15335d',
                    950: '#0e2140',
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
