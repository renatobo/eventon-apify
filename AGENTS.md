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
- Prefer using `./release.sh <version>` so the release commit, tag, and packaged GitHub release asset stay aligned.

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
