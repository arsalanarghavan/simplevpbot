#!/usr/bin/env php
<?php
/**
 * One-off: admin-hub hardcoded strings → admin_msg().
 *
 * @package SimpleVPBot
 */

$path = dirname( __DIR__ ) . '/includes/bot/handlers/class-handler-admin-hub.php';
$src  = file_get_contents( $path );
$orig = $src;

$replacements = array(
	"'✅ سرور #' . \$lid . ' active=' . \$new" => "self::admin_msg( 'msg.admin.server_active', \$platform, \$chat_id, array( 'id' => \$lid, 'state' => \$new ) )",
	"'✅ سرور #' . \$lid . ' حذف شد.'" => "self::admin_msg( 'msg.admin.server_deleted', \$platform, \$chat_id, array( 'id' => \$lid ) )",
	"'✅ کاربر #' . \$uid . ' تایید شد.'" => "self::admin_msg( 'msg.admin.user_approved', \$platform, \$chat_id, array( 'id' => \$uid ) )",
	"'✅ کاربر #' . \$uid . ' رد شد.'" => "self::admin_msg( 'msg.admin.user_rejected', \$platform, \$chat_id, array( 'id' => \$uid ) )",
	"'✏️ مقدار جدید را برای «' . \$key . \"» ارسال کنید.\\n/cancel\"" => "self::admin_msg( 'msg.admin.prompt_new_value', \$platform, \$chat_id, array( 'key' => \$key ) )",
	"'📝 ' . \$key . \"\\n➖➖➖\\n\" . \$val" => "self::admin_msg( 'msg.admin.text_preview', \$platform, \$chat_id, array( 'key' => \$key, 'value' => \$val ) )",
	"! empty( \$out['ok'] ) ? '✅ پلن حذف شد.' : '⛔ حذف امکان‌پذیر نیست.'" => "! empty( \$out['ok'] ) ? self::admin_msg( 'msg.admin.plan_deleted_ok', \$platform, \$chat_id ) : self::admin_msg( 'msg.admin.plan_delete_fail', \$platform, \$chat_id )",
	"! empty( \$out['ok'] ) ? '✅ دسته حذف شد.' : ( '⛔ ' . (string) ( \$out['code'] ?? 'رد' ) )" => "! empty( \$out['ok'] ) ? self::admin_msg( 'msg.admin.category_deleted_ok', \$platform, \$chat_id ) : self::admin_msg( 'msg.admin.category_delete_rejected', \$platform, \$chat_id, array( 'code' => (string) ( \$out['code'] ?? 'رد' ) ) )",
	"'✅ تنظیم «' . \$key . '» تغییر کرد.'" => "self::admin_msg( 'msg.admin.setting_changed', \$platform, \$chat_id, array( 'key' => \$key ) )",
	"'⛔ این کلید از ربات قابل سوییچ نیست.'" => "self::admin_msg( 'msg.admin.setting_not_switchable', \$platform, \$chat_id )",
	"'✅ پلن #' . \$pid . ' active=' . \$new" => "self::admin_msg( 'msg.admin.plan_active', \$platform, \$chat_id, array( 'id' => \$pid, 'state' => \$new ) )",
	"'✅ دسته #' . \$cid . ' active=' . \$new" => "self::admin_msg( 'msg.admin.category_active', \$platform, \$chat_id, array( 'id' => \$cid, 'state' => \$new ) )",
	"'✅ کارت #' . \$cid . ' active=' . \$new" => "self::admin_msg( 'msg.admin.card_active', \$platform, \$chat_id, array( 'id' => \$cid, 'state' => \$new ) )",
	"'ℹ️ ناشناخته.'" => "self::admin_msg( 'msg.admin.unknown', \$platform, \$chat_id )",
	"'متنی ثبت نشده.'" => "self::admin_msg( 'msg.admin.no_text_saved', \$platform, \$chat_id )",
	"'⛔ ' . (string) ( \$r['reason'] ?? 'خطا' )" => "self::admin_msg( 'msg.admin.error_generic', \$platform, \$chat_id, array( 'reason' => (string) ( \$r['reason'] ?? 'خطا' ) ) )",
	"'⛔ پلن قابل نمایش در دکمه نیست (شناسه‌ها بزرگند). با ادمین وردپرس اقدام کنید.'" => "self::admin_msg( 'msg.admin.plan_ids_too_large', \$platform, \$chat_id )",
	"'⛔ پلن per-GB اشتباه پیکربندی شده است.'" => "self::admin_msg( 'msg.admin.plan_pergb_misconfigured', \$platform, \$chat_id )",
	"'⛔ روش پرداخت نامعتبر است.'" => "self::admin_msg( 'msg.admin.pay_method_invalid', \$platform, \$chat_id )",
	"'⛔ پلن نامعتبر است.'" => "self::admin_msg( 'msg.admin.plan_invalid', \$platform, \$chat_id )",
	"'⛔ حجم برای این پلن معتبر نیست.'" => "self::admin_msg( 'msg.admin.volume_invalid_for_plan', \$platform, \$chat_id )",
	"'⛔ برای پلن ثابت حجم نفرستید؛ دوباره از ابتدا شروع کنید.'" => "self::admin_msg( 'msg.admin.fixed_plan_no_volume', \$platform, \$chat_id )",
	"'⛔ پنل فعالی نیست.'" => "self::admin_msg( 'msg.admin.panel_inactive', \$platform, \$chat_id )",
	"'✅ ثبت‌نام پردازش شد.'" => "self::admin_msg( 'msg.admin.signup_processed', \$platform, \$chat_id )",
	"'✅ رد ثبت‌نام ثبت شد.'" => "self::admin_msg( 'msg.admin.signup_rejected_recorded', \$platform, \$chat_id )",
	"'⛔ فقط ادمین پلتفرم می‌تواند متن‌ها را بازنشانی کند.'" => "self::admin_msg( 'msg.admin.texts_reset_denied', \$platform, \$chat_id )",
	"'✅ همهٔ متن‌ها به پیش‌فرض نسخهٔ فعلی برگردانده شد.'" => "self::admin_msg( 'msg.admin.texts_reset_ok', \$platform, \$chat_id )",
	"'ℹ️ حالت/ویزارد لغو شد.'" => "self::admin_msg( 'msg.admin.wizard_cancelled', \$platform, \$chat_id )",
	"'⛔ لینک پورتال برای این چت تنظیم نشده.'" => "self::admin_msg( 'msg.admin.portal_link_unset', \$platform, \$chat_id )",
	"'⛔ لینک پنل ادمین وب در دسترس نیست.'" => "self::admin_msg( 'msg.admin.admin_panel_unset', \$platform, \$chat_id )",
	"'' !== \$url ? \$url : '⛔ لینک خالی است.'" => "'' !== \$url ? \$url : self::admin_msg( 'msg.admin.link_empty', \$platform, \$chat_id )",
);

foreach ( $replacements as $from => $to ) {
	$src = str_replace( $from, $to, $src );
}

// Multi-line prompts (heredoc-style strings).
$prompts = array(
	array(
		'"♻️ تمدید سرویس #{$sid}\nیک حرف بفرستید: w یا f یا i\n/cancel"',
		"self::admin_msg( 'msg.admin.prompt_renew_line', \$platform, \$chat_id, array( 'id' => \$sid ) )",
	),
	array(
		'"➕ حجم سرویس #{$sid}\nدو بخش: <code>گیگ mode</code> (مثل: 10 w)\n/cancel"',
		"self::admin_msg( 'msg.admin.prompt_add_volume_line', \$platform, \$chat_id, array( 'id' => \$sid ) )",
	),
	array(
		"'💰 شارژ کیف پول کاربر #' . \$tuid . \"\\nمبلغ را فقط به تومان (عدد) بفرستید.\\n/cancel\"",
		"self::admin_msg( 'msg.admin.prompt_wallet_credit', \$platform, \$chat_id, array( 'id' => \$tuid ) )",
	),
	array(
		"'📉 کاهش از کیف پول کاربر #' . \$tuid . \"\\nمبلغ را فقط به تومان (عدد) بفرستید.\\n/cancel\"",
		"self::admin_msg( 'msg.admin.prompt_wallet_debit', \$platform, \$chat_id, array( 'id' => \$tuid ) )",
	),
);

foreach ( $prompts as $pair ) {
	$src = str_replace( $pair[0], $pair[1], $src );
}

$bulk_old = <<<'TXT'
				"➕ عملیات گروهی Xray\nبرای اجرا دقیقا بفرستید:\n<code>ok روز [گیگ]</code>\nمثال فقط روز: <code>ok 7</code>\nمثال روز+حجم: <code>ok 3 5</code>\n/cancel",
TXT;
$bulk_new = "self::admin_msg( 'msg.admin.prompt_bulk_xray', \$platform, \$chat_id )";
$src      = str_replace( $bulk_old, $bulk_new, $src );

$ipn_old = "'✅ مسیر IPN جدید ذخیره شد.' . ( \$uipn ? \"\\n🔗 \" . \$uipn : '' )";
$ipn_new = "self::admin_msg( 'msg.admin.ipn_saved', \$platform, \$chat_id ) . ( \$uipn ? \"\\n🔗 \" . \$uipn : '' )";
$src     = str_replace( $ipn_old, $ipn_new, $src );

if ( $src !== $orig ) {
	file_put_contents( $path, $src );
	echo "Updated admin-hub\n";
} else {
	echo "No changes\n";
}
