/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./index.html",
    "./src/**/*.{js,ts,jsx,tsx}",
  ],
  theme: {
    extend: {
      colors: {
        primary: {
          DEFAULT: '#4f46e5',
          hover: '#4338ca',
        },
        secondary: '#64748b',
        success: '#10b981',
        border: '#e2e8f0',
        bg: '#f8fafc',
        'card-bg': '#ffffff',
      }
    },
  },
  plugins: [],
}
