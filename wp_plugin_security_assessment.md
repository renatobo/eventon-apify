# WordPress Plugin Security Assessment

## Executive Summary

- Plugin version reviewed: 2.2.1.
- Scope reviewed: the plugin bootstrap, all files in `includes/`, `uninstall.php`, REST routes, WordPress `wp/v2` compatibility hooks, settings, RSVP reads, event writes, taxonomy writes, and the current PHP test/CI configuration.
- Method: static source tracing plus the 65-test PHP suite and PHP syntax checks under PHP 8.5.8. A WordPress 7.0.2 integration smoke job is included in CI; no live EventON/RSVP instance or authenticated penetration test was available locally.
- Overall risk: Low. No concrete critical, high, or medium vulnerability was identified. Protected event and RSVP routes consistently require `manage_options`; inputs are normalized and validated before writes; rendered admin output is contextually escaped; and no raw SQL, upload, filesystem-write, or command-execution surface exists.
- Finding counts: Critical 0, High 0, Medium 0, Low 0. One prior Low finding is resolved on this branch.

## Critical

No critical issues identified.

## High

No high-severity issues identified.

## Medium

No medium-severity issues identified.

## Low

No unresolved low-severity issues identified.

### Resolved: WPSEC-001 Schema endpoints were accessible without authentication

- Files: `includes/rest-routes.php:4-31`, `includes/mcp-manifest.php:54-176`.
- Resolution: both MCP schema routes now use `eventon_apify_admin_only`, matching the event API and the stated authenticated-automation-client policy. OpenAPI, Postman, README, and settings UI copy now describe the authenticated behavior.

## Notes

- Strong controls: all event and RSVP data routes use `eventon_apify_admin_only()` (`includes/rest-routes.php:34-196`, `includes/rest-access-control.php:6-8`); write callbacks re-check feature/capability enablement; the optional `wp/v2` compatibility surface is hidden and rejected for non-administrators; shared taxonomy mutation has an explicit administrator check; and user-facing settings output is escaped.
- No direct use of `$_GET`, `$_POST`, `$_REQUEST`, `$_COOKIE`, or `$_FILES` was found. No direct `$wpdb`, dynamic include, unsafe upload, shell command, or arbitrary filesystem operation was found.
- `maybe_unserialize()` is used only on EventON values read through WordPress metadata APIs. No attacker-controlled deserialization path was established in this review.
- Multi-step event writes now use compensating rollback across post fields, post metadata, term assignments, and EventON's shared taxonomy metadata option.
- Residual gaps: no live EventON/RSVP test, no multisite test, no full role/application-password matrix, no concurrent-write stress test, and no automated dynamic scanner. CodeRabbit was unavailable locally.
