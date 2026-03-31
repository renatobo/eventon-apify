# EventON APIfy UI Notes

## Settings Header

- Use the banner image at `assets/eventon-apify-settings-banner.svg` at the top of the settings page.
- Render the banner at its intended width instead of stretching it to the full admin container.
  - keep the header image capped near the SVG's native width (`~750px`)
  - allow it to shrink on narrower screens without scaling larger than its authored size
- The banner should keep the current visual direction:
  - EventON APIfy wordmark
  - calendar/API logo treatment
  - short one-line product explanation with a lighter secondary line
- Below the banner, keep a compact metadata row with these items:
  - `GitHub Repository`
  - current plugin version
  - author GitHub link
  - single-button link: `GitHub updates via Git Updater`

## Settings Intro Copy

- Keep the page title `EventON APIfy Settings`.
- Keep the short descriptive paragraph about controlling the custom EventON REST API surface, `wp/v2` compatibility, and discovery docs.
- Keep a secondary note that the plugin is intended for dual distribution through GitHub Releases and WordPress.org.
  - GitHub Releases is the active install/update channel today
  - WordPress.org is the planned secondary channel and should not be described as live unless that build exists
  - Git Updater should be named explicitly as the GitHub update mechanism
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
  - `API Specs`
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
- Keep `WP v2 compatibility`, `API Specs`, `MCP schema manifest`, `Request fields`, and `Application Passwords` as separate tabs.
- Keep the `API Specs` tab focused on checked-in artifact links for:
  - the OpenAPI spec file
  - the Postman collection
  - the current site REST root / Postman `baseUrl` value

## Maintenance

- The plugin updates row should use the standard WordPress plugin asset filenames:
  - `assets/icon.svg`
  - `assets/icon-128x128.png`
  - `assets/icon-256x256.png`
- Keep those icon assets aligned with the primary logo artwork in `assets/eventon-apify-logo.svg`.
- When cutting a release, keep these version references synchronized:
  - `eventon-apify.php` plugin header `Version`
  - `eventon-apify.php` constant `EVENTON_APIFY_VERSION`
  - `readme.txt` `Stable tag`
- The compatibility label shown in the updates UI depends on `readme.txt` metadata:
  - `Tested up to` should be updated when the plugin is verified on a newer WordPress release
  - `Unknown` is expected when the site version is newer than the published `Tested up to` value
- When the header or tabs design changes, update this file in the same change.
