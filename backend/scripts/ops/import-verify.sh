#!/usr/bin/env bash
# Run wp:import --verify-only and save evidence log.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../../.." && pwd)"
cd "$ROOT/backend"

DSN="${SVP_MYSQL_DSN:-}"
DUMP="${SVP_WP_DUMP:-}"
PREFIX="${SVP_WP_PREFIX:-wp_}"
OUT="${SVP_VERIFY_LOG:-$ROOT/docs/evidence/import-verify-$(date +%F).log}"

mkdir -p "$(dirname "$OUT")"
{
  echo "import-verify start $(date -u +%Y-%m-%dT%H:%M:%SZ)"
  if [[ -n "$DSN" ]]; then
    php artisan wp:import --mysql-dsn="$DSN" --prefix="$PREFIX" --verify-only
  elif [[ -n "$DUMP" && -f "$DUMP" ]]; then
    php artisan wp:import "$DUMP" --prefix="$PREFIX" --verify-only
  else
    echo "ERROR: set SVP_MYSQL_DSN or SVP_WP_DUMP"
    exit 1
  fi
  echo "import-verify complete $(date -u +%Y-%m-%dT%H:%M:%SZ)"
  if [[ "${SVP_SKIP_POST_IMPORT:-}" != "1" ]]; then
    bash "$(dirname "$0")/post-import-ops.sh"
  fi
} 2>&1 | tee "$OUT"
