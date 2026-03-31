# EventON APIfy

[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-21759B?logo=wordpress&logoColor=white)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-777bb4?logo=php&logoColor=white)](https://www.php.net/)
[![Tested up to](https://img.shields.io/badge/Tested%20up%20to-6.9.4-21759B?logo=wordpress&logoColor=white)](https://wordpress.org/)
[![Release](https://img.shields.io/github/v/release/renatobo/eventon-apify?label=release)](https://github.com/renatobo/eventon-apify/releases)
[![License: GPL v2 or later](https://img.shields.io/badge/License-GPL%20v2%20or%20later-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

WordPress plugin that exposes protected REST API endpoints for EventON `ajde_events`, including pagination, search, create, update, and delete operations for administrator-authenticated integrations.

## Quick start

1. Copy this plugin into your WordPress plugins directory.
2. Activate **EventON APIfy**.
3. Go to **Settings -> EventON APIfy**.
4. Enable **Event API** and the capabilities you want to expose.
5. Create a WordPress **Application Password** for an administrator user.
6. Call the endpoint:

```bash
curl -u your_username:your_app_password \
  "https://your-site.com/wp-json/eventonapify/v1/events?per_page=10&page=1"
```

## Features

- Dedicated namespace: `eventonapify/v1`
- List EventON events with pagination, search, and status filtering
- Fetch a single event by ID
- Create new `ajde_events` posts via REST
- Update existing events, including EventON timestamps, status, virtual, repeat, RSVP, and taxonomy-backed location/organizer metadata
- Trash events through the API
- Read yes-only RSVP attendance summaries for EventON RSVP events
- List RSVP attendee records and additional RSVP form fields for EventON RSVP events
- Administrator-only access
- Global Event API switch plus per-capability toggles for event reads/writes and RSVP reads
- Optional `wp/v2` compatibility mode for generic WordPress tools such as `mcp-wp`
- Read-only MCP schema manifest for clients that need an executable EventON content contract
- Compatible with WordPress Application Passwords
- Git Updater metadata included for dashboard-based GitHub updates

## Requirements

- WordPress `6.0+`
- PHP `8.0+`
- EventON installed and active
- HTTPS-enabled site recommended for secure API authentication

## Installation

1. Install the packaged zip from [GitHub Releases](https://github.com/renatobo/eventon-apify/releases), or upload the plugin folder to `/wp-content/plugins/eventon-apify/` when working from source in local development.
2. Activate it from **Plugins** in wp-admin.
3. Open **Settings -> EventON APIfy**.
4. Enable **Event API**.
5. Enable the capabilities you want available to administrators.
6. If you are using a generic WordPress client such as `mcp-wp`, also enable **WP v2 compatibility**.
7. If you installed from GitHub and want in-dashboard updates, install [Git Updater](https://github.com/afragen/git-updater).

Upgrade note: from `1.3.2` onward, the plugin keeps a backup copy of the API and `WP v2 compatibility` settings so future upgrades can restore them if those options go missing during an update.

## Packaging

Build an installable plugin zip from the repo root:

```bash
./build.sh
```

That creates a file like `eventon-apify-x.y.z.zip` in the project root, ready to upload in **Plugins -> Add New -> Upload Plugin**.

## Releases

To publish a GitHub release with the WordPress-ready zip attached:

```bash
./release.sh x.y.z
```

That script:

- updates the plugin version in `eventon-apify.php`
- updates the stable tag in `readme.txt`
- commits the version bump
- creates and pushes the git tag `vx.y.z`
- verifies that the plugin header, `EVENTON_APIFY_VERSION`, and `Stable tag` all match

Pushing the tag triggers GitHub Actions, which runs `./build.sh`, creates or updates the GitHub Release for that tag, and uploads the generated zip asset automatically.

## Authentication

This API requires WordPress authentication and checks for the `manage_options` capability.

Route access is controlled in two layers:

- Global switch: **Event API**
- Per-capability switches: **List events**, **Read single event**, **Create events**, **Update events**, **Delete events**, **Read RSVP summary**, **List RSVP attendees**

Recommended method: **Application Passwords**.

1. Go to **Users -> Profile**.
2. Create a new application password.
3. Use `username:application_password` in Basic Auth.

Example:

```bash
curl -u your_username:your_app_password \
  "https://your-site.com/wp-json/eventonapify/v1/events?search=ride&status=publish,draft"
```

## Privacy

EventON APIfy works with EventON data stored in standard WordPress/EventON post meta and taxonomy meta rather than creating its own custom tables.

Depending on the enabled routes and submitted payloads, that data can include:

- organizer contact details
- location contact details
- virtual event credentials and visibility settings
- RSVP notification email recipients

Both the custom namespace and the optional `wp/v2` compatibility mode are intended for administrator-authenticated access only. Site owners remain responsible for their privacy disclosures, retention policies, and any export/erasure workflows required for EventON-managed content.

## MCP compatibility

If you use [InstaWP mcp-wp](https://github.com/InstaWP/mcp-wp) or another WordPress MCP server, use `ajde_events` as the content type and fetch the EventON APIfy MCP manifest first when the client supports plugin-published contracts.

The manifest now advertises the custom EventON APIfy events route as the preferred content endpoint for EventON event reads and writes:

- Content type: `ajde_events`
- Preferred content endpoint: `/wp-json/eventonapify/v1/events`

If your client specifically requires the standard WordPress namespace, enable **WP v2 compatibility** in **Settings -> EventON APIfy**. That additionally exposes EventON through the standard WordPress REST API:

- Content type: `ajde_events`
- Content endpoint: `/wp-json/wp/v2/ajde_events`
- Taxonomies: `event_type`, `event_location`, `event_organizer`

Recommended `mcp-wp` usage:

- Use `content_type: "ajde_events"` for content operations
- Send EventON-specific fields either at the top level or inside `custom_fields` / `fields`, including `start_date`, `start_time`, `end_date`, `end_time`, `timezone`, `event_status`, `location`, `organizers`, `flags`, `virtual`, `repeat`, and `rsvp`
- Use the MCP taxonomy tools for `event_type`, `event_location`, and `event_organizer` when you want direct taxonomy-level operations
- Fetch the EventON APIfy MCP manifest first if your MCP server supports plugin-published content contracts

Important: the `wp/v2` compatibility endpoints are also restricted to administrator-authenticated requests. Compatibility responses redact sensitive fields such as virtual access secrets and notification email metadata.

## MCP schema manifest

EventON APIfy also publishes a read-only discovery contract for compatible MCP servers:

- Manifest: `/wp-json/eventonapify/v1/mcp-schema`
- Content type detail: `/wp-json/eventonapify/v1/mcp-schema/ajde_events`
- Content type detail: `/wp-json/eventonapify/v1/mcp-schema/event_rsvps` when the RSVP addon is active

The manifest describes:

- `preferred_endpoint: "eventonapify/v1/events"`
- `preferred_write_mode: "fields"` for structured client input
- normalized EventON `fields` with nested object and array shapes
- executable `validation_rules` plus additional runtime `validation_notes`
- `examples.create` and `examples.update` payloads for MCP clients
- runtime availability such as whether `WP v2 compatibility` is currently enabled
- when the RSVP addon is active, a read-only `event_rsvps` content type for `/wp-json/eventonapify/v1/events/{event_id}/rsvps`
- when the RSVP addon is active, the related RSVP summary endpoint `/wp-json/eventonapify/v1/events/{event_id}/rsvps/summary`

Important: the manifest is discovery-only. Compatible clients should follow the advertised `preferred_endpoint`, which for `ajde_events` is `/wp-json/eventonapify/v1/events`. The contract examples are client-facing normalized payloads, not raw WordPress REST requests.

When using `wp/v2`, clients may send those normalized EventON fields directly on the request body or nest them inside `fields` / `custom_fields`.

Example:

```bash
curl "https://your-site.com/wp-json/eventonapify/v1/mcp-schema/ajde_events"
```

## API reference

### Routes

- `GET /wp-json/eventonapify/v1/mcp-schema`
- `GET /wp-json/eventonapify/v1/mcp-schema/ajde_events`
- `GET /wp-json/eventonapify/v1/mcp-schema/event_rsvps` when the RSVP addon is active
- `GET /wp-json/eventonapify/v1/events`
- `GET /wp-json/eventonapify/v1/events/<id>`
- `GET /wp-json/eventonapify/v1/events/<id>/rsvps/summary`
- `GET /wp-json/eventonapify/v1/events/<id>/rsvps`
- `POST /wp-json/eventonapify/v1/events`
- `PUT /wp-json/eventonapify/v1/events/<id>`
- `PATCH /wp-json/eventonapify/v1/events/<id>`
- `DELETE /wp-json/eventonapify/v1/events/<id>`

### Settings capability map

| Setting | Methods | Route | Effect when disabled |
| --- | --- | --- | --- |
| `List events` | `GET` | `/events` | Collection reads return `403` |
| `Read single event` | `GET` | `/events/<id>` | Single-event reads return `403` |
| `Read RSVP summary` | `GET` | `/events/<id>/rsvps/summary` | RSVP summary reads return `403` |
| `List RSVP attendees` | `GET` | `/events/<id>/rsvps` | RSVP attendee reads return `403` |
| `Create events` | `POST` | `/events` | Event creation returns `403` |
| `Update events` | `PUT`, `PATCH` | `/events/<id>` | Event updates return `403` |
| `Delete events` | `DELETE` | `/events/<id>` | Event deletion returns `403` |

### List query parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `per_page` | integer | No | Items per page, default `20`, max `100` |
| `page` | integer | No | Page number, default `1` |
| `search` | string | No | Search against event title/content |
| `status` | string | No | Comma-separated post statuses such as `publish,draft` |

### RSVP summary response

`GET /wp-json/eventonapify/v1/events/<id>/rsvps/summary` is available only when the `EventON - RSVP Events` addon is active.

- `yes_submissions`: number of RSVP records whose RSVP response is `yes`
- `yes_attendees_total`: total headcount across those `yes` records, using the RSVP `Count` field and falling back to `1`
- `yes_additional_attendees`: `yes_attendees_total - yes_submissions`

Example:

```bash
curl -u your_username:your_app_password \
  "https://your-site.com/wp-json/eventonapify/v1/events/123/rsvps/summary"
```

### RSVP attendee list query parameters

`GET /wp-json/eventonapify/v1/events/<id>/rsvps` is available only when the `EventON - RSVP Events` addon is active.

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `per_page` | integer | No | Items per page, default `50`, max `100` |
| `page` | integer | No | Page number, default `1` |
| `search` | string | No | Search attendee names, email, phone, RSVP fields, and custom RSVP fields |
| `rsvp` | string | No | `all`, `yes`, `no`, or `maybe`; default `all` |
| `status` | string | No | Exact RSVP attendee status filter; default `all` |

Each attendee item exposes:

- `id`
- `first_name`
- `last_name`
- `full_name`
- `email`
- `phone`
- `email_updates`
- `rsvp`
- `status`
- `rsvp_type`
- `count`
- `event_time`
- `other_attendees`
- `custom_fields`

### Create and update fields

| Name | Type | Required on create | Description |
| --- | --- | --- | --- |
| `title` | string | Yes | Event title |
| `description` | string | No | Event content/body |
| `status` | string | No | `publish`, `draft`, `private`, `pending`, or `future` |
| `excerpt` | string | No | WordPress post excerpt |
| `start_date` / `start_time` | string | Yes | Event start in `YYYY-MM-DD` and `HH:MM` |
| `end_date` / `end_time` | string | No | Event end in `YYYY-MM-DD` and `HH:MM` |
| `start_at` / `end_at` | string | No | ISO datetime aliases accepted on input |
| `timezone` or `timezone_key` | object/string | No | Event timezone, for example `America/Los_Angeles` |
| `event_status` | string | No | `scheduled`, `cancelled`, `movedonline`, `postponed`, `rescheduled`, `preliminary`, `tentative` |
| `status_reason` | string | No | EventON status reason text |
| `attendance_mode` | string | No | `offline`, `online`, or `mixed` |
| `location` | object | No | EventON `event_location` term payload |
| `organizers` | array | No | EventON `event_organizer` terms |
| `event_color` / `event_color_secondary` | string | No | Hex colors, with or without `#` |
| `event_type` | array or string | No | Event type terms as array or comma-separated string |
| `flags` | object | No | EventON yes/no flags such as `featured`, `generate_gmap`, `hide_end_time` |
| `virtual` | object | No | Virtual event metadata such as URL, password, embed, and visible end |
| `repeat` | object | No | Repeat settings, including `frequency`, `count`, and custom `intervals` |
| `rsvp` | object | No | RSVP addon metadata such as capacity and repeat capacities |

Preferred payloads use nested `location`, `organizers`, `virtual`, `repeat`, `rsvp`, and `flags` objects. Legacy flat aliases like `location_name`, `location_address`, `map_url`, and `organizer` are still accepted for backward compatibility.

### Example create request

```bash
curl -u your_username:your_app_password \
  -X POST "https://your-site.com/wp-json/eventonapify/v1/events" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Ride to Big Bear",
    "description": "Optional HTML content",
    "excerpt": "Short summary",
    "status": "publish",
    "start_date": "2026-04-01",
    "start_time": "09:00",
    "end_date": "2026-04-01",
    "end_time": "17:00",
    "timezone": {
      "key": "America/Los_Angeles",
      "text": "PT"
    },
    "event_status": "scheduled",
    "attendance_mode": "offline",
    "location": {
      "name": "Big Bear Lake",
      "address": "123 Main St",
      "city": "Big Bear Lake",
      "state": "CA",
      "country": "US",
      "link": "https://maps.google.com/?q=Big+Bear+Lake"
    },
    "organizers": [
      {
        "name": "EventON APIfy",
        "email": "events@example.com"
      }
    ],
    "event_color": "#ff0000",
    "event_type": ["Rides", "Featured"],
    "flags": {
      "featured": true,
      "generate_gmap": true,
      "open_google_maps_link": true
    },
    "rsvp": {
      "enabled": true,
      "capacity_enabled": true,
      "capacity_count": 75
    }
  }'
```

### Example `wp/v2` compatibility request

```bash
curl -u your_username:your_app_password \
  -X POST "https://your-site.com/wp-json/wp/v2/ajde_events" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Ride to Big Bear",
    "status": "publish",
    "custom_fields": {
      "start_date": "2026-04-01",
      "start_time": "09:00",
      "end_date": "2026-04-01",
      "end_time": "17:00",
      "timezone": {
        "key": "America/Los_Angeles",
        "text": "PT"
      }
    }
  }'
```

### Example list response shape

```json
{
  "total": 14,
  "pages": 2,
  "page": 1,
  "per_page": 10,
  "events": [
    {
      "id": 123,
      "title": "Ride to Big Bear",
      "status": "publish",
      "slug": "ride-to-big-bear",
      "description": "Optional HTML content",
      "excerpt": "Short summary",
      "start_timestamp": 1775034000,
      "start_at": "2026-04-01T09:00:00-07:00",
      "start_date": "2026-04-01",
      "start_time": "09:00",
      "end_timestamp": 1775062800,
      "end_at": "2026-04-01T17:00:00-07:00",
      "end_date": "2026-04-01",
      "end_time": "17:00",
      "location": {
        "term_id": 55,
        "name": "Big Bear Lake",
        "address": "123 Main St",
        "city": "Big Bear Lake",
        "state": "CA",
        "country": "US",
        "map_url": "https://www.google.com/maps?q=123%20Main%20St"
      },
      "organizer": "EventON APIfy",
      "organizers": [
        {
          "term_id": 12,
          "name": "EventON APIfy",
          "email": "events@example.com"
        }
      ],
      "event_color": "#ff0000",
      "event_type": ["Rides", "Featured"],
      "event_status": "scheduled",
      "attendance_mode": "offline",
      "featured_image": ""
    }
  ]
}
```

### Common error responses

- Endpoint disabled in settings:

```json
{
  "code": "eventon_apify_disabled",
  "message": "The EventON APIfy endpoint is disabled. Enable it in Settings > EventON APIfy.",
  "data": {
    "status": 403
  }
}
```

- Invalid date/time combination:

```json
{
  "code": "eventon_apify_invalid_start_datetime",
  "message": "The start_date/start_time combination could not be parsed.",
  "data": {
    "status": 400
  }
}
```

- Capability disabled in settings:

```json
{
  "code": "eventon_apify_capability_disabled",
  "message": "Create events is disabled in Settings > EventON APIfy.",
  "data": {
    "status": 403
  }
}
```

## Automatic updates

This repository is set up for a dual distribution model:

- GitHub Releases is the active channel and publishes the packaged zip used by direct installs.
- [Git Updater](https://github.com/afragen/git-updater) can track this repository and install updates from the attached GitHub release asset because the main plugin file includes the required Git Updater headers.
- WordPress.org is intended as a secondary channel later; it is not the active install or release path yet.

## Release process

- `readme.txt` keeps the `Stable tag` version
- GitHub Actions can package zip releases for `v*` tags
- `release.sh` bumps the main plugin version and `Stable tag`

## Related packages

- [mcp-wp-cpt](https://github.com/renatobo/mcp-wp-cpt)
- Other WordPress plugins:
  - [TelegrARM](https://github.com/renatobo/TelegrARM)
  - [WebHookARM](https://github.com/renatobo/WebHookARM)
  - [bono_arm_api](https://github.com/renatobo/bono_arm_api)

## License

Licensed under [GPL-2.0-or-later](https://www.gnu.org/licenses/gpl-2.0.html).
