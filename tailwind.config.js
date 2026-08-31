/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './app/Views/**/*.php',
    './public/assets/js/**/*.js',
  ],
  theme: {
    extend: {
      colors: {
        // Premium pharmaceutical palette — deep, trustworthy blues + a clinical teal.
        brand: {
          50:  '#eef6fc',
          100: '#d6e9f7',
          200: '#aed3ef',
          300: '#7db6e3',
          400: '#4a93d1',
          500: '#2575ba',   // primary
          600: '#1b5c99',
          700: '#174c7d',
          800: '#153f66',
          900: '#0b2a4a',   // ink navy
        },
        teal: {
          400: '#2fbfb0',
          500: '#12a99a',
          600: '#0e8a7f',
        },
        ink: '#0b1f3a',
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
