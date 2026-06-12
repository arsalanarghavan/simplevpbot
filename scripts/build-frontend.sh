#!/usr/bin/env bash
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT/frontend"
npm ci
npm run build
mkdir -p "$ROOT/assets/dashboard/dist"
rsync -a --delete "$ROOT/frontend/dist/" "$ROOT/assets/dashboard/dist/"
if [[ -f "$ROOT/assets/portal.js" && -f "$ROOT/assets/portal.css" ]]; then
  mkdir -p "$ROOT/backend/public/portal"
  cp "$ROOT/assets/portal.js" "$ROOT/backend/public/portal/portal.js"
  cp "$ROOT/assets/portal.css" "$ROOT/backend/public/portal/portal.css"
fi
echo "Built: $ROOT/frontend/dist (mirrored to assets/dashboard/dist)"
