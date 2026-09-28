/**
 * Intentionally empty.
 *
 * This app writes plain CSS in `app/globals.css` — the design tokens from
 * Plan_UI.md Part I §1 — and pulls in no CSS framework, so Next needs no
 * PostCSS plugins.
 *
 * The file exists to *stop* the lookup walking up into the parent Laravel
 * project, which has a Tailwind + autoprefixer config. Inheriting it made the
 * build fail inside next/font, and it would have silently applied the
 * console's Tailwind build to a surface that is not supposed to use it.
 */
module.exports = {
  plugins: {},
};
