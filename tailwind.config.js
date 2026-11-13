import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                head: ['Sora', ...defaultTheme.fontFamily.sans],
                mono: ['Roboto Mono', ...defaultTheme.fontFamily.mono],
            },
            colors: {
                // MyPetakom design palette (approximated from the original oklch tokens)
                brand: {
                    50: '#eef3fd',
                    100: '#dbe6fa',
                    200: '#b9cdf5',
                    300: '#8aa9ec',
                    400: '#5b82db',
                    500: '#2f5bbf',
                    600: '#1f48a0',
                    700: '#153680',
                    800: '#0e2862',
                    900: '#091c44',
                    vibrant: '#1f4690',
                },
                gold: {
                    50: '#fdf7e3',
                    300: '#f3d777',
                    500: '#e0b12c',
                    700: '#8a6a0a',
                },
                danger: {
                    50: '#fdeeed',
                    500: '#e0463f',
                    600: '#c92f2a',
                    700: '#a11f1d',
                },
                ok: {
                    50: '#e9f7ee',
                    500: '#2f9e5f',
                    600: '#237a49',
                },
            },
        },
    },

    plugins: [forms],
};
