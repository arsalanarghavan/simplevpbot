#!/usr/bin/env php
<?php
/** DEPRECATED (v13 ARCH-11): WP includes/ archived. */
fwrite(STDERR, "DEPRECATED: Laravel dashboard replaces WP admin handlers.\n");
exit(2);

$path = dirname( __DIR__ ) . '/includes/bot/handlers/class-handler-admin.php';
$src  = file_get_contents( $path );
$orig = $src;

$replacements = array(
	"'⛔ فقط فایل .zip بکاپ SimpleVPBot.'" => "SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.backup.zip_only', \$user )",
	"'⛔ دانلود فایل ناموفق: ' . \$down->get_error_message()" => "SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.backup.download_fail', \$user, array( 'error' => \$down->get_error_message() ) )",
	"'⛔ ریستور ناموفق: ' . \$res->get_error_message()" => "SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.backup.restore_fail', \$user, array( 'error' => \$res->get_error_message() ) )",
	'"✅ بازگردانی ادغامی انجام شد.\nکاربران: {$matched} تطبیق · {$inserted} جدید · {$skipped} رد"' => "SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.backup.restore_ok', \$user, array( 'matched' => (string) \$matched, 'inserted' => (string) \$inserted, 'skipped' => (string) \$skipped ) )",
	"'📊 آمار در دسترس نیست.'" => "SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.stats_unavailable', \$user )",
	"'👥 مدیریت کاربران — یکی را انتخاب کنید:'" => "SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.users_submenu', \$user )",
	"'💰 مالی — یکی را انتخاب کنید:'" => "SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.finance_submenu', \$user )",
	"'🆔 شناسه سرویس (svp_services.id) را ارسال کنید:'" => "SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_service_id', \$user )",
	"'📣 متن پیام همگانی را ارسال کنید:'" => "SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.prompt_broadcast', \$user )",
	"'ℹ️ لغو شد.'" => "SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.cancelled', \$user )",
	"'✅ فاصله بکاپ: ' . \$m . ' دقیقه'" => "SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.backup.interval_saved', \$user, array( 'minutes' => (string) \$m ) )",
	"'✅ chat id تلگرام ذخیره شد: ' . (int) \$trimn" => "SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.backup.tg_chat_saved', \$user, array( 'id' => (string) (int) \$trimn ) )",
	"'✅ chat id بله ذخیره شد: ' . (int) \$trimn" => "SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.backup.bl_chat_saved', \$user, array( 'id' => (string) (int) \$trimn ) )",
	"'⏳ فقط فایل .zip بکاپ را بفرستید (/cancel — لغو).'" => "SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.backup.send_zip', \$user )",
	"'⏳ فقط یک عدد معتبر ارسال کنید یا /cancel'" => "SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.backup.send_number', \$user )",
	"'ℹ️ برای ریستور: از «🔧 تنظیمات پیشرفته» → «💾 پشتیبان‌گیری» → 📥 ریستور (۲ مرحله) اقدام کنید و سپس فایل .zip بفرستید.'" => "SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.backup.restore_hint', \$user )",
	"'⛔ کاربر مقصد نامعتبر بود.'" => "SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.target_invalid', \$user )",
	"'⛔ کاربر چت تلگرام/بله ندارد.'" => "SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.user_no_chat', \$user )",
	"'✅ پیام ارسال شد.'" => "SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.message_sent', \$user )",
	"'⛔ مقصد نامعتبر بود.'" => "SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.destination_invalid', \$user )",
	"'⛔ کاربر یافت نشد.'" => "SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.user_not_found', \$user )",
	"'⛔ فقط عدد تومان بفرستید (مثلاً 50000).'" => "SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.wallet_toman_only', \$user )",
	"'⛔ مبلغ باید بزرگ‌تر از صفر باشد.'" => "SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.wallet_amount_positive', \$user )",
	"'⛔ کاربری یافت نشد. عبارت دیگری بفرستید یا از منو دوباره جستجو کنید.'" => "SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.find_user_none', \$user )",
	"'🔎 چند کاربر پیدا شد؛ یکی را انتخاب کنید:'" => "SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.find_user_pick', \$user )",
	"'📣 پیام در صف ارسال قرار گرفت.'" => "SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.broadcast_queued', \$user )",
	"'ℹ️ گزینه را از منوی ادمین انتخاب کنید.'" => "SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.menu_pick_option', \$user )",
	"'⛔ کاربر مقصد یافت نشد یا مبهم است. دوباره بفرستید یا /start را بزنید.'" => "SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.transfer_target_ambiguous', \$user )",
	"'⛔ انتقال انجام نشد: ' . (string) ( \$res['reason'] ?? 'err' )" => "SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.error_generic', \$user, array( 'reason' => (string) ( \$res['reason'] ?? 'err' ) ) )",
	"'⛔ پلن نامعتبر است.'" => "SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.plan_invalid', \$user )",
	"'⛔ فقط یک عدد صحیح (گیگابایت) بفرستید.'" => "SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.ns_volume_integer', \$user )",
	"'⛔ خطای داخلی دکمه‌ها (شناسه‌ها بزرگند).'" => "SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.internal_button_error', \$user )",
	"'⛔ سه بخش لازم است: plan_id حجم mode (w|f|i)'" => "SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.line.ns_parts', \$user )",
	"'⛔ mode باید w یا f یا i باشد.'" => "SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.line.mode_wfi', \$user )",
	"'⛔ فقط یک حرف: w یا f یا i'" => "SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.line.renew_one_char', \$user )",
	"'⛔ دو بخش: گیگ mode(w|f|i)'" => "SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.line.two_parts', \$user )",
	"'⛔ mode نامعتبر.'" => "SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.line.mode_invalid', \$user )",
	"'⛔ برای تأیید دقیقا بفرستید: ok days [gb]'" => "SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.line.bulk_confirm', \$user )",
	"'⛔ عدد نامعتبر.'" => "SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.line.bulk_invalid', \$user )",
);

foreach ( $replacements as $from => $to ) {
	$src = str_replace( $from, $to, $src );
}

// Multi-line patterns.
$src = preg_replace(
	'/SimpleVPBot_Bot_Runtime::send_message\(\s*\$platform,\s*\$chat_id,\s*"⛔ حجم باید بین \{\$min_f\} و \{\$max_f\} گیگابایت باشد\."\s*\)/',
	'SimpleVPBot_Bot_Runtime::send_message( $platform, $chat_id, SimpleVPBot_Bot_Admin_Texts::msg( \'msg.admin.ns_volume_range\', $user, array( \'min\' => $min_f, \'max\' => $max_f ) ) )',
	$src,
	1
);

$src = str_replace(
	"'⛔ موجودی کافی نیست. موجودی فعلی: ' . number_format( \$bal ) . ' تومان.'",
	"SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.wallet_insufficient', \$user, array( 'balance' => number_format( \$bal ) ) )",
	$src
);

$src = str_replace(
	"'✅ ' . number_format( \$amt ) . ' تومان از کیف پول کاربر #' . \$tuid . ' کسر شد.'",
	"SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.wallet_debited', \$user, array( 'amount' => number_format( \$amt ), 'id' => (string) \$tuid ) )",
	$src
);

$src = str_replace(
	"'✅ ' . number_format( \$amt ) . ' تومان به کیف پول کاربر #' . \$tuid . ' اضافه شد.'",
	"SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.wallet_credited', \$user, array( 'amount' => number_format( \$amt ), 'id' => (string) \$tuid ) )",
	$src
);

$src = str_replace(
	"'✅ سرویس #' . \$sid . ' به کاربر #' . (int) \$target->id . ' منتقل شد.'",
	"SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.transfer_ok', \$user, array( 'sid' => (string) \$sid, 'uid' => (string) (int) \$target->id ) )",
	$src
);

$src = str_replace(
	"! empty( \$r['ok'] ) ? '✅ تمدید انجام شد.' : ( '⛔ ' . (string) ( \$r['reason'] ?? '' ) )",
	"! empty( \$r['ok'] ) ? SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.line.renew_ok', \$user ) : SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.error_generic', \$user, array( 'reason' => (string) ( \$r['reason'] ?? '' ) ) )",
	$src
);

$src = str_replace(
	"! empty( \$r['ok'] ) ? '✅ حجم اعمال شد.' : ( '⛔ ' . (string) ( \$r['reason'] ?? '' ) )",
	"! empty( \$r['ok'] ) ? SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.line.volume_ok', \$user ) : SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.error_generic', \$user, array( 'reason' => (string) ( \$r['reason'] ?? '' ) ) )",
	$src
);

$src = str_replace(
	"\$msg = isset( \$r['service_id'] ) ? '✅ سرویس #' . (int) \$r['service_id'] : '✅ فاکتور ارسال شد (سفارش #' . (int) ( \$r['transaction_id'] ?? 0 ) . ').';",
	"\$msg = isset( \$r['service_id'] )\n\t\t\t\t? SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.line.service_created', \$user, array( 'id' => (string) (int) \$r['service_id'] ) )\n\t\t\t\t: SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.line.invoice_sent', \$user, array( 'id' => (string) (int) ( \$r['transaction_id'] ?? 0 ) ) );",
	$src
);

// Bulk result lines.
$src = str_replace(
	"\$out .= 'روز: ok=' . (int) \$rd['done'] . ' err=' . (int) \$rd['errors'] . \"\\n\";",
	"\$out .= SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.line.bulk_days_result', \$user, array( 'done' => (string) (int) \$rd['done'], 'errors' => (string) (int) \$rd['errors'] ) ) . \"\\n\";",
	$src
);

$src = str_replace(
	"\$out .= 'حجم: ok=' . (int) \$rg['done'] . ' err=' . (int) \$rg['errors'] . \"\\n\";",
	"\$out .= SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.line.bulk_gb_result', \$user, array( 'done' => (string) (int) \$rg['done'], 'errors' => (string) (int) \$rg['errors'] ) ) . \"\\n\";",
	$src
);

// DM body prefix.
$src = str_replace(
	"\$body = \"📩 پیام از پشتیبانی\\n➖➖➖➖➖➖➖➖\\n\" . (string) \$text;",
	"\$body = SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.dm_body_prefix', \$user, array( 'body' => (string) \$text ) );",
	$src
);

// Transfer service prompt.
$src = preg_replace(
	"/SimpleVPBot_Bot_Runtime::send_message\(\s*\n\s*\$platform,\s*\n\s*\$chat_id,\s*\n\s*'🎁 انتقال سرویس #' \. \$sid \. ' \(مالک فعلی: ' \. \(int\) \$svc->user_id \. \"\\\\nشناسه مقصد را ارسال کنید:[^']+'\"\s*\n\s*\);/s",
	"SimpleVPBot_Bot_Runtime::send_message(\n\t\t\t\t\$platform,\n\t\t\t\t\$chat_id,\n\t\t\t\tSimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.transfer_prompt', \$user, array( 'id' => (string) \$sid, 'owner' => (string) (int) \$svc->user_id ) )\n\t\t\t);",
	$src,
	1
);

// NS pick mode text.
$src = preg_replace(
	"/\$txt\s*=\s*\"➕ ساخت سرویس برای #\{\$tuid_f\}\\\\n📦 حجم: \{\$gb_f\} گیگابایت\\\\n۳\) روش اعمال را انتخاب کنید:\";/",
	"\$txt = SimpleVPBot_Bot_Admin_Texts::msg( 'msg.admin.ns_pick_mode', \$user, array( 'uid' => \$tuid_f, 'gb' => \$gb_f ) );",
	$src,
	1
);

if ( $src !== $orig ) {
	file_put_contents( $path, $src );
	echo "Updated admin.php\n";
} else {
	echo "No changes\n";
}
