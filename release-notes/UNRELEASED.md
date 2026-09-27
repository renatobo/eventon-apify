## New Features

- The `ajde_events` MCP manifest publishes `one_of_required_for_create: [["start_date", "start_at"]]`, a list of groups where each group needs at least one field on create, and `related_endpoints` for the single-event route `eventonapify/v1/events/{id}` and the secondary `wp/v2/ajde_events` route.

## Improvements

- `repeat.intervals` items in the manifest now list every key the write accepts: `start_at`, `end_at`, `start_timestamp`, `end_timestamp`, `start_date`, `start_time`, `end_date`, `end_time`. Clients that build requests from the manifest shape no longer strip the timestamp and date/time forms.
- `event_type` and `tags` descriptions state that string items are exact term names, created when missing, and numeric values are term IDs. Both still accept an array or one comma-separated string.

## Bug Fixes

- `ajde_events` `preferred_endpoint` is `eventonapify/v1/events` instead of `wp/v2/ajde_events`, so manifest-driven clients write through the transactional route that saves post fields, EventON meta, and terms with rollback.
- `required_for_create` is `["title"]` instead of `["title", "start_date"]`. The REST write accepts `start_at` in place of `start_date`, and `start_date` no longer declares `required_on: ["create"]`.
- `supported_operations` for `ajde_events` includes `delete`, matching `DELETE /eventonapify/v1/events/{id}`.
- Field definitions in the manifest now publish `also_accepts`. It was used internally but never exported, so clients that validate against the manifest rejected the comma-separated string form of `event_type` and `tags`.
- `event_type` and `tags` items are typed `["string", "integer"]`, so numeric term IDs pass client-side and REST schema validation.
- `location.lat` and `location.lon` are typed `["string", "number"]`, matching the numeric input the write accepts. The `wp/v2` field schema uses the same type, so numeric coordinates pass `wp/v2` validation.

## Notes

- The manifest `schema_version` stays `1.0.0`. Every change is additive or a value change.
- Rename this file to `release-notes/<version>.md` at release time; `release.sh` requires the versioned name.
