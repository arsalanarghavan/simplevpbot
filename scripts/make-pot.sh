#!/usr/bin/env bash
# Regenerate languages/simplevpbot.pot from gettext-style calls (__(), _e(), …).
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"
LIST="$(mktemp)"
trap 'rm -f "$LIST"' EXIT
find includes simplevpbot.php uninstall.php -name '*.php' -type f | sort >"$LIST"
xgettext --language=PHP --from-code=UTF-8 \
	--keyword=__:1 --keyword=_e:1 --keyword=_n:1,2 --keyword=_x:1c,2 --keyword=_ex:1c,2 \
	--keyword=esc_html__:1 --keyword=esc_attr__:1 --keyword=esc_html_e:1 --keyword=esc_attr_e:1 \
	--keyword=_nx:1c,2,3 \
	--package-name=SimpleVPBot --package-version=1.0.2 \
	--copyright-holder="ArsalanArghavan.ir" \
	--msgid-bugs-address="https://github.com/simplevpbot/simplevpbot" \
	--files-from="$LIST" \
	-d simplevpbot \
	-o languages/simplevpbot.pot
# Tidy boilerplate header (xgettext emits generic TITLE line); avoid sed -i (some FS permission noise).
TMP_POT="${ROOT}/languages/.simplevpbot.pot.$$"
sed '1,6s/# SOME DESCRIPTIVE TITLE\./# Translation template for SimpleVPBot (WordPress plugin)./' languages/simplevpbot.pot \
	| sed '2s/# Copyright (C) YEAR /# Copyright (C) /' \
	| sed '/^# FIRST AUTHOR/d' >"$TMP_POT"
mv -f "$TMP_POT" languages/simplevpbot.pot
echo "Wrote languages/simplevpbot.pot"
