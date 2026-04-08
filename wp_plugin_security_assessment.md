# WordPress Plugin Security Assessment

## Executive Summary

- Scope reviewed: [`eventon-apify.php`](/Users/renatobo/development/eventon-apify/eventon-apify.php), [`uninstall.php`](/Users/renatobo/development/eventon-apify/uninstall.php), and the live local behavior on `http://localhost:8089` for relevant REST routes.
- Overall risk: Medium. The custom `eventonapify/v1` routes are consistently administrator-gated, but the optional `wp/v2` compatibility mode still exposes EventON content through uncovered core REST routes.
- Finding counts by severity:
  - Critical: 0
  - High: 0
  - Medium: 1
  - Low: 1

## Critical

No critical issues identified in the reviewed scope.

## High

No high-severity issues identified in the reviewed scope.

## Medium

### WPSEC-001 Unauthenticated `wp/v2` search leaks EventON content in compatibility mode
- File: [`eventon-apify.php:397`](/Users/renatobo/development/eventon-apify/eventon-apify.php#L397), [`eventon-apify.php:422`](/Users/renatobo/development/eventon-apify/eventon-apify.php#L422)
- Impact: When `wp/v2` compatibility is enabled, unauthenticated users can enumerate published `ajde_events` titles and URLs through core search endpoints even though the plugin describes the compatibility surface as administrator-only.
- Evidence: The compatibility guard only blocks a hardcoded prefix list in `eventon_apify_is_wp_v2_compatibility_route()`, covering `/wp/v2/ajde_events`, selected taxonomy endpoints, and `/wp/v2/types/ajde_events`, but not `/wp/v2/search`. Core WordPress registers `WP_REST_Search_Controller` separately, and its post search handler includes every public post type with `show_in_rest = true`. Local verification on `localhost:8089` confirmed unauthenticated `GET /wp-json/wp/v2/search?search=bike&subtype=ajde_events` returns EventON event titles and links.
- Remediation: Do not rely on a route-prefix allowlist for compatibility mode. Intercept all core REST requests that resolve to `ajde_events` or EventON taxonomies, or disable `show_in_rest` for non-admin requests in a way that also prevents search-controller enumeration.

## Low

### WPSEC-002 Public MCP manifest exposes live feature configuration
- File: [`eventon-apify.php:1254`](/Users/renatobo/development/eventon-apify/eventon-apify.php#L1254), [`eventon-apify.php:3002`](/Users/renatobo/development/eventon-apify/eventon-apify.php#L3002)
- Impact: Unauthenticated callers can learn whether EventON and the RSVP addon are active, whether the custom API is enabled, which capabilities are turned on, and whether `wp/v2` compatibility is enabled. This is low-risk reconnaissance data, but it needlessly discloses internal feature state.
- Evidence: Both MCP schema endpoints are registered with `permission_callback => '__return_true'`, and the emitted manifest includes `eventon_available`, `eventon_rsvp_available`, `custom_event_api_enabled`, `custom_event_api_capabilities`, and `wp_v2_compatibility_enabled`. Local verification on `localhost:8089` confirmed these flags are returned anonymously from `/wp-json/eventonapify/v1/mcp-schema`.
- Remediation: Require authentication for the MCP schema if public discovery is not required. If it must remain public, strip live enablement flags and publish only the static contract data clients need.

## Notes

- Assumptions: The Medium finding depends on `wp/v2` compatibility being enabled; the leakage was confirmed against the local WordPress instance, but production impact depends on that feature being switched on.
- Areas not fully verified: I did not perform exhaustive dynamic testing of every core REST endpoint, admin form flow, or every EventON addon interaction. No SQL, file-upload, command-execution, or nonce-related issues were found in the reviewed scope.
