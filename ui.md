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
  - `Release notes`
  - author GitHub link
  - single-button link: `GitHub updates via Git Updater`
- The `Release notes` link should point to the tagged GitHub release for the current plugin version:
  - `https://github.com/renatobo/eventon-apify/releases/tag/vX.Y.Z`

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
  - keep the active tab after saving
- Tab state survives a save because `admin-settings.js` writes the active panel
  into the `_wp_http_referer` hidden field. `options.php` redirects to
  `add_query_arg('settings-updated', 'true', wp_get_referer())`, and
  `add_query_arg()` preserves a fragment, so the hash comes back with the
  redirect. A fragment never reaches the server on its own, so removing that
  sync silently drops the user back on `Event API` after every save.

## Notices and Feedback

- Do not call `settings_errors()` in the page callback. `add_options_page()`
  puts this screen under `options-general.php`, so core's `admin-header.php`
  loads `options-head.php`, which already renders them. A second call prints
  "Settings saved." twice.
- Panels are hand-rolled rather than registered via `add_settings_section()` /
  `add_settings_field()`. Do not add `do_settings_sections()`: it takes a page
  slug, not a settings group, so it renders nothing here.
- Copy buttons use `data-eventon-apify-copy="<element id>"` with a single
  delegated listener; no inline `onclick`. They confirm by swapping the button
  label for two seconds, and fall back to `document.execCommand('copy')` because
  `navigator.clipboard` is undefined on insecure origins. Feedback strings are
  localized through `wp_localize_script()` as `eventonApifySettings`.

## Panel Layout

- Keep the layout WordPress-admin friendly, not app-like.
- Prefer flat cards, subtle borders, and native admin spacing.
- Keep toggles and capability controls on the `Event API` tab.
- Keep `WP v2 compatibility`, `API Specs`, `MCP schema manifest`, `Request fields`, and `Application Passwords` as separate tabs.
- Describe the MCP schema manifest as read-only discovery that still requires an authenticated administrator; do not describe either manifest route as public.
- Keep the `API Specs` tab focused on checked-in artifact links for:
  - the OpenAPI spec file
  - the Postman collection
  - the current site REST root / Postman `baseUrl` value
- Describe MCP discovery as administrator-authenticated; it uses the same
  Application Password and `manage_options` policy as the protected event API.

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
