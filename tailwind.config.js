/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './app/Views/**/*.php',
    './public/assets/js/**/*.js',
  ],
  theme: {
    extend: {
      colors: {
        // Brand palette derived from the SSJ logo — royal blue #2b4a9e + red #d81f26.
        brand: {
          50:  '#eef1fb',
          100: '#dde3f6',
          200: '#bfcaee',
          300: '#97a8df',
          400: '#6a80cc',
          500: '#2b4a9e',   // logo blue — primary
          600: '#26418b',
          700: '#203674',
          800: '#1c2f61',
          900: '#14213f',   // ink navy
        },
        // Accent = the SSJ logo red. (Key name kept as `teal` so existing
        // `teal-*` utilities across the templates re-theme to red on rebuild.)
        teal: {
          400: '#e5484d',
          500: '#d81f26',   // logo red
          600: '#b3161c',
        },
        ink: '#14213f',
      },
      fontFamily: {
        sans: ['Inter', 'ui-sans-serif', 'system-ui', '-apple-system', 'Segoe UI', 'Roboto', 'Helvetica', 'Arial', 'sans-serif'],
        display: ['"Fraunces"', 'Georgia', 'ui-serif', 'serif'],
      },
      maxWidth: {
        content: '1200px',
      },
      boxShadow: {
        card: '0 1px 2px rgba(11,31,58,.06), 0 8px 24px -12px rgba(11,31,58,.18)',
      },
    },
  },
  plugins: [
    require('@tailwindcss/typography'),
  ],
};
