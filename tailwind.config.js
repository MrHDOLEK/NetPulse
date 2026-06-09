/** @type {import('tailwindcss').Config} */
module.exports = {
  content: ["./templates/**/*.twig", "./assets/**/*.js"],
  darkMode: ["selector", '[data-theme="dark"]'],
  theme: {
    extend: {
      colors: {
        accent: "var(--accent)",
        "accent-strong": "var(--accent-strong)",
        "accent-dim": "var(--accent-dim)",
        good: "var(--good)",
        warn: "var(--warn)",
        bad: "var(--bad)",
        violet: "var(--violet)",
        amber: "var(--amber)",
        bg: "var(--bg)",
        "bg-2": "var(--bg-2)",
        ink: "var(--text)",
        dim: "var(--text-dim)",
        faint: "var(--text-faint)",
        line: "var(--border)",
        "line-2": "var(--border-strong)",
        surface: "var(--surface)",
        "surface-2": "var(--surface-2)",
        "surface-hover": "var(--surface-hover)",
      },
      fontFamily: {
        sans: ["IBM Plex Sans", "sans-serif"],
        mono: ["IBM Plex Mono", "monospace"],
      },
    },
  },
  plugins: [],
};
