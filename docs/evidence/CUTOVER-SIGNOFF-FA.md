# Cutover Sign-off — Staging / Production

چک‌لیست evidence برای [`WP-DECOMMISSION-FA.md`](WP-DECOMMISSION-FA.md).

## Automated (repo scripts + CI)

| Step | Command | Evidence |
|------|---------|----------|
| CI preflight | `backend/scripts/ops/cutover-preflight.sh` (GitHub Actions `backend` job) | `docs/evidence/cutover-preflight-YYYY-MM-DD.log` |
| CI short soak | `SVP_SOAK_DURATION_SEC=30 soak-24h.sh` | workflow log |
| CI load smoke | `SVP_LOAD_REQUESTS=20 load-smoke.sh` | workflow log |
| Nightly soak | `.github/workflows/nightly-soak.yml` | `docs/evidence/soak-nightly-YYYY-MM-DD.log` artifact |
| Import verify only | `SVP_MYSQL_DSN=... backend/scripts/ops/import-verify.sh` | `docs/evidence/import-verify-YYYY-MM-DD.log` |
| Import + verify | `SVP_MYSQL_DSN=... backend/scripts/ops/staging-cutover-runbook.sh` | log output |
| HTTP smoke | `SVP_BASE_URL=... backend/scripts/ops/staging-e2e.sh` | exit 0 |
| Checklist | `SVP_BASE_URL=... backend/scripts/ops/staging-cutover-checklist.sh` | exit 0 |
| Soak 24h | `SVP_SOAK_DURATION_SEC=86400 SVP_BASE_URL=... backend/scripts/ops/soak-24h.sh` | `docs/evidence/soak-24h-YYYY-MM-DD.log` |
| Rollback drill | `backend/scripts/ops/rollback-drill.sh` | `docs/evidence/rollback-drill.log` |
| WP disable (staging) | `WP_PATH=... backend/scripts/ops/wp-disable-staging.sh` | manual confirm |

## Manual sign-off

- [ ] Portal admin `?svp_adm=1` — stats, membership, create_service
- [ ] Portal sub plain + HTML
- [ ] Bot webhook (direct + relay)
- [ ] Crypto IPN test transaction
- [ ] Dashboard login + mutate smoke
- [ ] Scheduler 14 jobs running

## Production cutover

Runbook: [`CUTOVER-STAGING-FA.md`](CUTOVER-STAGING-FA.md) + DNS change ticket.

Operator / date: _______________
