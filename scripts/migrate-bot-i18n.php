#!/usr/bin/env php
<?php
/** DEPRECATED (v13 ARCH-11): WP includes/ archived — texts live in Laravel `TextService`. */
fwrite(STDERR, "DEPRECATED: use backend TextService + dashboard texts UI.\n");
exit(2);

$root = dirname( __DIR__ );
$files = array(
	$root . '/includes/bot/handlers/class-handler-service.php',
	$root . '/includes/bot/handlers/class-handler-buy.php',
	$root . '/includes/bot/handlers/class-handler-start.php',
	$root . '/includes/bot/handlers/class-handler-support.php',
	$root . '/includes/bot/handlers/class-handler-sync.php',
	$root . '/includes/bot/handlers/class-handler-account.php',
	$root . '/includes/bot/handlers/class-handler-apps.php',
	$root . '/includes/bot/handlers/class-handler-wallet.php',
	$root . '/includes/bot/handlers/class-handler-callback.php',
	$root . '/includes/bot/class-state.php',
	$root . '/includes/helpers/class-bot-admin-user-caption.php',
	$root . '/includes/bot/handlers/class-handler-admin-hub.php',
	$root . '/includes/bot/handlers/class-handler-admin.php',
	$root . '/includes/bot/handlers/class-handler-admin-settings.php',
	$root . '/includes/bot/handlers/class-handler-support.php',
	$root . '/includes/bot/handlers/class-handler-apps.php',
	$root . '/includes/bot/handlers/class-handler-referral.php',
	$root . '/includes/bot/handlers/class-handler-sync.php',
	$root . '/includes/bot/handlers/class-handler-account.php',
	$root . '/includes/bot/handlers/class-handler-wallet.php',
	$root . '/includes/bot/handlers/class-handler-callback.php',
);

$map = array(
	"'⛔ سرویس یافت نشد.'" => "SimpleVPBot_Texts::get_for_user( 'msg.svc.not_found', \$user )",
	"'🔑 رمز جدید برای سرویس L2TP ساخته شد.'" => "SimpleVPBot_Texts::get_for_user( 'msg.svc.l2tp_password_ok', \$user )",
	"'ℹ️ این گزینه برای سرویس L2TP در دسترس نیست.'" => "SimpleVPBot_Texts::get_for_user( 'msg.svc.l2tp_option_na', \$user )",
	"'⚠️ لینک اتصال یافت نشد.'" => "SimpleVPBot_Texts::get_for_user( 'msg.svc.link_not_found', \$user )",
	"'⛔ ورود به پنل ناموفق است.'" => "SimpleVPBot_Texts::get_for_user( 'msg.svc.panel_login_fail', \$user )",
	"'⛔ دریافت UUID جدید ناموفق بود.'" => "SimpleVPBot_Texts::get_for_user( 'msg.svc.uuid_fail', \$user )",
	"'⛔ اینباند پنل یافت نشد.'" => "SimpleVPBot_Texts::get_for_user( 'msg.svc.inbound_not_found', \$user )",
	"'⛔ شناسه کلاینت در پنل یافت نشد (ایمیل یا UUID نامعتبر).'" => "SimpleVPBot_Texts::get_for_user( 'msg.svc.client_id_invalid', \$user )",
	"'⛔ فهرست کلاینت خالی است.'" => "SimpleVPBot_Texts::get_for_user( 'msg.svc.client_list_empty', \$user )",
	"'⛔ کلاینت این سرویس روی پنل پیدا نشد.'" => "SimpleVPBot_Texts::get_for_user( 'msg.svc.client_not_found', \$user )",
	"'⛔ بروزرسانی روی پنل انجام نشد.'" => "SimpleVPBot_Texts::get_for_user( 'msg.svc.panel_update_fail', \$user )",
	"'🔑 کلید (UUID) جدید ساخته شد و روی سرویس ثبت گردید.'" => "SimpleVPBot_Texts::get_for_user( 'msg.svc.uuid_regenerated', \$user )",
	"'🔄 اطلاعات سرور به‌روز شد.'" => "SimpleVPBot_Texts::get_for_user( 'msg.svc.servers_refreshed', \$user )",
	"'🔁 تمدید خودکار: ✅ روشن'" => "SimpleVPBot_Texts::get_for_user( 'msg.svc.auto_renew_on', \$user )",
	"'🔁 تمدید خودکار: ❌ خاموش'" => "SimpleVPBot_Texts::get_for_user( 'msg.svc.auto_renew_off', \$user )",
	"'📝 یادداشت نمایش (نام روی پنل X-UI) را ارسال کنید:'" => "SimpleVPBot_Texts::get_for_user( 'msg.svc.prompt_panel_note', \$user )",
	"'✏️ نام نمایشی این سرویس (در ربات و لیست سرویس‌ها) را ارسال کنید:'" => "SimpleVPBot_Texts::get_for_user( 'msg.svc.prompt_display_name', \$user )",
	"'⛔ پلن سرویس برای صدور فاکتور تنظیم نشده. در تنظیمات عمومی، «پلن پیش‌فرض سرویس‌های بدون پلن» را روی یک پلن Xray فعال بگذارید.'" => "SimpleVPBot_Texts::get_for_user( 'msg.svc.default_plan_missing', \$user )",
	"'⛔ خطای داخلی دکمه‌ها.'" => "SimpleVPBot_Texts::get_for_user( 'msg.svc.internal_button_error', \$user )",
	"'⛔ افزایش حجم از این مسیر فقط برای Xray است.'" => "SimpleVPBot_Texts::get_for_user( 'msg.svc.volume_xray_only', \$user )",
	"'➕ چند گیگابایت به سقف حجم اضافه شود؟ فقط عدد (گیگ) بفرستید؛ مثلاً 10'" => "SimpleVPBot_Texts::get_for_user( 'msg.svc.prompt_add_volume_gb', \$user )",
	"'⛔ این گزینه برای این نوع سرویس نیست.'" => "SimpleVPBot_Texts::get_for_user( 'msg.svc.option_wrong_type', \$user )",
	"'⛔ امکان تولید کد انتقال نیست.'" => "SimpleVPBot_Texts::get_for_user( 'msg.svc.transfer_code_fail', \$user )",
	"'⛔ سرویس نامعتبر است.'" => "SimpleVPBot_Texts::get_for_user( 'msg.svc.invalid_service', \$user )",
	"'⛔ فقط یک عدد ۱ تا ۹۹ بفرستید.'" => "SimpleVPBot_Texts::get_for_user( 'msg.svc.alert_days_1_99', \$user )",
	"'⛔ عدد باید بین ۱ تا ۹۹ باشد.'" => "SimpleVPBot_Texts::get_for_user( 'msg.svc.alert_days_range', \$user )",
	"'⛔ حداقل یک روز معتبر بفرستید، مثل ۳,۱,۰'" => "SimpleVPBot_Texts::get_for_user( 'msg.svc.alert_days_min', \$user )",
	"'⛔ فقط یک عدد ۵۰ تا ۱۰۰ بفرستید.'" => "SimpleVPBot_Texts::get_for_user( 'msg.svc.alert_pct_50_100', \$user )",
	"'⛔ عدد باید بین ۵۰ تا ۱۰۰ باشد.'" => "SimpleVPBot_Texts::get_for_user( 'msg.svc.alert_pct_range', \$user )",
	"'⛔ جلسه نامعتبر است. دوباره از منوی سرویس شروع کنید.'" => "SimpleVPBot_Texts::get_for_user( 'msg.svc.invalid_session', \$user )",
	"'⛔ فقط یک عدد صحیح بفرستید (مثلا 10).'" => "SimpleVPBot_Texts::get_for_user( 'msg.svc.integer_only', \$user )",
	"'⛔ حداقل ۱ گیگ است.'" => "SimpleVPBot_Texts::get_for_user( 'msg.svc.min_1_gb', \$user )",
	"'⛔ حداکثر ۵۱۲ گیگ در هر درخواست.'" => "SimpleVPBot_Texts::get_for_user( 'msg.svc.max_512_gb', \$user )",
	"'⛔ مبلغ نامعتبر است. با ادمین تماس بگیرید.'" => "SimpleVPBot_Texts::get_for_user( 'msg.svc.invalid_amount', \$user )",
	"'⛔ فقط یک عدد بفرستید مثل ۲.'" => "SimpleVPBot_Texts::get_for_user( 'msg.svc.slots_integer', \$user )",
	"'⛔ عدد باید بین ۱ تا ۵۰ باشد.'" => "SimpleVPBot_Texts::get_for_user( 'msg.svc.slots_range', \$user )",
	"'⛔ قیمت هر کاربر اضافه در تنظیمات صفر است. با ادمین تماس بگیرید.'" => "SimpleVPBot_Texts::get_for_user( 'msg.svc.extra_user_price_zero', \$user )",
	"'⛔ متن خالی است.'" => "SimpleVPBot_Texts::get_for_user( 'msg.svc.empty_text', \$user )",
	"'✅ نام نمایشی به‌روز شد.'" => "SimpleVPBot_Texts::get_for_user( 'msg.svc.display_name_updated', \$user )",
	"'✅ یادداشت به‌روز شد.'" => "SimpleVPBot_Texts::get_for_user( 'msg.svc.note_updated', \$user )",
	"'✅ نام نمایشی در ربات به‌روز شد.'" => "SimpleVPBot_Texts::get_for_user( 'msg.svc.display_name_bot_updated', \$user )",
	"'⛔ ورود به پنل ناموفق است. بعداً دوباره تلاش کنید.'" => "SimpleVPBot_Texts::get_for_user( 'msg.svc.panel_login_retry', \$user )",
	"'⛔ فهرست کلاینت روی پنل خالی است.'" => "SimpleVPBot_Texts::get_for_user( 'msg.svc.client_list_empty_panel', \$user )",
	"'⛔ کلاینت روی پنل پیدا نشد.'" => "SimpleVPBot_Texts::get_for_user( 'msg.svc.client_not_found_panel', \$user )",
	"'⛔ شناسه کلاینت روی پنل پیدا نشد.'" => "SimpleVPBot_Texts::get_for_user( 'msg.svc.client_id_not_found_panel', \$user )",
	"'✅ یادداشت روی پنل و نام نمایشی در ربات به‌روز شد.'" => "SimpleVPBot_Texts::get_for_user( 'msg.svc.note_and_name_updated', \$user )",
	"'⛔ دسترسی نامعتبر است.'" => "SimpleVPBot_Texts::get_for_user( 'msg.svc.invalid_access', \$user )",
	"'⛔ این کانفیگ دیگر در دسترس نیست. منوی سرویس را دوباره باز کنید.'" => "SimpleVPBot_Texts::get_for_user( 'msg.svc.config_unavailable', \$user )",
	"'⛔ این سرویس دیگر روی پنل نیست و از لیست شما حذف شد.'" => "SimpleVPBot_Texts::get_for_user( 'msg.svc.deleted_from_panel', \$user )",
	"'📡 مصرف و وضعیت: زنده از پنل (لحظهٔ باز کردن این صفحه).'" => "SimpleVPBot_Texts::get_for_user( 'msg.svc.usage_live_footer', \$user )",
	"'⚠️ اتصال زنده به پنل برقرار نشد؛ اعداد مصرف از آخرین ذخیرهٔ ربات (کش DB) است.'" => "SimpleVPBot_Texts::get_for_user( 'msg.svc.usage_cache_footer', \$user )",
	"'ℹ️ مصرف از پنل خوانده شد؛ انقضای DB ممکن است با پنل چند دقیقه اختلاف داشته باشد.'" => "SimpleVPBot_Texts::get_for_user( 'msg.svc.usage_stale_footer', \$user )",
	"'⚠️ سرویس در این لحظه روی پنل در لیست کلاینت‌ها دیده نشد؛ اشتراک شما در ربات حذف نشده است. اگر این پیام تکرار شد با پشتیبانی تماس بگیرید.'" => "SimpleVPBot_Texts::get_for_user( 'msg.svc.usage_sync_uncertain', \$user )",
	"'🧰 سرویس‌های شما'" => "SimpleVPBot_Texts::get_for_user( 'msg.svc.list_title', \$user )",
	"'⛔ ثبت سفارش ناموفق بود.'" => "SimpleVPBot_Texts::get_for_user( 'msg.buy.order_failed', \$user )",
	"'⛔ کارتی ثبت نشده. ادمین را مطلع کنید.'" => "SimpleVPBot_Texts::get_for_user( 'msg.buy.no_cards', \$user )",
	"'⛔ سفارش نامعتبر است.'" => "SimpleVPBot_Texts::get_for_user( 'msg.buy.invalid_order', \$user )",
	"'🏷 کد تخفیف را بفرستید. برای انصراف «لغو» بفرستید یا از منوی اصلی یک گزینه را انتخاب کنید.'" => "SimpleVPBot_Texts::get_for_user( 'msg.buy.prompt_discount', \$user )",
	"'⛔ این دسته در دسترس نیست یا غیرفعال شده است.'" => "SimpleVPBot_Texts::get_for_user( 'msg.buy.category_unavailable', \$user )",
	"'⛔ این پلن در دسترس نیست یا غیرفعال شده است.'" => "SimpleVPBot_Texts::get_for_user( 'msg.buy.plan_unavailable', \$user )",
	"'⛔ حجم انتخاب‌شده معتبر نیست.'" => "SimpleVPBot_Texts::get_for_user( 'msg.buy.volume_invalid', \$user )",
	"'⛔ این بخش خرید منقضی یا نامعتبر است.'" => "SimpleVPBot_Texts::get_for_user( 'msg.buy.section_expired', \$user )",
	"'⛔ سفارش خرید نامعتبر است.'" => "SimpleVPBot_Texts::get_for_user( 'msg.buy.purchase_invalid', \$user )",
	"'⛔ مبلغ سفارش نامعتبر است.'" => "SimpleVPBot_Texts::get_for_user( 'msg.buy.amount_invalid', \$user )",
	"'⛔ پرداخت کیف پول فقط در بله در دسترس است.'" => "SimpleVPBot_Texts::get_for_user( 'msg.buy.wallet_bale_only', \$user )",
	"'⛔ پرداخت کیف پول در حال حاضر غیرفعال است.'" => "SimpleVPBot_Texts::get_for_user( 'msg.buy.wallet_disabled', \$user )",
	"'⛔ پلن این سفارش در دسترس نیست.'" => "SimpleVPBot_Texts::get_for_user( 'msg.buy.plan_missing', \$user )",
	"'⛔ ارسال فاکتور ممکن نشد. کمی بعد دوباره تلاش کنید.'" => "SimpleVPBot_Texts::get_for_user( 'msg.buy.invoice_failed', \$user )",
	"'📸 لطفاً تصویر رسید کارت‌به‌کارت را همینجا ارسال کنید.'" => "SimpleVPBot_Texts::get_for_user( 'msg.buy.send_receipt_photo', \$user )",
	"'❌ لغو شد.'" => "SimpleVPBot_Texts::get_for_user( 'msg.buy.cancelled', \$user )",
	"'⛔ جلسه نامعتبر بود.'" => "SimpleVPBot_Texts::get_for_user( 'msg.buy.session_invalid', \$user )",
	"'❌ ورود کد تخفیف لغو شد.'" => "SimpleVPBot_Texts::get_for_user( 'msg.buy.discount_cancelled', \$user )",
	"'ℹ️ از دکمه‌های منو استفاده کنید.'" => "SimpleVPBot_Texts::get_for_user( 'msg.buy.use_menu', \$user )",
	"'⛔ جلسه خرید نامعتبر است. دوباره از منو شروع کنید.'" => "SimpleVPBot_Texts::get_for_user( 'msg.buy.session_restart', \$user )",
	"'⛔ فقط یک عدد صحیح بفرستید (مثلا 20).'" => "SimpleVPBot_Texts::get_for_user( 'msg.buy.integer_gb', \$user )",
	"'⛔ خطای داخلی: شناسه بیش از حد بزرگ است. با ادمین تماس بگیرید.'" => "SimpleVPBot_Texts::get_for_user( 'msg.buy.id_overflow', \$user )",
	"'⛔ ابتدا خرید را از منو شروع کنید.'" => "SimpleVPBot_Texts::get_for_user( 'msg.buy.start_from_menu', \$user )",
	"'✅ رسید دریافت شد. پس از تایید ادمین به شما اطلاع داده می‌شود.'" => "SimpleVPBot_Texts::get_for_user( 'msg.buy.receipt_received', \$user )",
	"'⛔ پلنی در این دسته نیست.'" => "SimpleVPBot_Texts::get_for_user( 'msg.buy.no_plans_in_category', \$user )",
	"'⛔ دستهٔ معتبری برای نمایش نیست.'" => "SimpleVPBot_Texts::get_for_user( 'msg.buy.no_categories', \$user )",
	"'⛔ دسترسی شما مسدود است.'" => "SimpleVPBot_Texts::get_for_user( 'msg.blocked', \$user )",
	"'ℹ️ درخواست قبلی لغو شد.'" => "SimpleVPBot_Texts::get_for_user( 'msg.state.cancelled', \$user )",
);

foreach ( $files as $path ) {
	if ( ! is_file( $path ) ) {
		fwrite( STDERR, "Skip missing: {$path}\n" );
		continue;
	}
	$src = file_get_contents( $path );
	$orig = $src;
	foreach ( $map as $from => $to ) {
		$src = str_replace( $from, $to, $src );
	}
	if ( $src !== $orig ) {
		file_put_contents( $path, $src );
		echo "Updated: {$path}\n";
	}
}
echo "Done.\n";
