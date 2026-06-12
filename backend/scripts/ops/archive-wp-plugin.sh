#!/usr/bin/env bash
# Archive WP plugin sources to git branch archive/wp-plugin (final decommission step).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../../.." && pwd)"
cd "$ROOT"

if ! git rev-parse --git-dir >/dev/null 2>&1; then
  echo "Not a git repo"
  exit 1
fi

BRANCH="archive/wp-plugin"
if git show-ref --verify --quiet "refs/heads/$BRANCH"; then
  echo "Branch $BRANCH already exists"
else
  git branch "$BRANCH" HEAD
  echo "Created branch $BRANCH from current HEAD"
fi

echo "includes/ remains in tree until physical removal is approved."
echo "Deploy should nginx proxy only to Laravel — see docs/WP-DECOMMISSION-FA.md"
