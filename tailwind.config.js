/** @type {import('tailwindcss').Config} */
const defaultTheme = require('tailwindcss/defaultTheme');

module.exports = {
    content: [
        "./resources/**/*.blade.php",
        "./resources/**/*.js",
    ],
    darkMode: 'class',

    // Die Safelist schützt deine dynamischen Livewire-Farben davor, gelöscht zu werden
    safelist: [
        {
            pattern: /(bg|text|border|shadow|ring)-(cyan|emerald|blue|indigo|purple|pink|rose|red|orange|amber|yellow|green)-(400|500|600|900)/,
            variants: ['hover', 'focus', 'peer-checked', 'group-hover'],
        }
    ],

    theme: {
        extend: {
            animation: {
                'fade-in': 'fadeIn 0.8s ease-out forwards',
            },
            keyframes: {
                fadeIn: {
                    '0%': { opacity: 0 },
                    '100%': { opacity: 1 },
                },
            },
            colors: {
                primary: '#C9A66B',         // Das klassische Gold aus deinem Logo
                'primary-dark': '#1A1A1A',  // Ein edles Tiefschwarz
                'primary-light': '#E0C28F', // Ein sanftes, helles Gold
                'custom-blue': '#1fb6ff',   // Neu
                'custom-purple': '#7e5bef', // Neu
                brand: {                    // Neu
                    light: '#3fbaeb',
                    DEFAULT: '#0fa9e6',
                    dark: '#0c87b8',
                },
            },
        },
    },

    variants: {
        extend: {
            opacity: ['disabled'],
        },
    },
}
