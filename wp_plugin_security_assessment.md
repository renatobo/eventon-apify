# WordPress Plugin Security Assessment

## Executive Summary

- Plugin version reviewed: 2.1.1.
- Scope reviewed: `eventon-apify.php`, `uninstall.php`, and the `includes/{core,admin,mcp,rest}.php` modules.
- Method: static source review only. No live instance was deployed and no dynamic/runtime testing was performed in this run.
- Overall risk: Low. The custom `eventonapify/v1` routes are consistently administrator-gated, no raw SQL is used, and the optional `wp/v2` compatibility mode now disables `show_in_rest` for non-admins and filters core search. The previously reported Medium `wp/v2` search leak is substantially mitigated.
- Finding counts by severity:
  - Critical: 0
  - High: 0
  - Medium: 0
  - Low: 4 (WPSEC-002, WPSEC-003, WPSEC-004 remediated in the working tree, pending the 2.1.2 release)

## Critical

No critical issues identified in the reviewed scope.

## High

No high-severity issues identified in the reviewed scope.

## Medium

No medium-severity issues identified in the reviewed scope. See WPSEC-001 below for the prior Medium finding, now downgraded to Low after mitigation.

## Low

### WPSEC-001 `wp/v2` compatibility authorization depends on a route-prefix allowlist (mitigated; defense-in-depth)

- Prior status: Medium (unauthenticated `/wp/v2/search?subtype=ajde_events` leaked EventON titles/URLs in compatibility mode). Current status: substantially mitigated.
- Files: `includes/core.php:360` (allowlist `eventon_apify_is_wp_v2_compatibility_route()`), `includes/core.php:335` (auth filter, hooked at `eventon-apify.php:60`).
- Why mitigated: for non-admin requests the post type and taxonomies are registered with `show_in_rest = false` (`includes/rest.php:274`, `includes/rest.php:305`), compatibility fields register only for admins (`includes/rest.php:324`), and core search is explicitly stripped of `ajde_events` for non-admins via `rest_post_search_query` / `rest_term_search_query` filters (`includes/core.php:481`, `includes/core.php:504`). Index/types/taxonomies responses are also scrubbed (`includes/core.php:422`, `includes/core.php:444`).
- Residual concern: the design still enumerates routes/filters rather than using a single deny-by-default gate, so a future core route that surfaces `show_in_rest` post types by an unanticipated path could regress admin-context exposure (non-admins remain protected by `show_in_rest=false`).
- Remediation: keep `show_in_rest=false`-for-non-admins as the primary control; treat the allowlist as belt-and-suspenders. Add regression tests for non-admin access to `/wp/v2/search?subtype=ajde_events`, `/wp/v2/ajde_events`, `/wp/v2/types`, and `/wp/v2/taxonomies`.

### WPSEC-002 Public MCP manifest exposes live feature configuration

- Status: Resolved (pending 2.1.2). The capability matrix and per-capability RSVP flags are now gated behind `manage_options` in `eventon_apify_get_mcp_availability_state()` and `eventon_apify_get_mcp_rsvp_content_type_manifest()`; anonymous callers get coarse booleans only.
- Files: `includes/rest.php:10` and `includes/rest.php:22` (routes registered with `permission_callback => '__return_true'`); `includes/mcp.php:1411` (`eventon_apify_get_mcp_availability_state()`), embedded at `includes/mcp.php:1520`, repeated at `includes/mcp.php:1588` and `includes/mcp.php:1610`.
- Impact: unauthenticated callers learn which addons are active, whether the custom API and `wp/v2` compatibility are enabled, and the full capability matrix (`custom_event_api_capabilities`, `rsvp_attendees_enabled`, `rsvp_counts_enabled`). Low-risk reconnaissance; no records, secrets, or PII are exposed. The endpoint is public by design (`includes/admin.php:307`).
- Remediation: move the live `availability` block behind an authenticated path, or reduce the public payload to coarse booleans and drop the capability matrix.

### WPSEC-003 Slug filter has no count cap; sanitizer string branch is inconsistent

- Status: Resolved (pending 2.1.2). Slug input is capped at `EVENTON_APIFY_MAX_SLUG_FILTER` (100) and `sanitize_title` is applied to every value in both branches.
- Files: `includes/rest.php:893` (`eventon_apify_sanitize_slug_filter()`), `includes/rest.php:1662` (query-time normalization to `post_name__in`).
- Impact: no injection (values are `sanitize_title`'d before reaching `WP_Query`), but there is no upper bound on the number of slugs, so a very large list produces a large `IN` clause (minor performance/DoS consideration).
- Remediation: cap the slug list (e.g. `array_slice(..., 0, 100)`) and apply `sanitize_title` in the string branch for consistency.

### WPSEC-004 `uninstall.php` leaves RSVP delta-sync post meta

- Status: Resolved (pending 2.1.2). `uninstall.php` now calls `delete_post_meta_by_key('_eventon_apify_updated_at_gmt')`.
- Files: `uninstall.php:6` (option cleanup), missing removal of `_eventon_apify_updated_at_gmt` (defined `eventon-apify.php:34`, written `includes/rest.php:250`).
- Impact: incomplete cleanup; orphaned timestamp meta remains after uninstall. No security/PII impact.
- Remediation: add `delete_post_meta_by_key('_eventon_apify_updated_at_gmt')` to `uninstall.php`.

## Notes

- Assumptions: WPSEC-001's residual risk depends on `wp/v2` compatibility being enabled and on future WordPress core changes; non-admin exposure is closed in 2.1.1.
- Strengths confirmed: centralized admin-only permission callback on all event routes (`includes/rest.php:855`); API and sensitive capabilities disabled by default (`includes/core.php:67`, `:222`); PII/secret redaction on the `wp/v2` surface (`includes/rest.php:411`); strict input validation and allowlists for dates, order/orderby, status, and enums (`includes/rest.php:644`, `:703`, `:742`); no direct `$wpdb` usage anywhere in `includes/`; Settings API nonce/CSRF and escaped admin output (`includes/admin.php:99`).
- Areas not verified this run: no live/dynamic testing, no exhaustive per-endpoint core REST probing, no addon-interaction testing. No SQL, file-upload, command-execution, or nonce issues were found in the reviewed scope.
