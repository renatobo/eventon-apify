# Spec: expose all four EventON event-type taxonomies

**Status:** proposed
**Target:** 3.3.0
**Written:** 2026-08-01
**Files:** `includes/rest-events-read.php`, `includes/rest-event-terms.php`, `includes/rest-event-payload.php`, `includes/mcp-field-definitions.php`

---

## Problem

EventON registers **one taxonomy per activated event-type slot**, not one taxonomy with several terms. See `eventON/includes/class-evo-post-types.php:110`, which loops `eventon_get_valid_ett()` and registers `event_type`, `event_type_2`, `event_type_3`, `event_type_4`, each with a site-configurable name.

On drocdesmo.com those four are:

| Taxonomy | Site name | Its terms |
|---|---|---|
| `event_type` | Ride | Short ride, Long ride |
| `event_type_2` | Bike Night | Ducati Newport Beach, Pub, Karting, Dainese |
| `event_type_3` | Track Day | Chuckwalla, Buttonwillow, Laguna Seca, COTA, … |
| `event_type_4` | MotoGP Watch Party | MotoDoffo, Bike Shed LA, Ducati Newport Beach |

**This plugin exposes only the first one.**

| Surface | Location | Covers |
|---|---|---|
| Read | `rest-events-read.php:69-70` — `'event_type'`, `'event_type_terms'` | `event_type` only |
| Write | `rest-event-terms.php:22-25` — one `array_key_exists('event_type', …)` | `event_type` only |
| Contract | `mcp-field-definitions.php:71` — a single `event_type` entry | `event_type` only |
| Colour | `rest-event-terms.php:115` — `if ($taxonomy === 'event_type' …)` | `event_type` only |

The omission is already inconsistent inside this file set: `eventon_apify_build_access_control()` at `rest-events-read.php:636-639` enumerates **all four** taxonomies when scanning for ARMember term protection. The plugin knows they exist; it just does not carry them.

## Impact

An API or MCP client can read and write one quarter of an event's categorisation. On the consuming site, three of the four event families are invisible and unsettable, so:

- Events created through the MCP land uncategorised, whatever the client intends.
- A read-modify-write client cannot round-trip an event's categories, because three taxonomies are absent from the read.
- Consumers that count or filter by family have to bypass the contract entirely.

Measured on 2026-08-01: a backfill of 169 events across all four families had to be written through `wp/v2` directly, because `fields` could not express three of them. 208 of 435 published events now carry a family term; only 181 of those are reachable through this plugin's read payload.

## Requirements

### R1. Read exposes every event-type taxonomy

Add an `event_types` object to the read payload, keyed by taxonomy slug:

```json
"event_types": {
  "event_type":   { "label": "Ride",               "names": ["Short ride"], "terms": [ { "term_id": 280, "name": "Short ride", "slug": "short-ride" } ] },
  "event_type_2": { "label": "Bike Night",         "names": [],             "terms": [] },
  "event_type_3": { "label": "Track Day",          "names": [],             "terms": [] },
  "event_type_4": { "label": "MotoGP Watch Party", "names": [],             "terms": [] }
}
```

- The set is discovered from `get_object_taxonomies('ajde_events')` filtered to `/^event_type(_\d+)?$/`, never hardcoded. EventON only registers the slots that are activated, so a site with two event types must produce two entries.
- `label` comes from the taxonomy's `menu_name` label, which EventON sets to the bare configured name. Its `name` label is the same string with `" Categories"` appended, so fall back to `name` with that suffix stripped.
- `terms` reuses `eventon_apify_format_term_objects()`, the same shape `event_type_terms` already returns.
- Every activated taxonomy appears even when the event has no terms in it, so a client can discover the available families from any single event read.

### R2. Existing `event_type` fields stay exactly as they are

`event_type` (label array) and `event_type_terms` (objects) remain in the payload, unchanged, describing the first taxonomy. They are published contract. R1 adds a surface; it does not migrate one.

### R3. Write accepts every event-type taxonomy

`eventon_apify_save_event_terms()` must handle each activated taxonomy, not a hardcoded `event_type`. Two accepted spellings:

- Flat, matching the read keys: `"event_type_2": ["Pub"]` or `"event_type_2": [301]`
- Nested: `"event_types": { "event_type_2": ["Pub"] }`

`eventon_apify_sync_simple_terms()` already takes `$taxonomy` as an argument, so this is mostly a loop over the discovered set. Terms accept the same union the current implementation does: term ID, name string, or object with `name`/`term_id`.

**Absent key means unchanged.** Only taxonomies present in the request are touched. An empty array clears that taxonomy. This matches how `tags` and `event_type` behave today, and matters more here: a client that knows about one taxonomy must not wipe the other three by omission.

### R4. Term colour generalises

`rest-event-terms.php:115` gates `et_color` on `$taxonomy === 'event_type'`. EventON stores `et_color` term meta for every event-type taxonomy, so the condition should be "is this one of the event-type taxonomies", not "is this the first one".

### R5. Contract and discovery advertise the set

- `mcp-field-definitions.php` gains an `event_types` entry of type `object`, with a shape derived at runtime from the activated taxonomies, plus per-taxonomy aliases so the flat spelling in R3 validates.
- `describe_content_type` must show the labels. They are site-configurable in EventON settings, so a client that hardcodes "Bike Night" is broken on every other install. Discovery is the only correct source.
- The `mcp-contract-consistency` test must cover the new fields, so the manifest cannot drift from the read payload the way `flags.all_day` did before 3.2.1.

### R6. Round-trip is lossless

Reading an event, changing one unrelated field, and writing the whole payload back must leave all four taxonomies untouched. This is the same class of defect as the `start_time` corruption fixed in 3.2.1: a read that omits a field, combined with a write that accepts it, silently destroys data on round trip. Since R1 lands the read and R3 the write, they must ship together.

## Tests

Add to `tests/php/cases/`, following `term-meta.php` and `audit-fixes-core.php`:

1. Read returns one entry per activated taxonomy, including empty ones.
2. `label` prefers `menu_name`; falls back to `name` with `" Categories"` stripped.
3. A site with only `event_type` activated returns exactly one entry (no hardcoded four).
4. Flat and nested write spellings both assign, by term ID and by name.
5. An absent taxonomy key leaves existing terms alone; an empty array clears them.
6. Full round trip: read, modify `title` only, write back, assert all four taxonomies unchanged.
7. `et_color` persists on a non-primary event-type taxonomy.
8. Contract consistency: every field in the read payload is declared in the manifest.

## Out of scope

- Migrating or deprecating `event_type` / `event_type_terms`. R2 keeps them.
- Creating taxonomies. EventON owns activation; this plugin reports what exists.
- Any opinion about what the families mean. "Ride" and "Bike Night" are site data, not plugin concepts.

## Draft release note

```markdown
## New Features

- Read payloads now carry `event_types`, one entry per activated EventON
  event-type taxonomy, with the site-configured label and full term objects.
  EventON registers a separate taxonomy per event-type slot, and only the first
  was exposed, so three quarters of an event's categorisation was invisible to
  the API.
- Writes accept every event-type taxonomy, flat (`event_type_2`) or nested
  under `event_types`. Absent keys are left untouched, so a client that knows
  about one taxonomy cannot clear the others by omission.

## Improvements

- `et_color` term meta is honored on every event-type taxonomy rather than only
  the first.
- `describe_content_type` advertises the taxonomies and their labels, which are
  site-configurable, so clients can discover them instead of hardcoding slugs.

## Bug Fixes

- A read-modify-write round trip no longer drops an event's second, third and
  fourth event-type assignments. They were absent from reads while remaining
  writable, the same defect class as the `start_time` corruption fixed in 3.2.1.
```
