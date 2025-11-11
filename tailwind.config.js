/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./public/**/*.php",        // Scan all PHP files in public and subfolders
    "./src/templates/**/*.php" // Scan all PHP files in templates
  ],
  theme: {
    extend: {},
  },
  plugins: [],
}