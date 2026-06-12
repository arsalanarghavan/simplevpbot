#!/usr/bin/env bash
# Pre-cutover checklist — validates tooling without mutating production.
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../../.." && pwd)"
cd "$ROOT/backend"
BASE="${SVP_BASE_URL:-http://localhost:8080}"
LOG="${SVP_PREFLIGHT_LOG:-$ROOT/docs/evidence/cutover-preflight-$(date +%F).log}"

mkdir -p "$(dirname "$LOG")"
{
  echo "cutover-preflight start $(date -u +%Y-%m-%dT%H:%M:%SZ)"
  echo "== artisan commands =="
  php artisan --version
  php artisan schedule:list | tee /tmp/svp-schedule.txt
  for job in svp:expiry svp:purge_expired svp:inbound_queue_drain svp:backup; do
    grep -q "$job" /tmp/svp-schedule.txt && echo "OK schedule: $job" || echo "MISSING schedule: $job"
  done
  echo "== ops scripts executable =="
  for s in import-run.sh import-verify.sh post-import-ops.sh staging-cutover-runbook.sh soak-24h.sh; do
    test -x "scripts/ops/$s" && echo "OK exec $s" || echo "WARN not executable $s"
  done
  echo "== HTTP smoke base=$BASE =="
  curl -sf "${BASE}/health/ready" && echo "OK health/ready" || echo "FAIL health/ready"
  curl -sf "${BASE}/health" && echo "OK health" || echo "FAIL health"
  echo "cutover-preflight complete $(date -u +%Y-%m-%dT%H:%M:%SZ)"
} 2>&1 | tee "$LOG"
