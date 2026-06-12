#!/usr/bin/env bash
# Disable WP SimpleVPBot crons/webhooks on staging (requires wp-cli + WP path).
set -euo pipefail

WP_PATH="${WP_PATH:-/var/www/wordpress}"
PLUGIN="${WP_PLUGIN:-simplevpbot}"

if ! command -v wp >/dev/null 2>&1; then
  echo "wp-cli not found — manual steps in docs/WP-DECOMMISSION-FA.md §2–3"
  exit 0
fi

echo "=== WP disable staging path=$WP_PATH plugin=$PLUGIN ==="

wp cron event list --path="$WP_PATH" --format=table | grep -i simplevpbot || true

for hook in $(wp cron event list --path="$WP_PATH" --fields=hook --format=csv 2>/dev/null | grep -i simplevpbot || true); do
  echo "Unscheduling $hook"
  wp cron event delete "$hook" --path="$WP_PATH" || true
done

echo "Deactivating plugin (rollback-friendly — not deleting files)"
wp plugin deactivate "$PLUGIN" --path="$WP_PATH" || true

echo "Done — verify Laravel-only flows per staging-cutover-checklist.sh"
