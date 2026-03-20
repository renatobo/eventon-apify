=== EventON APIfy ===
Contributors: renatobo
Tags: eventon, api, rest-api, events
Requires at least: 6.0
Tested up to: 6.9.4
Requires PHP: 8.0
Stable tag: 1.3.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Protected REST API endpoints for EventON events with administrator-only CRUD and optional wp/v2 compatibility.

== Description ==

EventON APIfy adds protected REST endpoints for working with EventON `ajde_events` posts from external systems.

Access control:
- Endpoint access is restricted to authenticated administrators.
- Endpoint availability can be enabled or disabled in plugin settings.
- Individual capabilities can be enabled or disabled for list, read, create, update, delete, RSVP summary, and RSVP attendee operations.

Routes:
- `GET /wp-json/eventonapify/v1/events`
- `GET /wp-json/eventonapify/v1/events/<id>`
- `GET /wp-json/eventonapify/v1/events/<id>/rsvps/summary`
- `GET /wp-json/eventonapify/v1/events/<id>/rsvps`
- `POST /wp-json/eventonapify/v1/events`
- `PUT/PATCH /wp-json/eventonapify/v1/events/<id>`
- `DELETE /wp-json/eventonapify/v1/events/<id>`

Features:
- Paginated event listing
- Search and status filtering
- Create, update, and delete EventON events
- Yes-only RSVP attendance summaries for EventON RSVP events
- RSVP attendee listing with normalized contact fields and custom RSVP form fields
- EventON date/time, status, virtual, repeat, RSVP, and taxonomy-backed location/organizer handling
- Event type taxonomy assignment
- Global API switch plus per-capability route controls
- Optional `wp/v2` compatibility mode for generic WordPress clients such as `mcp-wp`
- Read-only MCP schema manifest for compatible MCP servers
- Compatible with WordPress Application Password authentication

Automatic updates:
- This plugin supports updates via GitHub Updater:
  https://github.com/afragen/github-updater

Privacy:
- EventON APIfy reads and writes EventON event data stored in standard WordPress/EventON post meta and taxonomy meta.
- Depending on the enabled features and payloads, that data can include organizer contact details, venue contact details, virtual event credentials/settings, and RSVP notification email recipients.
- The custom API and the `wp/v2` compatibility surface are intended for administrator-authenticated access only.
- This plugin does not create its own custom database tables.
- Site owners remain responsible for their EventON content retention, privacy disclosures, and any export/erasure workflows they require.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate the plugin from wp-admin -> Plugins.
3. Go to Settings -> EventON APIfy.
4. Enable "Event API".
5. Enable the capabilities you want to expose.
6. Install and activate EventON if it is not already active.
7. If you use a generic WordPress client such as `mcp-wp`, enable "WP v2 compatibility".
8. (Optional) Install GitHub Updater for one-click updates from GitHub.

Upgrade note:
- Starting with `1.3.2`, the plugin keeps a backup copy of the API and `WP v2 compatibility` settings so upgrades can restore them if those options go missing during an update.

== Usage ==

List endpoint:
- `GET /wp-json/eventonapify/v1/events`

Settings capability map:
- `List events`: `GET /events`
- `Read single event`: `GET /events/<id>`
- `Read RSVP summary`: `GET /events/<id>/rsvps/summary`
- `List RSVP attendees`: `GET /events/<id>/rsvps`
- `Create events`: `POST /events`
- `Update events`: `PUT/PATCH /events/<id>`
- `Delete events`: `DELETE /events/<id>`

If the global Event API switch is on but one of the capability switches is off, only that operation returns `403`.

MCP / wp/v2 compatibility:
- Enable "WP v2 compatibility" in Settings -> EventON APIfy.
- Use `ajde_events` as the content type in generic WordPress clients.
- Standard routes become available at `/wp-json/wp/v2/ajde_events` and related taxonomy routes.
- EventON-specific fields can be sent either at the top level or through wrapper objects such as `custom_fields` / `fields`, using keys like `start_date`, `start_time`, `timezone`, `event_status`, `location`, `organizers`, `flags`, `virtual`, `repeat`, and `rsvp`.
- These `wp/v2` routes are restricted to administrator-authenticated requests, matching the custom namespace.
- Compatibility responses redact sensitive fields such as virtual access secrets and notification email metadata.

MCP schema manifest:
- `GET /wp-json/eventonapify/v1/mcp-schema`
- `GET /wp-json/eventonapify/v1/mcp-schema/ajde_events`
- `GET /wp-json/eventonapify/v1/mcp-schema/event_rsvps` when the RSVP addon is active
- The manifest publishes an executable EventON content contract with `preferred_endpoint`, `preferred_write_mode`, normalized `fields`, executable `validation_rules`, and `examples.create` / `examples.update`.
- When the RSVP addon is active, the manifest also publishes a read-only `event_rsvps` contract for `/events/{event_id}/rsvps`, including the related yes-only summary endpoint.
- The manifest is discovery-only. Compatible MCP servers should still write through `/wp-json/wp/v2/ajde_events`.

Query parameters:
- `per_page` (integer): page size, default `20`, max `100`
- `page` (integer): page number, default `1`
- `search` (string): search term
- `status` (string): comma-separated statuses like `publish,draft`

RSVP summary endpoint:
- `GET /wp-json/eventonapify/v1/events/<id>/rsvps/summary`
- Available only when the `EventON - RSVP Events` addon is active.
- Counts only RSVP responses normalized to `yes`.
- Returns `yes_submissions`, `yes_attendees_total`, and `yes_additional_attendees`.

RSVP attendee list endpoint:
- `GET /wp-json/eventonapify/v1/events/<id>/rsvps`
- Available only when the `EventON - RSVP Events` addon is active.
- Query parameters:
  - `per_page` default `50`, max `100`
  - `page` default `1`
  - `search`
  - `rsvp`: `all`, `yes`, `no`, `maybe`
  - `status`: exact attendee-status filter
- Each attendee item includes `first_name`, `last_name`, `full_name`, `email`, `phone`, `email_updates`, `rsvp`, `status`, `rsvp_type`, `count`, `event_time`, `other_attendees`, and `custom_fields`.

Create/update fields:
- `title` (string)
- `description` (string)
- `excerpt` (string)
- `status` (string)
- `start_date` (string, `YYYY-MM-DD`)
- `start_time` (string)
- `end_date` (string, `YYYY-MM-DD`)
- `end_time` (string)
- `start_at` / `end_at` (ISO datetime aliases)
- `timezone` or `timezone_key`
- `event_status`, `status_reason`, `attendance_mode`
- `location` (object for the `event_location` term)
- `organizers` (array for `event_organizer` terms)
- `event_color` / `event_color_secondary` (hex color)
- `event_type` (array or comma-separated string)
- `flags` (object)
- `virtual` (object)
- `repeat` (object)
- `rsvp` (object)

Preferred payloads use nested `location`, `organizers`, `virtual`, `repeat`, `rsvp`, and `flags` objects. Legacy flat aliases such as `location_name`, `location_address`, `map_url`, and `organizer` are still accepted for backward compatibility.

Example requests:
- `https://yourwebsite.com/wp-json/eventonapify/v1/events?per_page=10&page=1`
- `https://yourwebsite.com/wp-json/eventonapify/v1/events?search=ride&status=publish,draft`

== Authentication ==

Use WordPress Application Passwords.

Setup:
1. Go to Users -> Profile.
2. In Application Passwords, create a new password.
3. Use your username + application password with Basic Auth.

Example curl:
`curl -u your_username:your_app_password "https://yourwebsite.com/wp-json/eventonapify/v1/events?per_page=10&page=1"`

== Frequently Asked Questions ==

= Who can access the endpoint? =
Only authenticated users with the `manage_options` capability.

= Does this work without EventON? =
No. EventON must be active so the `ajde_events` post type exists.

= Can I disable the API without deactivating the plugin? =
Yes. Go to Settings -> EventON APIfy and uncheck "Event API".

= Can I disable only writes and leave reads enabled? =
Yes. Leave "Event API" enabled and turn off only the `Create events`, `Update events`, or `Delete events` capabilities.

= How do I use this with mcp-wp or another generic WordPress MCP server? =
Enable "WP v2 compatibility" and use `ajde_events` as the content type. The plugin will expose EventON events through `/wp-json/wp/v2/ajde_events` and expose the main EventON taxonomies on the standard REST API as well.

= Can wp/v2 clients send EventON fields inside custom_fields or fields? =
Yes. The plugin accepts EventON field payloads either directly on the request body or nested under `custom_fields` / `fields`.

= Does the plugin publish a machine-readable schema for MCP servers? =
Yes. Fetch `/wp-json/eventonapify/v1/mcp-schema` or `/wp-json/eventonapify/v1/mcp-schema/ajde_events` to discover the executable EventON contract, including `preferred_endpoint: wp/v2/ajde_events`, `preferred_write_mode: fields`, normalized field definitions, and create/update examples.

= How do I assign event types? =
Send `event_type` as an array or a comma-separated string in create or update requests.

= What happens if a date is invalid? =
The API responds with a `400` error explaining which date/time combination could not be parsed.

== Changelog ==

= 1.3.4 =
* Hardened the optional `wp/v2` compatibility layer so EventON compatibility routes stay administrator-only.
* Added translation bootstrapping and localized the primary admin/settings UI strings.
* Added privacy documentation, improved the security reporting policy, and completed uninstall cleanup for plugin-owned options.

= 1.3.2 =
* Preserved the Event API toggle, capability map, and `WP v2 compatibility` toggle across future upgrades by keeping and restoring a backup snapshot of those settings.
* Added activation and runtime bootstrap safeguards so missing settings are recreated from the backup instead of silently falling back to disabled defaults.

= 1.3.1 =
* Accepted EventON `wp/v2` write payloads nested under `custom_fields` and `fields`, matching the documented MCP compatibility formats.
* Clarified MCP and `wp/v2` documentation around wrapped EventON field payloads for date/time writes.

 = 1.2.0 =
* Reworked the MCP manifest into the executable contract shape expected by `mcp-wp-cpt`.
* Added `preferred_endpoint`, `preferred_write_mode`, normalized `fields`, executable `validation_rules`, and `examples.create` / `examples.update`.
* Published additional runtime validation notes for checks that are enforced by the plugin but not by the generic contract interpreter.

= 1.1.0 =
* Added a read-only MCP schema manifest at `/wp-json/eventonapify/v1/mcp-schema`.
* Published shared EventON field definitions, validation rules, and example payloads for `ajde_events`.
* Documented manifest discovery in settings and packaged docs.

= 1.0.0 =
* Initial stable release.
* Protected CRUD endpoints for EventON events.
* Settings toggle for endpoint enable/disable, plus per-capability route controls.
* Optional `wp/v2` compatibility mode for generic WordPress clients and MCP tools.
* EventON metadata handling for dates, times, locations, organizer, and color.
* GitHub Updater compatibility metadata and packaging docs.

== Upgrade Notice ==

= 1.3.4 =
Improves `wp/v2` compatibility hardening, localization readiness, privacy documentation, and uninstall cleanup.

= 1.3.2 =
Prevents future upgrades from silently disabling the Event API or `WP v2 compatibility` when the saved options go missing.

= 1.3.1 =
Fixes `wp/v2` compatibility writes for EventON date and other field payloads sent inside `custom_fields` or `fields`.

= 1.2.0 =
Aligns the MCP manifest with the executable plugin contract used by `mcp-wp-cpt`.
