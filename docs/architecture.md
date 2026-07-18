# EventON APIfy Architecture

## Goals

- Preserve the published `eventonapify/v1` and optional `wp/v2` contracts.
- Keep WordPress hooks and global callback names stable for compatibility.
- Separate request authorization, contract/schema metadata, use-case
  coordination, and EventON persistence.
- Return errors without retaining avoidable partial event writes.

## Runtime composition

`eventon-apify.php` defines stable plugin constants and delegates startup to
`EventON_APIfy\Plugin`. The composition root loads modules in dependency order
and owns hook registration. Admin UI code is loaded only in admin requests.

## Boundaries

1. **Transport:** `rest-routes.php`, `rest-schema.php`, and the `rest-wp-v2-*`
   modules define authorization, WordPress REST schemas, and callbacks.
2. **Contract and validation:** `mcp-field-*`, `rest-event-payload.php`, and
   `rest-event-validation.php` hold the canonical API vocabulary and validation.
3. **Use-case coordination:** `rest-events-write.php` handles HTTP-level event
   commands; `event-write-coordinator.php` coordinates post, metadata, taxonomy,
   and shared-option changes with compensating rollback.
4. **EventON persistence:** `rest-event-meta.php` and `rest-event-terms.php` map
   normalized domain values to EventON storage. `eventon-taxonomy-meta-store.php`
   isolates the proprietary shared taxonomy option and prefers EventON's public
   helper when available.
5. **Presentation:** `rest-events-read.php` and `rest-rsvp.php` map WordPress and
   EventON records to stable response objects. RSVP post access and attendee
   mapping are isolated behind `RSVP_Attendee_Repository` and
   `RSVP_Attendee_Formatter`; the published procedural callbacks remain as
   compatibility adapters. `admin.php` owns settings UI only.

## Reliability model

WordPress does not offer a transaction spanning posts, post meta, taxonomy
relationships, and EventON's option-backed taxonomy metadata. Event writes use a
snapshot plus compensating actions. This prevents the common case where a REST
request returns an error after leaving earlier post/meta/term mutations behind.
It cannot provide database-level isolation against simultaneous third-party
writes to the same event; callers should still serialize updates per event.

## Security model

All discovery, event, RSVP, and optional `wp/v2` compatibility surfaces require
an administrator (`manage_options`). WordPress Application Passwords are the
recommended automation authentication mechanism. Route permission callbacks are
authorization controls; request validation and output escaping remain separate
defense layers.

## Compatibility verification

The dependency-free unit suite covers normalization and contract logic across
PHP 8.0, 8.3, and 8.5. CI also installs WordPress 7.0.2 with MySQL, activates the
plugin, and verifies route registration, administrator authorization, discovery
protection, and required create schemas against WordPress core itself.
