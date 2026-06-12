#!/usr/bin/env bash
# Full WP → Laravel import with verify + post-import ops (staging/production).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../../.." && pwd)"
cd "$ROOT/backend"

DSN="${SVP_MYSQL_DSN:-}"
DUMP="${SVP_WP_DUMP:-}"
PREFIX="${SVP_WP_PREFIX:-wp_}"
OUT="${SVP_IMPORT_LOG:-$ROOT/docs/evidence/import-run-$(date +%F).log}"

mkdir -p "$(dirname "$OUT")"
{
  echo "import-run start $(date -u +%Y-%m-%dT%H:%M:%SZ)"
  if [[ -n "$DSN" ]]; then
    php artisan wp:import --mysql-dsn="$DSN" --prefix="$PREFIX"
  elif [[ -n "$DUMP" && -f "$DUMP" ]]; then
    php artisan wp:import "$DUMP" --prefix="$PREFIX"
  else
    echo "ERROR: set SVP_MYSQL_DSN or SVP_WP_DUMP"
    exit 1
  fi
  bash "$(dirname "$0")/import-verify.sh"
  echo "import-run complete $(date -u +%Y-%m-%dT%H:%M:%SZ)"
} 2>&1 | tee "$OUT"
