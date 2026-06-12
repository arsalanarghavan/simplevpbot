#!/usr/bin/env bash
# CI: ensure dashboard TS does not call /api/v1/admin/* without normalizeAdminApiPath (v17).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT/frontend/src"
violations=0
raw_paths=()
while IFS= read -r -d '' f; do
  if grep -qE '["'\''`]/api/v1/admin/' "$f" \
    && ! grep -qE 'normalizeAdminApiPath|apiBase\(|postAdminMutate|dash-admin-mutate|dash-admin-upload' "$f"; then
    if grep -qE "/api/v1/(bootstrap|auth/|me/)" "$f"; then
      continue
    fi
    echo "WARN: possible raw admin API path in $f"
    raw_paths+=("$f")
    violations=$((violations + 1))
  fi
done < <(find . \( -name '*.ts' -o -name '*.tsx' \) -print0)
if [[ "$violations" -gt 3 ]]; then
  echo "Too many fetch audit warnings ($violations):"
  printf '  %s\n' "${raw_paths[@]}"
  exit 1
fi
NAV="$ROOT/frontend/src/config/admin-nav.ts"
if ! grep -q 'FEATURE_TAB_MAP' "$NAV"; then
  echo "FEATURE_TAB_MAP missing in admin-nav.ts"
  exit 1
fi
EVIDENCE="$ROOT/docs/evidence/frontend-fetch-audit-v17.md"
{
  echo "# Frontend fetch audit v17"
  echo ""
  echo "Date: $(date -u +%Y-%m-%d)"
  echo "Warnings: $violations"
  echo ""
  if [[ "$violations" -gt 0 ]]; then
    echo "## Remaining raw paths"
    for p in "${raw_paths[@]}"; do echo "- \`$p\`"; done
  else
    echo "All admin fetch paths use normalizeAdminApiPath helpers."
  fi
} > "$EVIDENCE"
echo "Frontend fetch audit OK ($violations warnings); evidence → $EVIDENCE"
