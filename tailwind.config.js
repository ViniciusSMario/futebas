import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
    ],

    theme: {
        extend: {
            // Small phones (iPhone SE) are ~375px wide. `xs` gives a hook
            // for the step between "cramped" and "comfortable" that sits
            // below Tailwind's 640px `sm`.
            screens: {
                xs: '400px',
            },

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

            borderRadius: {
                '4xl': '2rem',
            },

            boxShadow: {
                // Elevation on a near-black surface can't rely on a soft
                // grey shadow — it needs a lit rim to read at all.
                card: '0 1px 2px rgb(0 0 0 / 0.5), 0 12px 28px -16px rgb(0 0 0 / 0.8)',
                glow: '0 0 0 1px rgb(16 185 129 / 0.35), 0 20px 44px -14px rgb(16 185 129 / 0.35)',
                'glow-red': '0 0 0 1px rgb(239 68 68 / 0.35), 0 20px 44px -14px rgb(239 68 68 / 0.4)',
                'glow-amber': '0 0 0 1px rgb(245 158 11 / 0.35), 0 20px 44px -14px rgb(245 158 11 / 0.35)',
            },

            keyframes: {
                float: {
                    '0%, 100%': { transform: 'translateY(0) rotate(0deg)' },
                    '50%': { transform: 'translateY(-16px) rotate(6deg)' },
                },
                'pulse-soft': {
                    '0%, 100%': { opacity: '0.35', transform: 'scale(1)' },
                    '50%': { opacity: '0.7', transform: 'scale(1.05)' },
                },
                'slide-up': {
                    from: { opacity: '0', transform: 'translateY(28px)' },
                    to: { opacity: '1', transform: 'translateY(0)' },
                },
                shimmer: {
                    '0%': { backgroundPosition: '-200% center' },
                    '100%': { backgroundPosition: '200% center' },
                },
                marquee: {
                    from: { transform: 'translateX(0)' },
                    to: { transform: 'translateX(-50%)' },
                },
            },

            animation: {
                float: 'float 6s ease-in-out infinite',
                'float-slow': 'float 9s ease-in-out infinite',
                'pulse-soft': 'pulse-soft 3.5s ease-in-out infinite',
                'slide-up': 'slide-up 0.75s cubic-bezier(0.22, 1, 0.36, 1) both',
                shimmer: 'shimmer 3s ease-in-out infinite',
                marquee: 'marquee 38s linear infinite',
            },
        },
    },

    plugins: [forms],
};
