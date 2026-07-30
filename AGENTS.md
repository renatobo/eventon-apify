# Repository Instructions

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
- `./release.sh <version>` updates all of the above and aborts if any are left out of sync.
- Prefer using `./release.sh <version>` so the release commit, tag, and packaged GitHub release asset stay aligned.

## Packaging and Release Flow

- Use `./build.sh` from the repo root to create the installable versioned plugin zip for local packaging checks.
- `./build.sh` expects the bootstrap file to match the repo slug (`eventon-apify.php`) and writes `eventon-apify-<version>.zip` in the project root.
- `./release.sh <version>` requires a clean working tree, a matching `release-notes/<version>.md` file, updates the synced version fields, creates the release commit, tags `v<version>`, and pushes both `main` and the tag.
- `./release.sh <version>` expects semantic version format (`X.Y.Z`) and aborts if the target tag already exists.
- Release notes files must include these top-level sections:
  - `## New Features`
  - `## Improvements`
  - `## Bug Fixes`
- Write `## New Features` as `- None.` when there are none; `release.sh` requires the heading to be present regardless.
- Pushing a `v*` tag triggers `.github/workflows/package-plugin.yml`, which runs `./build.sh`, uploads the generated zip to the GitHub Release, and uses `release-notes/<version>.md` as the descriptive release body before appending the changelog comparison link.
- `.github/workflows/update-stable-tag.yml` can create and push `v<Stable tag>` from `readme.txt` on `main` pushes, or from a manually supplied version via `workflow_dispatch`.

## Testing

- Use `npm run test:unit` for the PHP unit harness in `tests/php/run.php`.
- Use `npm run test:prod` for the production integration test wrapper in `scripts/test-production.sh`.
- Use `npm run test:dev` when you need the same production test path against `.env.development`.
- `scripts/test-production.sh` expects an env file at `.env.production.local` by default, or a custom file via `EVENTON_APIFY_ENV_FILE`.
- Use `composer quality` for the full gate (`phpcs` lint, `phpstan` analyse, unit tests, performance gate).
- If `composer` is not on PATH, run the gate directly: `vendor/bin/phpcs`, `vendor/bin/phpstan analyse --no-progress --memory-limit=1G`, `php tests/php/run.php`, `php scripts/performance-gate.php`.
- The gate is unit-level only. It cannot verify REST route registration; that needs a post-deploy check against the live site.

## wp/v2 Compatibility Layer

- Never call `current_user_can()` inside `register_post_type_args` / `register_taxonomy_args` filters in `includes/rest-wp-v2-compat.php`. Those fire on `init`, before application-password auth resolves, so the current user is always 0 and `show_in_rest` would be forced false for every REST client.
- Admin-only access is enforced at request time in `includes/wp-v2-compat.php` via `eventon_apify_should_filter_wp_v2_compatibility_for_request()`: a `rest_pre_dispatch` 401, REST index stripping, `/wp/v2/types` + `/wp/v2/taxonomies` response stripping, and the search-query exclusions.
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
