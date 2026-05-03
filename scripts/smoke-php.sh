#!/usr/bin/env bash
# Syntax-check all plugin PHP under includes/ and run PHPUnit smoke tests.
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
echo "== php -l includes/**/*.php =="
find includes -name '*.php' -print0 | xargs -0 -n1 php -l >/dev/null
echo "OK"
if [[ -f vendor/bin/phpunit ]] && php -r 'exit(extension_loaded("dom") && extension_loaded("xml") ? 0 : 1);' 2>/dev/null; then
	echo "== composer test (phpunit) =="
	composer test
elif [[ -f tests/run-smoke-tests.php ]]; then
	echo "== tests/run-smoke-tests.php (PHPUnit skipped: need php-xml) =="
	php tests/run-smoke-tests.php
else
	echo "SKIP: no smoke runner"
fi
