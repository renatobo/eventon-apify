# Repository Instructions

## Release Versioning

- When bumping or releasing a version, update all user-visible version references together.
- At minimum, keep these in sync:
  - `eventon-apify.php` plugin header `Version`
  - `eventon-apify.php` constant `EVENTON_APIFY_VERSION`
  - `readme.txt` `Stable tag`
- Prefer using `./release.sh <version>` so the release commit, tag, and packaged GitHub release asset stay aligned.
