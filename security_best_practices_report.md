# Security Assessment Report

## Executive Summary

Static review of this repository found one substantive code issue and two lower-severity security/process issues.

The main technical risk is in the optional `wp/v2` compatibility mode: the plugin enables core REST controllers for EventON content, but its administrator-only guard only applies to a narrow allowlist of route prefixes. Based on the code, this leaves room for ancillary WordPress REST endpoints outside that allowlist to expose EventON compatibility metadata or discovery information when compatibility mode is enabled.

Assessment scope was limited to source review of [eventon-apify.php](/Users/renatobo/development/eventon-apify/eventon-apify.php), [uninstall.php](/Users/renatobo/development/eventon-apify/uninstall.php), and [SECURITY.md](/Users/renatobo/development/eventon-apify/SECURITY.md). No live WordPress instance or dynamic exploitation testing was performed, so findings about reachable core REST routes are based on code-path inference.

## Medium Severity

### SEC-001: `wp/v2` compatibility gating relies on a narrow route allowlist

Impact: when `wp/v2` compatibility is enabled, EventON content is registered on core REST controllers, but the plugin only blocks a hardcoded subset of `wp/v2` routes. Other core REST endpoints outside that list may still expose compatibility metadata or content discovery that the plugin describes as administrator-only.

- Evidence: the authorization filter rejects only routes recognized by `eventon_apify_is_wp_v2_compatibility_route()` at [eventon-apify.php:380](/Users/renatobo/development/eventon-apify/eventon-apify.php#L380) and [eventon-apify.php:405](/Users/renatobo/development/eventon-apify/eventon-apify.php#L405).
- Evidence: that allowlist includes only eight prefixes, limited to `/wp/v2/ajde_events`, selected taxonomy endpoints, and `/wp/v2/types/ajde_events` at [eventon-apify.php:408](/Users/renatobo/development/eventon-apify/eventon-apify.php#L408).
- Evidence: compatibility mode separately enables the EventON post type and taxonomies on core REST controllers via `show_in_rest` at [eventon-apify.php:1242](/Users/renatobo/development/eventon-apify/eventon-apify.php#L1242) and [eventon-apify.php:1264](/Users/renatobo/development/eventon-apify/eventon-apify.php#L1264).

Why this matters:

- This protection model depends on knowing every core route that can surface a REST-exposed post type or taxonomy.
- Inference from the current code: endpoints such as broader REST discovery or search surfaces are not covered by the allowlist and therefore would not be blocked by this plugin-level filter.

Recommended fix:

- Replace the route-prefix allowlist with a capability check tied to the EventON post type and taxonomies themselves, or intercept all requests that resolve to `ajde_events` / EventON taxonomy controllers.
- If the intent is strict administrator-only access, treat the entire compatibility mode as private instead of trying to enumerate safe route prefixes.

## Low Severity

### SEC-002: Public MCP schema endpoint exposes live feature configuration

Impact: unauthenticated callers can learn whether EventON is installed, whether the custom API is enabled, which custom API capabilities are enabled, and whether `wp/v2` compatibility is turned on. This is low-risk reconnaissance data, but it is still unnecessary exposure.

- Evidence: both MCP schema routes are public through `permission_callback => '__return_true'` at [eventon-apify.php:1123](/Users/renatobo/development/eventon-apify/eventon-apify.php#L1123) and [eventon-apify.php:1135](/Users/renatobo/development/eventon-apify/eventon-apify.php#L1135).
- Evidence: the manifest publishes runtime availability state, including `custom_event_api_enabled`, `custom_event_api_capabilities`, and `wp_v2_compatibility_enabled`, at [eventon-apify.php:2467](/Users/renatobo/development/eventon-apify/eventon-apify.php#L2467) and [eventon-apify.php:2501](/Users/renatobo/development/eventon-apify/eventon-apify.php#L2501).

Recommended fix:

- If public discovery is not required, require authentication for the MCP schema.
- If it must stay public, remove live enablement flags and publish only the static contract information clients need.

### SEC-003: Security policy encourages public vulnerability disclosure and lists stale support coverage

Impact: [SECURITY.md](/Users/renatobo/development/eventon-apify/SECURITY.md) tells researchers to open a security report or issue on GitHub and only marks `1.0.x` as supported, while the plugin header shows version `1.3.2`. This increases the chance of public zero-day disclosure and makes patch/support expectations unclear.

- Evidence: the policy directs reporters to "Open a security report or issue on the GitHub repository" at [SECURITY.md:9](/Users/renatobo/development/eventon-apify/SECURITY.md#L9).
- Evidence: the policy's supported-versions table only lists `1.0.x` at [SECURITY.md:5](/Users/renatobo/development/eventon-apify/SECURITY.md#L5), while the plugin version is `1.3.2` at [eventon-apify.php:7](/Users/renatobo/development/eventon-apify/eventon-apify.php#L7) and [eventon-apify.php:25](/Users/renatobo/development/eventon-apify/eventon-apify.php#L25).

Recommended fix:

- Add a private reporting channel or GitHub private vulnerability reporting guidance.
- Update the supported-version matrix to match current maintained releases.

## Positive Notes

- The custom `eventonapify/v1` event routes consistently use a centralized administrator-only permission callback at [eventon-apify.php:1153](/Users/renatobo/development/eventon-apify/eventon-apify.php#L1153), [eventon-apify.php:1188](/Users/renatobo/development/eventon-apify/eventon-apify.php#L1188), and [eventon-apify.php:2814](/Users/renatobo/development/eventon-apify/eventon-apify.php#L2814).
- Write paths show generally solid sanitization and validation for post status, URLs, colors, dates, repeat intervals, emails, and numeric inputs at [eventon-apify.php:3444](/Users/renatobo/development/eventon-apify/eventon-apify.php#L3444), [eventon-apify.php:3497](/Users/renatobo/development/eventon-apify/eventon-apify.php#L3497), and [eventon-apify.php:3987](/Users/renatobo/development/eventon-apify/eventon-apify.php#L3987).
- The `wp/v2` compatibility formatter already redacts several sensitive fields, including virtual-event secrets and RSVP notification emails, before exposing compatibility payloads at [eventon-apify.php:2606](/Users/renatobo/development/eventon-apify/eventon-apify.php#L2606).

## Recommended Remediation Order

1. Harden `wp/v2` compatibility authorization so it cannot be bypassed by uncovered core REST routes.
2. Reduce or authenticate the public MCP schema output.
3. Fix [SECURITY.md](/Users/renatobo/development/eventon-apify/SECURITY.md) so disclosure guidance and supported versions are accurate.
