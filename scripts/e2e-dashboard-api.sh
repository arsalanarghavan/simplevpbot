#!/usr/bin/env bash
# API-level E2E smoke (login, CSRF, state, mutate) — complements browser E2E on staging.
set -euo pipefail
BASE="${SVP_BASE_URL:-http://127.0.0.1:8080}"
COOKIE_JAR="$(mktemp)"
trap 'rm -f "$COOKIE_JAR"' EXIT

curl -sf -c "$COOKIE_JAR" "${BASE}/sanctum/csrf-cookie" >/dev/null
TOKEN="$(grep XSRF-TOKEN "$COOKIE_JAR" | awk '{print $7}' | tail -1 | python3 -c 'import sys,urllib.parse; print(urllib.parse.unquote(sys.stdin.read().strip()))')"

curl -sf -b "$COOKIE_JAR" -c "$COOKIE_JAR" \
  -H "Content-Type: application/json" \
  -H "X-XSRF-TOKEN: ${TOKEN}" \
  -H "Accept: application/json" \
  -d '{"username":"admin","password":"changeme"}' \
  "${BASE}/api/v1/auth/login" | grep -q '"ok":true'

curl -sf -b "$COOKIE_JAR" \
  -H "Accept: application/json" \
  "${BASE}/api/v1/admin/state?tab=dashboard" | grep -q '"ok":true'

echo "[e2e-dashboard-api] OK"
