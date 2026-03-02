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

if ! git diff --quiet || ! git diff --cached --quiet; then
  echo "Working tree is not clean. Commit or stash changes before running a release."
  exit 1
fi

if git rev-parse "$TAG" >/dev/null 2>&1; then
  echo "Tag $TAG already exists."
  exit 1
fi

update_file() {
  local file_path="$1"
  local search_pattern="$2"
  local replacement="$3"
  local tmp_file

  tmp_file="$(mktemp)"
  sed "s/${search_pattern}/${replacement}/" "$file_path" > "$tmp_file"
  mv "$tmp_file" "$file_path"
}

update_file "readme.txt" "^Stable tag: .*" "Stable tag: $VERSION"
update_file "eventon-apify.php" "^[[:space:]]*\\*[[:space:]]*Version:[[:space:]]*.*" " * Version:           $VERSION"

git add readme.txt eventon-apify.php
git commit -m "Bump version to $VERSION"
git tag -a "$TAG" -m "Release $VERSION"
git push origin main
git push origin "$TAG"

cat <<EOF
Release prepared for $TAG.

GitHub Actions will now:
- build the WordPress plugin zip with ./build.sh
- create or update the GitHub Release for $TAG
- attach the generated versioned zip asset
EOF
