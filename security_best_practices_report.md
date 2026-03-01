# Security Assessment Report

## Executive Summary

Static review of the WordPress plugin identified two high-severity issues and one medium-severity issue centered on the optional `wp/v2` compatibility mode. The main problem is that the plugin's documented "administrator-only" security model is enforced on the custom `eventonapify/v1` routes, but not on the standard `wp/v2` surface that compatibility mode enables. In that mode, lower-privileged or unauthenticated access may be able to read or modify sensitive EventON data depending on the site's post type and REST configuration.

Assessment scope was limited to source review of [eventon-apify.php](/Users/renatobo/development/eventon-apify/eventon-apify.php), [uninstall.php](/Users/renatobo/development/eventon-apify/uninstall.php), and project docs. No live WordPress instance or dynamic exploitation testing was performed.

## High Severity

### SEC-001: `wp/v2` compatibility bypasses the plugin's admin-only authorization model

Impact: when `wp/v2` compatibility is enabled, access control falls back to WordPress core REST capabilities instead of the plugin's `manage_options` gate, which can expose read/write operations to broader roles than intended.

- Evidence: custom API routes use `eventon_apify_admin_only()` and require `manage_options` at [eventon-apify.php:611](/Users/renatobo/development/eventon-apify/eventon-apify.php#L611), [eventon-apify.php:634](/Users/renatobo/development/eventon-apify/eventon-apify.php#L634), [eventon-apify.php:646](/Users/renatobo/development/eventon-apify/eventon-apify.php#L646), [eventon-apify.php:656](/Users/renatobo/development/eventon-apify/eventon-apify.php#L656), and [eventon-apify.php:666](/Users/renatobo/development/eventon-apify/eventon-apify.php#L666), implemented by [eventon-apify.php:2169](/Users/renatobo/development/eventon-apify/eventon-apify.php#L2169).
- Evidence: compatibility mode exposes `ajde_events` and EventON taxonomies on core REST controllers at [eventon-apify.php:693](/Users/renatobo/development/eventon-apify/eventon-apify.php#L693) and [eventon-apify.php:715](/Users/renatobo/development/eventon-apify/eventon-apify.php#L715).
- Evidence: additional mutable `wp/v2` fields are registered with an `update_callback`, but no equivalent plugin-level permission check is added in that callback path at [eventon-apify.php:737](/Users/renatobo/development/eventon-apify/eventon-apify.php#L737), [eventon-apify.php:754](/Users/renatobo/development/eventon-apify/eventon-apify.php#L754), and [eventon-apify.php:2065](/Users/renatobo/development/eventon-apify/eventon-apify.php#L2065).

Recommended fix:

- Enforce the same authorization model on the `wp/v2` compatibility path by rejecting reads and writes unless `current_user_can('manage_options')`.
- If broad `wp/v2` compatibility is required, document clearly that this mode changes the trust boundary and expose only a reduced safe field set.

### SEC-002: Sensitive EventON metadata is exposed through `wp/v2` reads

Impact: if `ajde_events` becomes readable through the standard REST API, the plugin will serialize secret or operator-only fields such as virtual meeting passwords and notification addresses to any caller permitted by core REST visibility.

- Evidence: compatibility read callbacks return data directly from `eventon_apify_format_event()` at [eventon-apify.php:2039](/Users/renatobo/development/eventon-apify/eventon-apify.php#L2039).
- Evidence: the formatted payload includes location email at [eventon-apify.php:2504](/Users/renatobo/development/eventon-apify/eventon-apify.php#L2504), organizer email/phone/address at [eventon-apify.php:2551](/Users/renatobo/development/eventon-apify/eventon-apify.php#L2551), [eventon-apify.php:2552](/Users/renatobo/development/eventon-apify/eventon-apify.php#L2552), and [eventon-apify.php:2553](/Users/renatobo/development/eventon-apify/eventon-apify.php#L2553).
- Evidence: the virtual event payload includes URL, password, embed payload, and moderator ID at [eventon-apify.php:2585](/Users/renatobo/development/eventon-apify/eventon-apify.php#L2585) through [eventon-apify.php:2596](/Users/renatobo/development/eventon-apify/eventon-apify.php#L2596).
- Evidence: RSVP notification emails are exposed at [eventon-apify.php:2649](/Users/renatobo/development/eventon-apify/eventon-apify.php#L2649) through [eventon-apify.php:2670](/Users/renatobo/development/eventon-apify/eventon-apify.php#L2670).

Recommended fix:

- Remove secrets and operational contact fields from any public or semi-public response model.
- Split internal/admin response fields from public compatibility fields instead of reusing the full event formatter.
- At minimum, redact `virtual.password`, `virtual.embed`, `virtual.url`, `virtual.moderator_id`, `rsvp.additional_emails`, and direct contact data unless the current caller is an administrator.

## Medium Severity

### SEC-003: `wp/v2` writes can create or mutate shared taxonomy records without term-management authorization

Impact: a user who can edit an EventON post through standard REST may also be able to create or alter shared `event_type`, `event_location`, and `event_organizer` terms and their global metadata, affecting other events.

- Evidence: `wp/v2` field updates call shared save helpers at [eventon-apify.php:2065](/Users/renatobo/development/eventon-apify/eventon-apify.php#L2065) through [eventon-apify.php:2105](/Users/renatobo/development/eventon-apify/eventon-apify.php#L2105).
- Evidence: term synchronization can update event type assignments and metadata at [eventon-apify.php:4327](/Users/renatobo/development/eventon-apify/eventon-apify.php#L4327) through [eventon-apify.php:4424](/Users/renatobo/development/eventon-apify/eventon-apify.php#L4424).
- Evidence: location and organizer writes can update shared term metadata stores at [eventon-apify.php:4435](/Users/renatobo/development/eventon-apify/eventon-apify.php#L4435) through [eventon-apify.php:4535](/Users/renatobo/development/eventon-apify/eventon-apify.php#L4535), [eventon-apify.php:4548](/Users/renatobo/development/eventon-apify/eventon-apify.php#L4548) through [eventon-apify.php:4613](/Users/renatobo/development/eventon-apify/eventon-apify.php#L4613), and [eventon-apify.php:4759](/Users/renatobo/development/eventon-apify/eventon-apify.php#L4759).
- Evidence: term creation and updates occur without a dedicated capability check in [eventon-apify.php:4639](/Users/renatobo/development/eventon-apify/eventon-apify.php#L4639) and [eventon-apify.php:4717](/Users/renatobo/development/eventon-apify/eventon-apify.php#L4717).

Recommended fix:

- Require an explicit elevated capability before creating or modifying taxonomy terms or shared term metadata.
- Prefer separate code paths for "assign existing terms" versus "create or edit shared taxonomy records".

## Low Severity

### SEC-004: Public MCP schema endpoints reveal feature configuration

Impact: unauthenticated callers can enumerate whether EventON is installed, whether the API is enabled, and whether compatibility mode is on, which modestly improves attacker reconnaissance.

- Evidence: `/mcp-schema` and `/mcp-schema/<content_type>` are public via `__return_true` at [eventon-apify.php:574](/Users/renatobo/development/eventon-apify/eventon-apify.php#L574) through [eventon-apify.php:593](/Users/renatobo/development/eventon-apify/eventon-apify.php#L593).
- Evidence: the manifest includes runtime availability and configuration state as described in project docs and implementation paths.

Recommended fix:

- Decide whether discovery must be public. If not, gate it behind authentication.
- If public discovery is required, omit runtime flags that disclose feature enablement and reduce environment detail to the minimum clients need.

## Positive Notes

- The custom `eventonapify/v1` routes consistently use a centralized capability check for administrator-only access.
- Input handling is generally disciplined: the plugin validates statuses, dates, URLs, colors, and numeric inputs before persistence, and uses WordPress sanitizers in most write paths.
- No obvious SQL injection, arbitrary file access, or unsafe dynamic code execution paths were identified in the reviewed code.

## Recommended Remediation Order

1. Lock down or disable the `wp/v2` compatibility surface until it enforces the same administrator-only policy as the custom namespace.
2. Remove sensitive fields from compatibility responses and create an explicit allowlist for externally readable fields.
3. Add capability checks around taxonomy creation and term metadata mutation.
4. Revisit whether public MCP discovery is necessary.
