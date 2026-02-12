/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./index.php",
    "./**/*.php",
    "./assets/js/**/*.js",
    "./admin/**/*.php",
    "./api/**/*.php",
    "./models/**/*.php"
  ],
  theme: {
    extend: {
      colors: {
        primary: '#FF5722',
        'gray-50': '#FAFAFA',
        'gray-100': '#F5F5F5',
        'gray-200': '#EEEEEE',
        'gray-300': '#E0E0E0',
        'gray-400': '#BDBDBD',
        'gray-500': '#9E9E9E',
        'gray-600': '#757575',
        'gray-700': '#616161',
        'gray-800': '#424242',
        'gray-900': '#212121',
        white: '#FFFFFF',
        black: '#000000',
      },
      borderRadius: {
        xl: '1rem',
        '2xl': '1.5rem',
      },
      boxShadow: {
        sm: '0 1px 2px 0 rgba(0, 0, 0, 0.05)',
        md: '0 4px 6px -1px rgba(0, 0, 0, 0.1)',
        lg: '0 10px 15px -3px rgba(0, 0, 0, 0.1)',
      },
      fontFamily: {
        sans: ['Inter', 'DM Sans', 'sans-serif'],
        display: ['League Spartan', 'sans-serif'],
      },
    },
  },
  plugins: [],
}
