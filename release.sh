#!/bin/bash

read -p "Enter new version (e.g. 1.1.0): " VERSION

if [[ ! "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
  echo "Invalid version format. Use semantic versioning: X.Y.Z"
  exit 1
fi

TAG="v$VERSION"

sed -i '' "s/^Stable tag: .*/Stable tag: $VERSION/" readme.txt
sed -i '' "s/^[[:space:]]*\\*[[:space:]]*Version:[[:space:]]*.*/ * Version:           $VERSION/" eventon-apify.php

git add readme.txt eventon-apify.php
git commit -m "Bump version to $VERSION"
git push origin main

echo "Version updated to $VERSION and pushed to main."
echo "Waiting for GitHub Action to auto-tag version $TAG..."

if command -v gh &> /dev/null; then
  if git describe --tags --abbrev=0 >/dev/null 2>&1; then
    CHANGELOG=$(git log "$(git describe --tags --abbrev=0)..HEAD" --pretty=format:"- %s" --no-merges)
  else
    CHANGELOG=$(git log --pretty=format:"- %s" --no-merges)
  fi
  gh release create "$TAG" --title "EventON APIfy $VERSION" --notes "$CHANGELOG" || echo "GitHub release creation failed or already exists."
else
  echo "GitHub CLI (gh) not found. Skipping release creation."
fi
