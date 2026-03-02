=== EventON APIfy ===
Contributors: renatobo
Tags: eventon, api, rest-api, events, wordpress
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 1.2.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

GitHub Plugin URI: https://github.com/renatobo/eventon-apify
GitHub Branch: main

Protected REST API endpoints for EventON events with administrator-only access, pagination, and CRUD support.

== Description ==

EventON APIfy adds protected REST endpoints for working with EventON `ajde_events` posts from external systems.

Access control:
- Endpoint access is restricted to authenticated administrators.
- Endpoint availability can be enabled or disabled in plugin settings.
- Individual capabilities can be enabled or disabled for list, read, create, update, and delete operations.

Routes:
- `GET /wp-json/eventonapify/v1/events`
- `GET /wp-json/eventonapify/v1/events/<id>`
- `POST /wp-json/eventonapify/v1/events`
- `PUT/PATCH /wp-json/eventonapify/v1/events/<id>`
- `DELETE /wp-json/eventonapify/v1/events/<id>`

Features:
- Paginated event listing
- Search and status filtering
- Create, update, and delete EventON events
- EventON date/time, status, virtual, repeat, RSVP, and taxonomy-backed location/organizer handling
- Event type taxonomy assignment
- Global API switch plus per-capability route controls
- Optional `wp/v2` compatibility mode for generic WordPress clients such as `mcp-wp`
- Read-only MCP schema manifest for compatible MCP servers
- Compatible with WordPress Application Password authentication

Automatic updates:
- This plugin supports updates via GitHub Updater:
  https://github.com/afragen/github-updater

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate the plugin from wp-admin -> Plugins.
3. Go to Settings -> EventON APIfy.
4. Enable "Event API".
5. Enable the capabilities you want to expose.
6. Install and activate EventON if it is not already active.
7. If you use a generic WordPress client such as `mcp-wp`, enable "WP v2 compatibility".
8. (Optional) Install GitHub Updater for one-click updates from GitHub.

== Usage ==

List endpoint:
- `GET /wp-json/eventonapify/v1/events`

Settings capability map:
- `List events`: `GET /events`
- `Read single event`: `GET /events/<id>`
- `Create events`: `POST /events`
- `Update events`: `PUT/PATCH /events/<id>`
- `Delete events`: `DELETE /events/<id>`

If the global Event API switch is on but one of the capability switches is off, only that operation returns `403`.

MCP / wp/v2 compatibility:
- Enable "WP v2 compatibility" in Settings -> EventON APIfy.
- Use `ajde_events` as the content type in generic WordPress clients.
- Standard routes become available at `/wp-json/wp/v2/ajde_events` and related taxonomy routes.
- EventON-specific fields can be sent through client-specific custom field support using keys like `start_date`, `start_time`, `timezone`, `event_status`, `location`, `organizers`, `flags`, `virtual`, `repeat`, and `rsvp`.
- These `wp/v2` routes are restricted to administrator-authenticated requests, matching the custom namespace.
- Compatibility responses redact sensitive fields such as virtual access secrets and notification email metadata.

MCP schema manifest:
- `GET /wp-json/eventonapify/v1/mcp-schema`
- `GET /wp-json/eventonapify/v1/mcp-schema/ajde_events`
- The manifest publishes an executable EventON content contract with `preferred_endpoint`, `preferred_write_mode`, normalized `fields`, executable `validation_rules`, and `examples.create` / `examples.update`.
- The manifest is discovery-only. Compatible MCP servers should still write through `/wp-json/wp/v2/ajde_events`.

Query parameters:
- `per_page` (integer): page size, default `20`, max `100`
- `page` (integer): page number, default `1`
- `search` (string): search term
- `status` (string): comma-separated statuses like `publish,draft`

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

= Does the plugin publish a machine-readable schema for MCP servers? =
Yes. Fetch `/wp-json/eventonapify/v1/mcp-schema` or `/wp-json/eventonapify/v1/mcp-schema/ajde_events` to discover the executable EventON contract, including `preferred_endpoint: wp/v2/ajde_events`, `preferred_write_mode: fields`, normalized field definitions, and create/update examples.

= How do I assign event types? =
Send `event_type` as an array or a comma-separated string in create or update requests.

= What happens if a date is invalid? =
The API responds with a `400` error explaining which date/time combination could not be parsed.

== Changelog ==

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

= 1.2.0 =
Aligns the MCP manifest with the executable plugin contract used by `mcp-wp-cpt`.
