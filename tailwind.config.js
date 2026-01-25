/** @type {import('tailwindcss').Config} */
const defaultTheme = require('tailwindcss/defaultTheme');

module.exports = {
    content: [
        "./resources/**/*.blade.php",
        "./resources/**/*.js",
    ],
    darkMode: 'class',

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
                primary: '#C9A66B',       // Das klassische Gold aus deinem Logo
                'primary-dark': '#1A1A1A',  // Ein edles Tiefschwarz (für Kontraste & Eleganz)
                'primary-light': '#E0C28F'  // Ein sanftes, helles Gold (für Hintergründe/Hover)
            },
        },
    },

    variants: {
        extend: {
            opacity: ['disabled'],
        },
    },

}
