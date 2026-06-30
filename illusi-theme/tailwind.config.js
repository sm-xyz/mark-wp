/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./**/*.php",
    "./src/js/**/*.js",
    "../**/*.html"
  ],
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        primary: {
          DEFAULT: 'var(--color-primary, #2563eb)',
          hover: 'var(--color-primary-hover, #1d4ed8)',
          text: 'var(--color-primary-text, #ffffff)'
        },
        header: {
          bg: 'var(--color-header-bg, #ffffff)',
          text: 'var(--color-header-text, #334155)',
          hover: 'var(--color-header-hover, #2563eb)',
          darkbg: 'var(--color-header-darkbg, #1e293b)'
        }
      }
    },
  },
  plugins: [],
}
