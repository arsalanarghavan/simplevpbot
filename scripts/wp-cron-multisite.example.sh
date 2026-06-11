#!/usr/bin/env bash
# Ping wp-cron.php for every WordPress site on this VPS (one crontab entry).
#
# Usage:
#   1. Copy to /usr/local/bin/wp-cron-multisite.sh and chmod +x
#   2. Edit SITE_URLS below
#   3. crontab -e:
#        */5 * * * * /usr/local/bin/wp-cron-multisite.sh
#   4. On each site wp-config.php (recommended):
#        define('DISABLE_WP_CRON', true);
#
# SimpleVPBot also pings wp-cron on traffic/webhooks; this script is for
# idle sites or maximum schedule accuracy (e.g. 30-minute backups).

set -euo pipefail

SITE_URLS=(
  "https://goatvps.ir/wp-cron.php?doing_wp_cron=1"
  # "https://site2.example/wp-cron.php?doing_wp_cron=1"
)

CURL_OPTS=(-fsS -m 30 -o /dev/null)

for url in "${SITE_URLS[@]}"; do
  [[ -z "${url// }" ]] && continue
  curl "${CURL_OPTS[@]}" "$url" || echo "wp-cron failed: $url" >&2
done
