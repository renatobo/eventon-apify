# Security Assessment Report

_Plugin version reviewed: 2.1.1. Method: static source review of `eventon-apify.php`, `uninstall.php`, and the `includes/{core,admin,mcp,rest}.php` modules. No live WordPress instance was deployed and no dynamic exploitation testing was performed in this run; findings are based on code-path inference against the current module layout._

## Executive Summary

The plugin follows sound WordPress security practices: the custom `eventonapify/v1` routes are consistently administrator-gated, the API and its sensitive capabilities default to disabled, all REST inputs are sanitized/validated, no raw SQL is used (everything goes through `WP_Query` and the WP CRUD APIs), and the optional `wp/v2` compatibility surface redacts PII and disables `show_in_rest` for non-admins.

No Critical or High severity issues were found. The previously reported Medium issue (`wp/v2` route-allowlist leak) is now substantially mitigated and is downgraded to a defense-in-depth note. Remaining items are Low severity.

## Low Severity

### SEC-001: `wp/v2` compatibility gating still relies on a route-prefix allowlist (defense-in-depth only)

Impact: when `wp/v2` compatibility is enabled, the plugin layers several controls on top of a hardcoded route-prefix allowlist. The original concern was that uncovered core routes such as `/wp/v2/search?subtype=ajde_events` would leak EventON content. That specific leak is now closed for non-admins, but the architecture still depends on enumerating routes/filters rather than a single deny-by-default gate.

- The route allowlist `eventon_apify_is_wp_v2_compatibility_route()` (`includes/core.php:360`) still lists only `/wp/v2/ajde_events`, `/wp/v2/types/ajde_events`, and per-taxonomy prefixes; it does not list `/wp/v2/search`.
- The auth filter `eventon_apify_restrict_wp_v2_compatibility_routes()` (`includes/core.php:335`, hooked at `eventon-apify.php:60`) blocks only allowlisted routes for non-admins.

Why this is now mitigated:

- For non-admin requests, `ajde_events` and its taxonomies are registered with `show_in_rest = false` (`includes/rest.php:274`, `includes/rest.php:305`), so they drop out of the searchable subtype set entirely.
- `register_rest_field` for compatibility fields runs only for `manage_options` users (`includes/rest.php:324`).
- Search is explicitly filtered: `eventon_apify_filter_wp_v2_compatibility_post_search_query()` (`includes/core.php:481`) and `..._term_search_query()` (`includes/core.php:504`) strip `ajde_events` from `/wp/v2/search` results for non-admins.
- Index/discovery and `/wp/v2/types` + `/wp/v2/taxonomies` responses are scrubbed for non-admins (`includes/core.php:422`, `includes/core.php:444`).

Recommended fix (hardening): keep `show_in_rest=false`-for-non-admins as the primary control and treat the route allowlist as belt-and-suspenders only. Add regression tests asserting that a non-admin request to `/wp/v2/search?subtype=ajde_events`, `/wp/v2/ajde_events`, `/wp/v2/types`, and `/wp/v2/taxonomies` returns no EventON objects.

### SEC-002: Public MCP schema endpoint exposes live feature configuration

Impact: unauthenticated callers can learn whether EventON and the RSVP addon are active, whether the custom API is enabled, the full CRUD/RSVP capability matrix, and whether `wp/v2` compatibility is on. This is low-risk reconnaissance data (it reveals which surfaces to probe and whether attendee PII endpoints are enabled), but it exposes no records, secrets, or PII directly. The endpoint is public by design.

- Both MCP schema routes register `permission_callback => '__return_true'` (`includes/rest.php:10`, `includes/rest.php:22`).
- The availability builder `eventon_apify_get_mcp_availability_state()` (`includes/mcp.php:1411`) emits `eventon_available`, `eventon_rsvp_available`, `custom_event_api_enabled`, `custom_event_api_capabilities`, `wp_v2_compatibility_enabled`, and `preferred_mcp_ready`, embedded into every manifest (`includes/mcp.php:1520`). Per-content-type availability is repeated at `includes/mcp.php:1588` and `includes/mcp.php:1610`.

Recommended fix: if public discovery is required, keep only the static contract/shape data public and move the live `availability` block behind an authenticated path (e.g. `permission_callback => 'eventon_apify_admin_only'`), or reduce the public payload to coarse booleans and drop `custom_event_api_capabilities` / `rsvp_*_enabled`.

### SEC-003: Slug filter has no count cap and an inconsistent string branch

Impact: minor robustness/performance, not exploitable. There is no SQL injection vector (slug values are normalized with `sanitize_title` at the point of use and passed to `WP_Query` `post_name__in`).

- `eventon_apify_sanitize_slug_filter()` (`includes/rest.php:893`) runs `sanitize_title` on array elements but returns the string branch through `sanitize_text_field` (not `sanitize_title`, and not split).
- At query time the value is re-split and every element is `sanitize_title`'d again before assignment to `post_name__in` (`includes/rest.php:1662`), which neutralizes the inconsistency.
- No upper bound is enforced on the number of slugs, so a very large comma-separated or `slug[]` list produces a large `IN` list.

Recommended fix: cap the slug list (e.g. `array_slice($slugs, 0, 100)`) and apply `sanitize_title` in the sanitizer's string branch for consistency.

### SEC-004: `uninstall.php` leaves RSVP delta-sync post meta behind

Impact: incomplete cleanup, not a security issue (the orphaned data is a timestamp key, no PII or secrets).

- `uninstall.php` deletes the five plugin options (`uninstall.php:6`) but does not remove the per-post RSVP timestamp meta `_eventon_apify_updated_at_gmt` (defined `eventon-apify.php:34`, written at `includes/rest.php:250`).

Recommended fix: add `delete_post_meta_by_key('_eventon_apify_updated_at_gmt')` to `uninstall.php`.

## Positive Notes

- Custom event routes consistently use the centralized administrator-only callback `eventon_apify_admin_only()` (`includes/rest.php:855`), applied at every `/events*` route (`includes/rest.php:40`, `:90`, `:102`, `:112`, `:122`, `:143`, `:160`).
- The API and its sensitive capabilities default to disabled: `enable_api`, `rsvp_*`, and `wp/v2` compatibility all default to `false` (`includes/core.php:67`, `:222`, `:87`).
- No raw SQL anywhere in `includes/`; date filters use a `WP_Query` `meta_query` with int-cast values (`includes/rest.php:1675`).
- Strict input validation: date filters via `preg_match` + `checkdate` (`includes/rest.php:644`), order/orderby allowlisted (`includes/rest.php:703`, `:742`), `per_page` capped at 100 (`includes/rest.php:874`).
- The `wp/v2` compatibility formatter redacts organizer/location contact details, virtual-event secrets, and RSVP notification emails before exposing payloads (`includes/rest.php:411`).
- Admin settings use the Settings API (`settings_fields()` at `includes/admin.php:99`) for nonce/CSRF; no ad-hoc form processing. Admin output is consistently escaped.

## Recommended Remediation Order

1. Reduce or authenticate the live `availability` flags in the public MCP schema output (SEC-002).
2. Add a slug-count cap and align the sanitizer string branch (SEC-003).
3. Extend `uninstall.php` to remove the RSVP timestamp meta (SEC-004).
4. Add regression tests for non-admin `wp/v2` access and keep `show_in_rest=false` as the primary compatibility control (SEC-001).
