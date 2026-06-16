#!/bin/bash

set -euo pipefail

VERSION="${1:-}"

if [[ -z "$VERSION" ]]; then
  read -r -p "Enter new version (e.g. 1.2.1): " VERSION
fi

if [[ ! "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
  echo "Invalid version format. Use semantic versioning: X.Y.Z"
  exit 1
fi

TAG="v$VERSION"
NOTES_FILE="release-notes/$VERSION.md"

if ! git diff --quiet || ! git diff --cached --quiet; then
  echo "Working tree is not clean. Commit or stash changes before running a release."
  exit 1
fi

if git rev-parse "$TAG" >/dev/null 2>&1; then
  echo "Tag $TAG already exists."
  exit 1
fi

if [[ ! -f "$NOTES_FILE" ]]; then
  echo "Missing release notes file: $NOTES_FILE"
  echo "Create it with these sections before releasing:"
  echo "  ## New Features"
  echo "  ## Improvements"
  echo "  ## Bug Fixes"
  exit 1
fi

for heading in "## New Features" "## Improvements" "## Bug Fixes"; do
  if ! grep -Fq "$heading" "$NOTES_FILE"; then
    echo "Release notes file $NOTES_FILE is missing required heading: $heading"
    exit 1
  fi
done

echo "Using release notes from $NOTES_FILE"

update_file() {
  local file_path="$1"
  local search_pattern="$2"
  local replacement="$3"
  local tmp_file

  tmp_file="$(mktemp)"
  sed "s/${search_pattern}/${replacement}/" "$file_path" > "$tmp_file"
  mv "$tmp_file" "$file_path"
}

extract_plugin_header_version() {
  sed -n 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*//p' "eventon-apify.php" | head -n 1
}

extract_plugin_constant_version() {
  sed -n "s/^define('EVENTON_APIFY_VERSION', '\\(.*\\)');$/\\1/p" "eventon-apify.php" | head -n 1
}

extract_stable_tag_version() {
  sed -n 's/^Stable tag: //p' "readme.txt" | head -n 1
}

extract_openapi_version() {
  sed -n 's/.*"version": "\([0-9][^"]*\)".*/\1/p' "docs/eventon-apify-openapi.json" | head -n 1
}

assert_versions_match() {
  local header_version
  local constant_version
  local stable_tag_version
  local openapi_version

  header_version="$(extract_plugin_header_version)"
  constant_version="$(extract_plugin_constant_version)"
  stable_tag_version="$(extract_stable_tag_version)"
  openapi_version="$(extract_openapi_version)"

  if [[ "$header_version" != "$VERSION" || "$constant_version" != "$VERSION" || "$stable_tag_version" != "$VERSION" || "$openapi_version" != "$VERSION" ]]; then
    echo "Version mismatch detected after update:"
    echo "  Plugin header: ${header_version:-missing}"
    echo "  EVENTON_APIFY_VERSION: ${constant_version:-missing}"
    echo "  Stable tag: ${stable_tag_version:-missing}"
    echo "  OpenAPI info.version: ${openapi_version:-missing}"
    echo "Expected all to equal $VERSION."
    exit 1
  fi
}

update_file "readme.txt" "^Stable tag: .*" "Stable tag: $VERSION"
update_file "eventon-apify.php" "^[[:space:]]*\\*[[:space:]]*Version:[[:space:]]*.*" " * Version:           $VERSION"
update_file "eventon-apify.php" "^define('EVENTON_APIFY_VERSION', '.*');$" "define('EVENTON_APIFY_VERSION', '$VERSION');"
update_file "docs/eventon-apify-openapi.json" "\"version\": \"[^\"]*\"" "\"version\": \"$VERSION\""
update_file "docs/eventon-apify-openapi.json" "\"provider_version\": \"[^\"]*\"" "\"provider_version\": \"$VERSION\""

assert_versions_match

git add readme.txt eventon-apify.php docs/eventon-apify-openapi.json "$NOTES_FILE"

if git diff --cached --quiet; then
  echo "No version changes to commit; files already at $VERSION."
else
  git commit -m "Bump version to $VERSION"
fi

git tag -a "$TAG" -m "Release $VERSION"
git push origin main
git push origin "$TAG"

echo "Building plugin zip locally with ./build.sh"
./build.sh

cat <<EOF
Release prepared for $TAG.

GitHub Actions will now:
- build the WordPress plugin zip with ./build.sh
- create or update the GitHub Release for $TAG
- attach the generated versioned zip asset
EOF
