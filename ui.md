# EventON APIfy UI Notes

## Settings Header

- Use the banner image at `assets/eventon-apify-settings-banner.svg` at the top of the settings page.
- The banner should keep the current visual direction:
  - EventON APIfy wordmark
  - calendar/API logo treatment
  - short one-line product explanation with a lighter secondary line
- Below the banner, keep a compact metadata row with these items:
  - `Plugin Repository`
  - current plugin version
  - author GitHub link
  - single-button link: `Updates via Git Updater`

## Settings Intro Copy

- Keep the page title `EventON APIfy Settings`.
- Keep the short descriptive paragraph about controlling the custom EventON REST API surface, `wp/v2` compatibility, and discovery docs.
- Keep the MCP note directly below the intro:
  - this plugin enables using MCP
  - extended MCP server link: `https://github.com/renatobo/mcp-wp-cpt`

## Tabs

- Use native WordPress tab markup:
  - `nav-tab-wrapper`
  - `nav-tab`
  - `nav-tab-active`
- Tabs should remain in this order:
  - `Event API`
  - `WP v2 compatibility`
  - `MCP schema manifest`
  - `Request fields`
  - `Application Passwords`
- Tabs are in-page panels, not separate admin pages.
- Switching tabs should:
  - show only the active panel
  - hide inactive panels with the `hidden` attribute
  - update the URL hash
  - restore the active tab from the URL hash on load

## Panel Layout

- Keep the layout WordPress-admin friendly, not app-like.
- Prefer flat cards, subtle borders, and native admin spacing.
- Keep toggles and capability controls on the `Event API` tab.
- Keep `WP v2 compatibility`, `MCP schema manifest`, `Request fields`, and `Application Passwords` as separate tabs.

## Maintenance

- When the header or tabs design changes, update this file in the same change.
