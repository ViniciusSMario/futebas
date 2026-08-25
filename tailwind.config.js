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
                sans: ['Figtree', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                pitch: {
                    50: '#eef2f0',
                    100: '#d7e3dc',
                    200: '#aec7ba',
                    300: '#84a897',
                    400: '#5c8272',
                    500: '#3f6353',
                    600: '#2f4c40',
                    700: '#233830',
                    800: '#182722',
                    900: '#101c18',
                    950: '#080f0c',
                },
            },
        },
    },

    plugins: [forms],
};
