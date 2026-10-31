# Repository Instructions

## Architecture

- Read `docs/architecture.md` before structural changes. It defines the five boundaries (transport, contract/validation, use-case coordination, EventON persistence, presentation) and the snapshot-plus-rollback write model.
- `includes/class-plugin.php` is the composition root: it owns module load order and every hook registration. Add hooks there, not at module scope.
- Design specs live in `docs/specs/`.

## REST Authorization

- Every route in `includes/rest-routes.php` uses `'permission_callback' => 'eventon_apify_admin_only'` (`manage_options`). There is no anonymous surface.
- Every handler must open with the matching `eventon_apify_assert_*_capability_is_ready('<capability>')` call. The permission callback covers who may call the route; the assert covers the `enable_api` master switch, EventON availability, and the per-route capability toggle. Neither substitutes for the other.
- Nothing enforces this automatically. `phpcs` does not check it, so a new handler that omits the assert needs a case in `tests/php/cases/`.

## WordPress Abilities

- `includes/abilities.php` is a read-only adapter over `eventonapify/v1`: each event ability dispatches the existing route in-process with `rest_do_request()`. Do not reimplement queries, readiness checks, or toggles in an ability; add behavior to the route and let the ability inherit it.
- Every ability's `permission_callback` is `eventon_apify_admin_only`, a plain boolean. Core turns a `WP_Error` from a permission callback into a generic error plus `_doing_it_wrong`, so readiness errors (API disabled, toggle off, EventON missing) come from the route handler through the execute callback, never from the permission callback.
- Core only applies a whole-input `default`, never per-property defaults. Execute callbacks must supply their own defaults or leave them to the route args.
- Non-administrators must not discover these abilities: `eventon_apify_filter_ability_visibility()` on `wp_get_abilities_item_include` covers listings, and `eventon_apify_restrict_ability_routes()` on `rest_pre_dispatch` covers the single-ability routes, which read the registry directly and bypass that filter. The integration smoke fails if either guard is removed.
- Do not pass the custom MCP manifest through `wp_prepare_json_schema_for_client()`. Its field vocabulary (`write_key`, `aliases`, `required_on`, `shape`) is its own contract, not JSON Schema. Core already prepares ability schemas when it exposes them.
- There are no write abilities by design. A future one must go through `EventON_APIfy_Event_Write_Coordinator` via the existing route, not a second write path.

## Distribution Channels

- Treat GitHub Releases as the active primary distribution channel.
- Treat WordPress.org as a secondary distribution channel that is not live until that build/submission path is explicitly in place.
- Do not describe WordPress.org as the current install or update source in UI copy, docs, or release notes unless the repo has been updated to support it.
- When writing user-facing copy, make Git Updater the explicit mechanism for GitHub-installed update flow.

## Release Versioning

- When bumping or releasing a version, update all user-visible version references together.
- At minimum, keep these in sync:
  - `eventon-apify.php` plugin header `Version`
  - `eventon-apify.php` constant `EVENTON_APIFY_VERSION`
  - `readme.txt` `Stable tag`
  - `docs/eventon-apify-openapi.json` `info.version` (and the `provider_version` example)
- The release validator also checks both version fields in the checked-in OpenAPI document.

## Packaging and Release Flow

- Run `bash build.sh` from the repository root; keep the `eventon-apify` directory name and ZIP root.
- Release publication uses the labelled-PR workflow below. The old automatic `main` tagging and tag/manual publishing workflows are removed; `release.sh` now refuses direct tagging.
- Keep release notes under `release-notes/X.Y.Z.md` with the three required feature/improvement/bug-fix headings.

## Testing

- Use `npm run test:unit` for the PHP unit harness in `tests/php/run.php`.
- Use `npm run test:prod` for the production integration test wrapper in `scripts/test-production.sh`.
- Use `npm run test:dev` when you need the same production test path against `.env.development`.
- `scripts/test-production.sh` expects an env file at `.env.production.local` by default, or a custom file via `EVENTON_APIFY_ENV_FILE`.
- Use `composer quality` for the full gate (`phpcs` lint, `phpstan` analyse, unit tests, performance gate).
- `phpcs.xml.dist` loads only `WordPress.Security`, deprecation sniffs, and `PrefixAllGlobals`. A clean run covers escaping, sanitization, nonces, and prepared SQL. It does not check capability gating or general code style.
- Unit-test cases are `tests/php/cases/*.php`, loaded in glob order by `tests/php/run.php`. Register with `test('name', fn)`; assert with `eq()` / `ok()` / `throws()`. The harness calls `eventon_test_reset_wp_state()` before each case.
- `tests/php/wp-stubs.php` holds hand-written WordPress doubles, not core. A function or class the plugin calls but the stubs lack must be added there before it can be tested.
- `eq()` compares with `!==`. Two structurally equal objects are not identical, so assert on `get_object_vars()` or `json_encode()` rather than comparing object instances.
- Neither `phpcs` nor `phpstan` scans `tests/`. Test and fixture code is covered by `php -l` only, so verify it by running it.
- A new test is not done until it has been seen to fail. Break the code it covers, confirm the failure, restore. A test that passes both ways is asserting nothing, and a mutation that unexpectedly survives usually means a second layer is doing the work, which is worth knowing either way.
- If `composer` is not on PATH, run the gate directly: `vendor/bin/phpcs`, `vendor/bin/phpstan analyse --no-progress --memory-limit=1G`, `php tests/php/run.php`, `php scripts/performance-gate.php`.
- `composer quality` is unit-level only and cannot verify REST route registration. The `wordpress-7-integration` CI job covers that: it installs WordPress 7.1.2 against MySQL and runs `tests/integration/wp-rest-smoke.php`, which dispatches real requests through the REST server and asserts compensating rollback. Live-site checks remain necessary only for the proprietary EventON runtime.
- Watch that job specifically. It is the only one that exercises WordPress, so a failure there does not turn the unit or quality jobs red.
- Reproduce that job locally: MySQL container, WordPress 7.1.2 extracted from the tarball, repo symlinked into `wp-content/plugins/`, then `wp eval-file tests/integration/wp-rest-smoke.php`. Four things bite:
  - Run every `wp-cli` command as `php -d memory_limit=1G wp-cli.phar …`; `wp core download` exhausts the default limit mid-extract.
  - Wait for MySQL with a real query (`mysql -uwordpress -pwordpress -e "SELECT 1" wordpress`), not `mysqladmin ping`, which reports ready before connections are accepted.
  - `wp core install` after a `db reset` clears `active_plugins`; reactivate before running anything, or the smoke dies with undefined functions.
  - `includes/admin.php` loads only under `is_admin()`, so `wp eval-file` must `require_once` it explicitly to reach any admin function.
- WordPress does not apply `rest_validate_request_arg` to hand-registered route args. An arg declaring only `type` / `minimum` / `enum` is documentation; it rejects nothing without an explicit `validate_callback`. `per_page` and `page` clamp in their sanitizers instead of returning 400.

## wp/v2 Compatibility Layer

- Never call `current_user_can()` inside `register_post_type_args` / `register_taxonomy_args` filters in `includes/rest-wp-v2-compat.php`. Those fire on `init`, before application-password auth resolves, so the current user is always 0 and `show_in_rest` would be forced false for every REST client.
- Admin-only access is enforced at request time in `includes/wp-v2-compat.php` via `eventon_apify_should_filter_wp_v2_compatibility_for_request()`: a `rest_pre_dispatch` 401, REST index stripping, `/wp/v2/types` + `/wp/v2/taxonomies` response stripping, and the search-query exclusions.
- Those guards apply only to objects compatibility mode itself exposed. The registration filters record them with `eventon_apify_mark_wp_v2_compat_exposed()` when they turn `show_in_rest` on; an object EventON already registers with `show_in_rest` (EventON 5 does for `ajde_events` and `event_type*`) is left untouched and keeps EventON's own access rules. The EventON fields stay admin-only either way.
- Do not source the registration whitelist from `eventon_apify_get_wp_v2_compatibility_taxonomies()`. It has a `static $cache` and depends on `post_type_exists('ajde_events')`; calling it at `init` poisons the cache the request-time guards read, registering routes the guards no longer recognize.

## UI Documentation

- The current settings header and tabs design is documented in `ui.md`.
- When changing the settings header, banner, metadata row, intro copy, tabs, or tab behavior, update `ui.md` in the same change.

## Git Updater Requirements

- Keep Git Updater compatibility enabled in the main plugin file header.
- Keep GitHub-first distribution messaging intact when editing settings UI, docs, or release copy.
- At minimum, preserve these plugin headers in `eventon-apify.php`:
  - `GitHub Plugin URI`
  - `Primary Branch`
  - `Release Asset: true`
- Releases should continue publishing a versioned zip asset to GitHub Releases so Git Updater can install/update from the packaged release asset.

## GitHub releases after PR merge

The supported release path is `.github/workflows/release.yml`. Ordinary merges do not publish. The old tag/push/manual publishing workflows have been replaced; pushing a tag alone does not publish.

For the next release:

1. Bump `eventon-apify.php` header `Version` and `EVENTON_APIFY_VERSION` to matching `X.Y.Z`. Also update `readme.txt` Stable tag and any Version field. Keep the configured API-spec version fields synchronized (see `.github/release.json`). Update the user-facing changelog/upgrade notes under this repository’s conventions.
2. Add `release-notes/X.Y.Z.md` with exact headings `## New Features`, `## Improvements`, `## Bug Fixes`; use `- None.` if a section has no entries. Older releases without this notes format remain unchanged.
3. Open a PR into `main`, run all required checks, then apply **`release` before merging**. That label explicitly opts the merge into publication. Do not create/push the version tag yourself or use the retired direct-tagging `release.sh` path.
4. Merge. The workflow verifies the canonical merged PR and exact merge commit, runs all existing reusable quality gates plus release/package guards, checks versions/notes and main ancestry, creates annotated `vX.Y.Z`, and publishes the **exact CI-tested ZIP** with SHA-256 checksum. The release is created as a draft and published only after asset upload succeeds.
5. Read back the published release and uploaded checksum; a green PR is not evidence that publication completed. Expected ZIP: `eventon-apify-{version}.zip` under root `eventon-apify/`.

If publication fails, manually dispatch **Release merged PR** from `main` with `pr_number`. The PR must be merged into `main` and still carry `release`. Adding the label after merge does not trigger publication, but manual dispatch can release that eligible PR. Matching existing tags may be reused only when they point to the same merge commit. Existing releases/drafts are never overwritten: inspect and explicitly resolve partial drafts before retrying; never move/delete published tags as a retry strategy. Concurrent runs for the same PR are serialized.

Tags and publication happen in one workflow, avoiding the `GITHUB_TOKEN` tag-event suppression. No PAT or additional secret is required. Only publishing has `contents: write` ; quality jobs retain their own minimum analysis permissions.

Release configuration is `.github/release.json`; guard code is `.github/scripts/release.py`; tests run with `python3 -B -m unittest discover -s .github/tests -v`. Package locally with `bash build.sh`, then run `python3 -B .github/scripts/release.py package`. Release ZIPs exclude this tooling and agent guides. Do not hand-edit the upload/download handoff to rebuild a different artifact in the publishing job.

For workflow-only maintenance, keep the plugin version unchanged and omit the `release` label. Merge setup/automation PRs unlabelled; the next labelled version PR uses the new flow.
