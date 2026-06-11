<?php
/**
 * Extended bot text seeds (service, buy, admin hub, common).
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Additional default rows for svp_texts.
 */
class SimpleVPBot_Bot_Text_Defaults_Extended {

	/**
	 * @param array<int, array<string, string>> $rows Rows accumulator.
	 * @param string                            $key Key.
	 * @param string                            $category Category.
	 * @param string                            $fa Persian.
	 * @param string                            $en English.
	 */
	private static function pair( array &$rows, $key, $category, $fa, $en ) {
		$rows[] = array(
			'key_name' => $key,
			'category' => $category,
			'locale'   => 'fa',
			'value'    => $fa,
		);
		$rows[] = array(
			'key_name' => $key,
			'category' => $category,
			'locale'   => 'en',
			'value'    => $en,
		);
	}

	/**
	 * @param array<int, array<string, string>> $r Rows accumulator.
	 */
	public static function append_rows( array &$r ) {
		self::append_btn_svc_pay( $r );
		self::append_msg_svc( $r );
		self::append_msg_buy( $r );
		self::append_msg_admin( $r );
		self::append_msg_admin_hub_extra( $r );
		self::append_msg_admin_wiz( $r );
		self::append_msg_common( $r );
		self::append_msg_cron( $r );
		self::append_msg_cron_admin( $r );
		self::append_msg_alerts( $r );
		self::append_msg_marketing( $r );
		self::append_msg_membership( $r );
		self::append_msg_admin_panel( $r );
	}

	/**
	 * Admin hub handler messages (errors, confirmations).
	 *
	 * @param array<int, array<string, string>> $r Rows.
	 */
	private static function append_msg_admin_hub_extra( array &$r ) {
		self::pair( $r, 'msg.admin.user_not_found', 'messages', '⛔ کاربر یافت نشد.', '⛔ User not found.' );
		self::pair( $r, 'msg.admin.service_id_invalid', 'messages', '⛔ شناسهٔ سرویس نامعتبر است.', '⛔ Invalid service id.' );
		self::pair( $r, 'msg.admin.service_soft_delete_fail', 'messages', '⛔ سرویس یافت نشد یا قبلاً از لیست فعال حذف شده است.', '⛔ Service not found or already removed from active list.' );
		self::pair( $r, 'msg.admin.soft_delete_fail', 'messages', '⛔ حذف نرم انجام نشد.', '⛔ Soft delete failed.' );
		self::pair( $r, 'msg.admin.server_active', 'messages', '✅ سرور #{id} active={state}', '✅ Server #{id} active={state}' );
		self::pair( $r, 'msg.admin.server_deleted', 'messages', '✅ سرور #{id} حذف شد.', '✅ Server #{id} deleted.' );
		self::pair( $r, 'msg.admin.user_approved', 'messages', '✅ کاربر #{id} تایید شد.', '✅ User #{id} approved.' );
		self::pair( $r, 'msg.admin.user_rejected', 'messages', '✅ کاربر #{id} رد شد.', '✅ User #{id} rejected.' );
		self::pair( $r, 'msg.admin.prompt_new_value', 'messages', "✏️ مقدار جدید را برای «{key}» ارسال کنید.\n/cancel", "✏️ Send new value for «{key}».\n/cancel" );
		self::pair( $r, 'msg.admin.text_preview', 'messages', "📝 {key}\n➖➖➖\n{value}", "📝 {key}\n➖➖➖\n{value}" );
		self::pair( $r, 'msg.admin.card_deleted', 'messages', '✅ کارت حذف شد.', '✅ Card deleted.' );
		self::pair( $r, 'msg.admin.user_status_updated', 'messages', '✅ وضعیت کاربر به‌روز شد.', '✅ User status updated.' );
		self::pair( $r, 'msg.admin.setting_changed', 'messages', '✅ تنظیم «{key}» تغییر کرد.', '✅ Setting «{key}» changed.' );
		self::pair( $r, 'msg.admin.setting_not_switchable', 'messages', '⛔ این کلید از ربات قابل سوییچ نیست.', '⛔ This key cannot be toggled from the bot.' );
		self::pair( $r, 'msg.admin.plan_active', 'messages', '✅ پلن #{id} active={state}', '✅ Plan #{id} active={state}' );
		self::pair( $r, 'msg.admin.category_active', 'messages', '✅ دسته #{id} active={state}', '✅ Category #{id} active={state}' );
		self::pair( $r, 'msg.admin.card_active', 'messages', '✅ کارت #{id} active={state}', '✅ Card #{id} active={state}' );
		self::pair( $r, 'msg.admin.unavailable', 'messages', '⛔ در دسترس نیست.', '⛔ Unavailable.' );
		self::pair( $r, 'msg.admin.unknown', 'messages', 'ℹ️ ناشناخته.', 'ℹ️ Unknown.' );
		self::pair( $r, 'msg.admin.no_text_saved', 'messages', 'متنی ثبت نشده.', 'No text saved.' );
		self::pair( $r, 'msg.admin.method_invalid', 'messages', '⛔ روش نامعتبر است.', '⛔ Invalid method.' );
		self::pair( $r, 'msg.admin.service_invalid', 'messages', '⛔ سرویس نامعتبر است.', '⛔ Invalid service.' );
		self::pair( $r, 'msg.admin.error_generic', 'messages', '⛔ {reason}', '⛔ {reason}' );
		self::pair( $r, 'msg.admin.target_user_not_found', 'messages', '⛔ کاربر مقصد یافت نشد.', '⛔ Target user not found.' );
		self::pair( $r, 'msg.admin.ops_unavailable', 'messages', '⛔ ماژول عملیات ادمین در دسترس نیست.', '⛔ Admin operations module unavailable.' );
		self::pair( $r, 'msg.admin.no_active_plans', 'messages', '⛔ پلن فعالی برای انتخاب نیست.', '⛔ No active plans to choose.' );
		self::pair( $r, 'msg.admin.plan_ids_too_large', 'messages', '⛔ پلن قابل نمایش در دکمه نیست (شناسه‌ها بزرگند). با ادمین وردپرس اقدام کنید.', '⛔ Plan cannot fit button (ids too large). Use WordPress admin.' );
		self::pair( $r, 'msg.admin.invalid_data', 'messages', '⛔ داده نامعتبر است.', '⛔ Invalid data.' );
		self::pair( $r, 'msg.admin.plan_unavailable', 'messages', '⛔ این پلن در دسترس نیست.', '⛔ This plan is unavailable.' );
		self::pair( $r, 'msg.admin.plan_pergb_misconfigured', 'messages', '⛔ پلن per-GB اشتباه پیکربندی شده است.', '⛔ Per-GB plan misconfigured.' );
		self::pair( $r, 'msg.admin.pay_method_invalid', 'messages', '⛔ روش پرداخت نامعتبر است.', '⛔ Invalid payment method.' );
		self::pair( $r, 'msg.admin.plan_invalid', 'messages', '⛔ پلن نامعتبر است.', '⛔ Invalid plan.' );
		self::pair( $r, 'msg.admin.volume_invalid_for_plan', 'messages', '⛔ حجم برای این پلن معتبر نیست.', '⛔ Volume invalid for this plan.' );
		self::pair( $r, 'msg.admin.fixed_plan_no_volume', 'messages', '⛔ برای پلن ثابت حجم نفرستید؛ دوباره از ابتدا شروع کنید.', '⛔ Do not send volume for fixed plans; start over.' );
		self::pair( $r, 'msg.admin.internal_button_error', 'messages', '⛔ خطای داخلی دکمه‌ها.', '⛔ Internal button error.' );
		self::pair( $r, 'msg.admin.caption.brand', 'messages', '🏷 برند: {brand}', '🏷 Brand: {brand}' );
		self::pair( $r, 'msg.admin.caption.user_line', 'messages', '👤 کاربر: {name}', '👤 User: {name}' );
		self::pair( $r, 'msg.admin.caption.username_line', 'messages', 'یوزرنیم: {username}', 'Username: {username}' );
		self::pair( $r, 'msg.admin.caption.telegram_line', 'messages', 'تلگرام: {id}', 'Telegram: {id}' );
		self::pair( $r, 'msg.admin.caption.bale_line', 'messages', 'بله: {id}', 'Bale: {id}' );
		self::pair( $r, 'msg.admin.caption.bot_line', 'messages', 'ربات: #{id}', 'Bot: #{id}' );
		self::pair( $r, 'msg.admin.caption.discount_line', 'messages', '🏷 کد تخفیف: {code}', '🏷 Discount: {code}' );
		self::pair( $r, 'msg.admin.caption.discount_amounts', 'messages', 'قبل از تخفیف: {subtotal} · تخفیف: {discount}', 'Before discount: {subtotal} · Discount: {discount}' );
		self::pair( $r, 'msg.admin.caption.amount_line', 'messages', '💰 مبلغ: {amount} تومان', '💰 Amount: {amount} Toman' );
		self::pair( $r, 'msg.admin.caption.amount_line_free', 'messages', '💰 مبلغ: رایگان', '💰 Amount: Free' );
		self::pair( $r, 'msg.admin.caption.service_line', 'messages', 'سرویس انتخابی: {service}', 'Selected service: {service}' );
		self::pair( $r, 'msg.admin.caption.receipt_id_line', 'messages', '🆔 رسید: {id}', '🆔 Receipt: {id}' );
		self::pair( $r, 'msg.admin.signup_review', 'messages', '🔔 درخواست ثبت‌نام (بازبینی)', '🔔 Signup request (review)' );
		self::pair( $r, 'msg.admin.signup_new', 'messages', '🔔 درخواست ثبت‌نام جدید', '🔔 New signup request' );
		self::pair( $r, 'msg.admin.invited_by', 'messages', '🔗 با لینک کسب درآمد از طرف {label}', '🔗 Joined via earn link from {label}' );
		self::pair( $r, 'msg.admin.invited_by_en', 'messages', '🔗 Joined via earn link from {label}', '🔗 Joined via earn link from {label}' );
		self::pair( $r, 'msg.admin.plan_deleted_ok', 'messages', '✅ پلن حذف شد.', '✅ Plan deleted.' );
		self::pair( $r, 'msg.admin.plan_delete_fail', 'messages', '⛔ حذف امکان‌پذیر نیست.', '⛔ Cannot delete.' );
		self::pair( $r, 'msg.admin.category_deleted_ok', 'messages', '✅ دسته حذف شد.', '✅ Category deleted.' );
		self::pair( $r, 'msg.admin.category_delete_rejected', 'messages', '⛔ {code}', '⛔ {code}' );
		self::pair( $r, 'msg.admin.prompt_renew_line', 'messages', "♻️ تمدید سرویس #{id}\nیک حرف بفرستید: w یا f یا i\n/cancel", "♻️ Renew service #{id}\nSend one letter: w, f, or i\n/cancel" );
		self::pair( $r, 'msg.admin.prompt_add_volume_line', 'messages', "➕ حجم سرویس #{id}\nدو بخش: <code>گیگ mode</code> (مثل: 10 w)\n/cancel", "➕ Service volume #{id}\nTwo parts: <code>GB mode</code> (e.g. 10 w)\n/cancel" );
		self::pair( $r, 'msg.admin.prompt_bulk_xray', 'messages', "➕ عملیات گروهی Xray\nبرای اجرا دقیقا بفرستید:\n<code>ok روز [گیگ]</code>\nمثال فقط روز: <code>ok 7</code>\nمثال روز+حجم: <code>ok 3 5</code>\n/cancel", "➕ Xray bulk operation\nSend exactly:\n<code>ok days [GB]</code>\nDays only: <code>ok 7</code>\nDays+GB: <code>ok 3 5</code>\n/cancel" );
		self::pair( $r, 'msg.admin.prompt_wallet_credit', 'messages', "💰 شارژ کیف پول کاربر #{id}\nمبلغ را فقط به تومان (عدد) بفرستید.\n/cancel", "💰 Credit wallet for user #{id}\nSend amount in Toman (number) only.\n/cancel" );
		self::pair( $r, 'msg.admin.prompt_wallet_debit', 'messages', "📉 کاهش از کیف پول کاربر #{id}\nمبلغ را فقط به تومان (عدد) بفرستید.\n/cancel", "📉 Debit wallet for user #{id}\nSend amount in Toman (number) only.\n/cancel" );
		self::pair( $r, 'msg.admin.wizard_cancelled', 'messages', 'ℹ️ حالت/ویزارد لغو شد.', 'ℹ️ Mode/wizard cancelled.' );
		self::pair( $r, 'msg.admin.signup_processed', 'messages', '✅ ثبت‌نام پردازش شد.', '✅ Signup processed.' );
		self::pair( $r, 'msg.admin.signup_rejected_recorded', 'messages', '✅ رد ثبت‌نام ثبت شد.', '✅ Signup rejection recorded.' );
		self::pair( $r, 'msg.admin.texts_reset_denied', 'messages', '⛔ فقط ادمین پلتفرم می‌تواند متن‌ها را بازنشانی کند.', '⛔ Only platform admin can reset texts.' );
		self::pair( $r, 'msg.admin.texts_reset_ok', 'messages', '✅ همهٔ متن‌ها به پیش‌فرض نسخهٔ فعلی برگردانده شد.', '✅ All texts reset to current defaults.' );
		self::pair( $r, 'msg.admin.portal_link_unset', 'messages', '⛔ لینک پورتال برای این چت تنظیم نشده.', '⛔ Portal link not set for this chat.' );
		self::pair( $r, 'msg.admin.admin_panel_unset', 'messages', '⛔ لینک پنل ادمین وب در دسترس نیست.', '⛔ Web admin panel link unavailable.' );
		self::pair( $r, 'msg.admin.link_empty', 'messages', '⛔ لینک خالی است.', '⛔ Link is empty.' );
		self::pair( $r, 'msg.admin.panel_inactive', 'messages', '⛔ پنل فعالی نیست.', '⛔ No active panel.' );
		self::pair( $r, 'msg.reseller.global_settings_denied', 'messages', '⛔ تنظیمات سراسری سایت فقط از ربات اصلی یا داشبورد مدیریت قابل تغییر است. برای ربات نماینده از داشبورد «ربات نماینده» استفاده کنید.', '⛔ Site-wide settings can only be changed from the main bot or admin dashboard. Use the reseller bot section in the dashboard for your reseller bot.' );
		self::pair( $r, 'msg.reseller.site_bulk_denied', 'messages', '⛔ عملیات گروهی سراسری فقط از ربات اصلی یا داشبورد نماینده (کاربران زیرمجموعه) قابل انجام است.', '⛔ Site-wide bulk operations are only available from the main bot or the reseller dashboard (downline users).' );
		self::pair( $r, 'msg.admin.ipn_saved', 'messages', '✅ مسیر IPN جدید ذخیره شد.', '✅ New IPN path saved.' );
		self::pair( $r, 'msg.admin.inbound_list_empty', 'messages', '⛔ {message}', '⛔ {message}' );
		self::pair( $r, 'msg.admin.inbound_none', 'messages', '⛔ Inboundی نیست.', '⛔ No inbound.' );
		self::pair( $r, 'msg.admin.inbound_clients_empty', 'messages', '⛔ {message}', '⛔ {message}' );
		self::pair( $r, 'msg.admin.inbound_email_missing', 'messages', '⛔ ایمیلی در این Inbound یافت نشد.', '⛔ No email in this inbound.' );
		self::pair( $r, 'msg.admin.inbound_session_expired', 'messages', '⛔ دوباره Inbound و لیست را باز کنید (منقضی ۱۰دقیقه).', '⛔ Reopen inbound list (10 min expiry).' );
		self::pair( $r, 'msg.admin.inbound_row_invalid', 'messages', '⛔ ردیف نامعتبر.', '⛔ Invalid row.' );
		self::pair( $r, 'msg.admin.inbound_link_user_prompt', 'messages', "🔗 svp_users.id مربوط به \n`{email}`\n\nرا عدد ارسال کنید. /cancel", "🔗 Send svp_users.id number for \n`{email}`\n\n/cancel" );
		self::pair( $r, 'msg.admin.prompt_tg_chat_id', 'messages', '📢 chat id تلگرام (کانال/گروه) را ارسال کنید (مثلاً -100...). /cancel', '📢 Send Telegram chat id (channel/group, e.g. -100...). /cancel' );
		self::pair( $r, 'msg.admin.prompt_bale_chat_id', 'messages', '💬 chat id بله را ارسال کنید. /cancel', '💬 Send Bale chat id. /cancel' );
		self::pair( $r, 'msg.admin.backup.zip_only', 'messages', '⛔ فقط فایل .zip بکاپ SimpleVPBot.', '⛔ Only SimpleVPBot .zip backup files.' );
		self::pair( $r, 'msg.admin.backup.download_fail', 'messages', '⛔ دانلود فایل ناموفق: {error}', '⛔ File download failed: {error}' );
		self::pair( $r, 'msg.admin.backup.restore_fail', 'messages', '⛔ ریستور ناموفق: {error}', '⛔ Restore failed: {error}' );
		self::pair( $r, 'msg.admin.backup.restore_ok', 'messages', "✅ بازگردانی ادغامی انجام شد.\nکاربران: {matched} تطبیق · {inserted} جدید · {skipped} رد", "✅ Merge restore done.\nUsers: {matched} matched · {inserted} new · {skipped} skipped" );
		self::pair( $r, 'msg.admin.backup.interval_saved', 'messages', '✅ فاصله بکاپ: {minutes} دقیقه', '✅ Backup interval: {minutes} min' );
		self::pair( $r, 'msg.admin.backup.tg_chat_saved', 'messages', '✅ chat id تلگرام ذخیره شد: {id}', '✅ Telegram chat id saved: {id}' );
		self::pair( $r, 'msg.admin.backup.bl_chat_saved', 'messages', '✅ chat id بله ذخیره شد: {id}', '✅ Bale chat id saved: {id}' );
		self::pair( $r, 'msg.admin.backup.send_zip', 'messages', '⏳ فقط فایل .zip بکاپ را بفرستید (/cancel — لغو).', '⏳ Send .zip backup file only (/cancel to abort).' );
		self::pair( $r, 'msg.admin.backup.send_number', 'messages', '⏳ فقط یک عدد معتبر ارسال کنید یا /cancel', '⏳ Send a valid number or /cancel' );
		self::pair( $r, 'msg.admin.backup.restore_hint', 'messages', 'ℹ️ برای ریستور: از «🔧 تنظیمات پیشرفته» → «💾 پشتیبان‌گیری» → 📥 ریستور (۲ مرحله) اقدام کنید و سپس فایل .zip بفرستید.', 'ℹ️ For restore: Advanced settings → Backup → Restore (2-step), then send .zip.' );
		self::pair( $r, 'msg.admin.stats_unavailable', 'messages', '📊 آمار در دسترس نیست.', '📊 Stats unavailable.' );
		self::pair( $r, 'msg.admin.users_submenu', 'messages', '👥 مدیریت کاربران — یکی را انتخاب کنید:', '👥 User management — pick one:' );
		self::pair( $r, 'msg.admin.finance_submenu', 'messages', '💰 مالی — یکی را انتخاب کنید:', '💰 Finance — pick one:' );
		self::pair( $r, 'msg.admin.prompt_service_id', 'messages', '🆔 شناسه سرویس (svp_services.id) را ارسال کنید:', '🆔 Send service id (svp_services.id):' );
		self::pair( $r, 'msg.admin.prompt_broadcast', 'messages', '📣 متن پیام همگانی را ارسال کنید:', '📣 Send broadcast message text:' );
		self::pair( $r, 'msg.admin.cancelled', 'messages', 'ℹ️ لغو شد.', 'ℹ️ Cancelled.' );
		self::pair( $r, 'msg.admin.transfer_prompt', 'messages', "🎁 انتقال سرویس #{id} (مالک فعلی: {owner})\nشناسه مقصد را ارسال کنید:\n- svp_users.id\n- یا @username\n- یا عدد chat id (تلگرام/بله)", "🎁 Transfer service #{id} (owner: {owner})\nSend target:\n- svp_users.id\n- or @username\n- or chat id (Telegram/Bale)" );
		self::pair( $r, 'msg.admin.transfer_service_simple', 'messages', "🎁 انتقال سرویس #{id}\nشناسه مقصد را ارسال کنید:\n- svp_users.id\n- یا @username\n- یا عدد chat id (تلگرام/بله)", "🎁 Transfer service #{id}\nSend target:\n- svp_users.id\n- or @username\n- or chat id (Telegram/Bale)" );
		self::pair( $r, 'msg.admin.dm_body_prefix', 'messages', "📩 پیام از پشتیبانی\n➖➖➖➖➖➖➖➖\n{body}", "📩 Message from support\n➖➖➖➖➖➖➖➖\n{body}" );
		self::pair( $r, 'msg.admin.target_invalid', 'messages', '⛔ کاربر مقصد نامعتبر بود.', '⛔ Invalid target user.' );
		self::pair( $r, 'msg.admin.user_no_chat', 'messages', '⛔ کاربر چت تلگرام/بله ندارد.', '⛔ User has no Telegram/Bale chat.' );
		self::pair( $r, 'msg.admin.message_sent', 'messages', '✅ پیام ارسال شد.', '✅ Message sent.' );
		self::pair( $r, 'msg.admin.destination_invalid', 'messages', '⛔ مقصد نامعتبر بود.', '⛔ Invalid destination.' );
		self::pair( $r, 'msg.admin.wallet_toman_only', 'messages', '⛔ فقط عدد تومان بفرستید (مثلاً 50000).', '⛔ Send Toman amount only (e.g. 50000).' );
		self::pair( $r, 'msg.admin.wallet_amount_positive', 'messages', '⛔ مبلغ باید بزرگ‌تر از صفر باشد.', '⛔ Amount must be greater than zero.' );
		self::pair( $r, 'msg.admin.wallet_insufficient', 'messages', '⛔ موجودی کافی نیست. موجودی فعلی: {balance} تومان.', '⛔ Insufficient balance. Current: {balance} Toman.' );
		self::pair( $r, 'msg.admin.wallet_debited', 'messages', '✅ {amount} تومان از کیف پول کاربر #{id} کسر شد.', '✅ Debited {amount} Toman from user #{id}.' );
		self::pair( $r, 'msg.admin.wallet_credited', 'messages', '✅ {amount} تومان به کیف پول کاربر #{id} اضافه شد.', '✅ Credited {amount} Toman to user #{id}.' );
		self::pair( $r, 'msg.admin.find_user_none', 'messages', '⛔ کاربری یافت نشد. عبارت دیگری بفرستید یا از منو دوباره جستجو کنید.', '⛔ No user found. Try another query or search again from the menu.' );
		self::pair( $r, 'msg.admin.find_user_pick', 'messages', '🔎 چند کاربر پیدا شد؛ یکی را انتخاب کنید:', '🔎 Multiple users found; pick one:' );
		self::pair( $r, 'msg.admin.broadcast_queued', 'messages', '📣 پیام در صف ارسال قرار گرفت.', '📣 Message queued for sending.' );
		self::pair( $r, 'msg.admin.menu_pick_option', 'messages', 'ℹ️ گزینه را از منوی ادمین انتخاب کنید.', 'ℹ️ Pick an option from the admin menu.' );
		self::pair( $r, 'msg.admin.transfer_target_ambiguous', 'messages', '⛔ کاربر مقصد یافت نشد یا مبهم است. دوباره بفرستید یا /start را بزنید.', '⛔ Target user not found or ambiguous. Retry or /start.' );
		self::pair( $r, 'msg.admin.transfer_ok', 'messages', '✅ سرویس #{sid} به کاربر #{uid} منتقل شد.', '✅ Service #{sid} transferred to user #{uid}.' );
		self::pair( $r, 'msg.admin.ns_volume_range', 'messages', '⛔ حجم باید بین {min} و {max} گیگابایت باشد.', '⛔ Volume must be between {min} and {max} GB.' );
		self::pair( $r, 'msg.admin.ns_volume_integer', 'messages', '⛔ فقط یک عدد صحیح (گیگابایت) بفرستید.', '⛔ Send one integer (GB) only.' );
		self::pair( $r, 'msg.admin.ns_pick_mode', 'messages', "➕ ساخت سرویس برای #{uid}\n📦 حجم: {gb} گیگابایت\n۳) روش اعمال را انتخاب کنید:", "➕ Create service for #{uid}\n📦 Volume: {gb} GB\n3) Pick apply method:" );
		self::pair( $r, 'msg.admin.line.ns_parts', 'messages', '⛔ سه بخش لازم است: plan_id حجم mode (w|f|i)', '⛔ Three parts required: plan_id volume mode (w|f|i)' );
		self::pair( $r, 'msg.admin.line.mode_wfi', 'messages', '⛔ mode باید w یا f یا i باشد.', '⛔ mode must be w, f, or i.' );
		self::pair( $r, 'msg.admin.line.service_created', 'messages', '✅ سرویس #{id}', '✅ Service #{id}' );
		self::pair( $r, 'msg.admin.line.invoice_sent', 'messages', '✅ فاکتور ارسال شد (سفارش #{id}).', '✅ Invoice sent (order #{id}).' );
		self::pair( $r, 'msg.admin.line.renew_one_char', 'messages', '⛔ فقط یک حرف: w یا f یا i', '⛔ One letter only: w, f, or i' );
		self::pair( $r, 'msg.admin.line.renew_ok', 'messages', '✅ تمدید انجام شد.', '✅ Renewed.' );
		self::pair( $r, 'msg.admin.line.two_parts', 'messages', '⛔ دو بخش: گیگ mode(w|f|i)', '⛔ Two parts: GB mode(w|f|i)' );
		self::pair( $r, 'msg.admin.line.mode_invalid', 'messages', '⛔ mode نامعتبر.', '⛔ Invalid mode.' );
		self::pair( $r, 'msg.admin.line.volume_ok', 'messages', '✅ حجم اعمال شد.', '✅ Volume applied.' );
		self::pair( $r, 'msg.admin.line.bulk_confirm', 'messages', '⛔ برای تأیید دقیقا بفرستید: ok days [gb]', '⛔ To confirm send exactly: ok days [gb]' );
		self::pair( $r, 'msg.admin.line.bulk_days_result', 'messages', 'روز: ok={done} err={errors}', 'Days: ok={done} err={errors}' );
		self::pair( $r, 'msg.admin.line.bulk_gb_result', 'messages', 'حجم: ok={done} err={errors}', 'Volume: ok={done} err={errors}' );
		self::pair( $r, 'msg.admin.line.bulk_invalid', 'messages', '⛔ عدد نامعتبر.', '⛔ Invalid number.' );
		self::pair( $r, 'msg.admin.prompt_edit_text', 'messages', '⏳ متن جدید را بفرستید یا /cancel', '⏳ Send new text or /cancel' );
		self::pair( $r, 'msg.admin.prompt_user_id_only', 'messages', '⛔ فقط عدد svp_users.id را بفرستید یا /cancel', '⛔ Send svp_users.id number only or /cancel' );
		self::pair( $r, 'msg.admin.settings_invalid', 'messages', '⛔ مقدار نامعتبر یا ذخیره ناموفق. /cancel', '⛔ Invalid value or save failed. /cancel' );
		self::pair( $r, 'msg.admin.link_ok', 'messages', '✅ {message}', '✅ {message}' );
		self::pair( $r, 'msg.admin.op_ok', 'messages', '✅ {message}', '✅ {message}' );
		self::pair( $r, 'msg.admin.op_fail', 'messages', '⛔ {message}', '⛔ {message}' );
		self::pair( $r, 'msg.admin.op_fail_details', 'messages', "⛔ {message}\n{details}", "⛔ {message}\n{details}" );
		self::pair( $r, 'msg.admin.catalog.category_lines', 'messages', '⛔ دو خط لازم است: slug و label.', '⛔ Two lines required: slug and label.' );
		self::pair( $r, 'msg.admin.catalog.category_added', 'messages', '✅ دسته اضافه شد.', '✅ Category added.' );
		self::pair( $r, 'msg.admin.catalog.error_code', 'messages', '⛔ خطا: {code}', '⛔ Error: {code}' );
		self::pair( $r, 'msg.admin.catalog.plan_lines', 'messages', '⛔ ۷ خط لازم است (نام… تا تعداد کلاینت).', '⛔ 7 lines required (name… to client count).' );
		self::pair( $r, 'msg.admin.catalog.plan_added', 'messages', '✅ پلن اضافه شد.', '✅ Plan added.' );
		self::pair( $r, 'msg.admin.catalog.plan_invalid', 'messages', '⛔ داده‌ نامعتبر یا حذف/به‌روز: {code}', '⛔ Invalid data or update failed: {code}' );
		self::pair( $r, 'msg.admin.catalog.card_lines', 'messages', '⛔ حداقل ۶ بخش با | لازم است.', '⛔ At least 6 pipe-separated fields required.' );
		self::pair( $r, 'msg.admin.catalog.card_added', 'messages', '✅ کارت اضافه شد.', '✅ Card added.' );
		self::pair( $r, 'msg.admin.catalog.l2tp_lines', 'messages', '⛔ ۷ بخش با | لازم است.', '⛔ 7 pipe-separated fields required.' );
		self::pair( $r, 'msg.admin.catalog.l2tp_added', 'messages', '✅ سرور L2TP اضافه شد.', '✅ L2TP server added.' );
		self::pair( $r, 'msg.admin.fallback.error', 'messages', 'خطا', 'Error' );
		self::pair( $r, 'msg.admin.fallback.rejected', 'messages', 'رد', 'Rejected' );
		self::pair( $r, 'msg.admin.fallback.link_ok', 'messages', 'لینک انجام شد.', 'Link done.' );
		self::pair( $r, 'msg.admin.fallback.failed', 'messages', 'ناموفق', 'Failed' );
		self::pair( $r, 'msg.admin.fallback.inbound_list_empty', 'messages', 'لیست inbounds خالی', 'Inbound list empty' );
		self::pair( $r, 'msg.admin.fallback.no_clients', 'messages', 'کلاینتی نیست', 'No clients' );
		self::pair( $r, 'msg.admin.user_actions', 'messages', '💬 اقدام برای کاربر #{id}', '💬 Actions for user #{id}' );
		self::pair( $r, 'msg.admin.user_services', 'messages', '📡 سرویس‌های کاربر #{id}', '📡 Services for user #{id}' );
		self::pair( $r, 'msg.admin.receipt_review_busy', 'messages', '⏳ ارسال رسیدهای معلق در حال انجام است. چند ثانیه صبر کنید.', '⏳ Sending pending receipts. Wait a few seconds.' );
		self::pair( $r, 'msg.admin.receipt_none', 'messages', '🧾 رسید معلقی نیست.', '🧾 No pending receipts.' );
		self::pair( $r, 'msg.admin.receipt_page_empty', 'messages', '🧾 رسید دیگری در این صفحه نیست.', '🧾 No more receipts on this page.' );
		self::pair( $r, 'msg.admin.receipt_sending', 'messages', '🧾 در حال ارسال {n} رسید معلق…', '🧾 Sending {n} pending receipt(s)…' );
		self::pair( $r, 'msg.admin.receipt_incomplete', 'messages', '🧾 رسید #{id} (داده ناقص)', '🧾 Receipt #{id} (incomplete data)' );
		self::pair( $r, 'msg.admin.service_soft_deleted_ok', 'messages', '✅ سرویس #{id} از لیست فعال کاربر حذف شد (غیرفعال‌سازی نرم). کلاینت روی پنل دست‌نخورده مانده است.', '✅ Service #{id} removed from active list (soft delete). Panel client unchanged.' );
		self::pair( $r, 'msg.admin.bulk_days_done', 'messages', "📊 +روز {days} (Xray)\n✅ موفق: {done}\n⛔ خطا: {errors}", "📊 +days {days} (Xray)\n✅ done: {done}\n⛔ errors: {errors}" );
		self::pair( $r, 'msg.admin.bulk_gb_done', 'messages', "📊 +{gb} GB (Xray)\n✅ موفق: {done}\n⛔ خطا: {errors}", "📊 +{gb} GB (Xray)\n✅ done: {done}\n⛔ errors: {errors}" );
		self::pair( $r, 'msg.admin.ipn_link_line', 'messages', "\n🔗 {url}", "\n🔗 {url}" );
		self::pair( $r, 'msg.admin.user_requeued', 'messages', '✅ کاربر #{id} به صف برگردانده شد.', '✅ User #{id} requeued.' );
		self::pair( $r, 'msg.admin.requeue_failed', 'messages', '⛔ نشد: {reason}', '⛔ Failed: {reason}' );
		self::pair( $r, 'msg.admin.prompt_dm', 'messages', "✉️ پیام خود را برای کاربر #{id} بفرستید.\n/cancel برای لغو.", "✉️ Send your message for user #{id}.\n/cancel to abort." );
		self::pair( $r, 'msg.admin.button_unknown', 'messages', 'ℹ️ این دکمه شناخته نشد.', 'ℹ️ Unknown button.' );
		self::pair( $r, 'msg.admin.catalog.categories_hint', 'messages', '📂 دسته‌های پلن — ➕ جدید؛ ردیف اول فعال/غیر، 🗑 حذف', '📂 Plan categories — ➕ new; toggle row; 🗑 delete' );
		self::pair( $r, 'msg.admin.catalog.plans_hint', 'messages', '📋 پلن‌ها (حداکثر ۲۰) — ➕ جدید؛ ردیف فعال/غیر؛ 🗑 حذف', '📋 Plans (max 20) — ➕ new; toggle; 🗑 delete' );
		self::pair( $r, 'msg.admin.catalog.cards_hint', 'messages', '💳 کارت‌ها — ➕ جدید؛ ردیف فعال/غیر؛ 🗑 حذف', '💳 Cards — ➕ new; toggle; 🗑 delete' );
		self::pair( $r, 'msg.admin.inbound_pick_panel', 'messages', '📡 ابتدا پنل را برای لیست Inbound انتخاب کنید:', '📡 Pick a panel for the inbound list:' );
		self::pair( $r, 'msg.admin.inbound_pick_one', 'messages', '📡 Inboundها — یکی را انتخاب کنید', '📡 Inbounds — pick one' );
		self::pair( $r, 'msg.admin.backup.interval_prompt', 'messages', '⏱ تعداد دقیقه (حداقل ۵) را عدد ارسال کنید. /cancel', '⏱ Send interval in minutes (min 5). /cancel' );
		self::pair(
			$r,
			'msg.admin.backup.restore_warning',
			'messages',
			"⚠️ ریستور، جداول پلاگین svp_* و گزینه‌های پلاگین SimpleVPBot را از فایل زیپ جایگزین می‌کند.\nفقط اگر بکاپ معتبر و آگاهانه است ادامه دهید.",
			"⚠️ Restore replaces svp_* tables and SimpleVPBot options from the zip.\nContinue only with a valid backup."
		);
		self::pair( $r, 'msg.admin.backup.restore_zip_prompt', 'messages', '📎 فقط فایل .zip بکاپ SimpleVPBot را بفرستید. /cancel', '📎 Send only a SimpleVPBot .zip backup. /cancel' );
		self::pair( $r, 'msg.admin.portal_link_prefix', 'messages', '🌐 {url}', '🌐 {url}' );
		self::pair( $r, 'msg.admin.admin_portal_link_prefix', 'messages', '🖥 {url}', '🖥 {url}' );
		self::pair( $r, 'btn.pay.card_label', 'buttons', '💳 {suffix} · {holder}', '💳 {suffix} · {holder}' );
		self::pair( $r, 'btn.svc.list_item', 'buttons', '📡 {remark}', '📡 {remark}' );
	}

	/**
	 * Admin settings/catalog wizard prompts and save confirmations.
	 *
	 * @param array<int, array<string, string>> $r Rows.
	 */
	private static function append_msg_admin_wiz( array &$r ) {
		self::pair( $r, 'msg.admin.wiz.default', 'messages', "مقدار جدید را ارسال کنید. /cancel", "Send the new value. /cancel" );

		self::pair( $r, 'msg.admin.wiz.gen_at', 'messages', "📥 آیدی ادمین‌های تلگرام (هر خط یک عدد):\n/cancel", "📥 Telegram admin IDs (one per line):\n/cancel" );
		self::pair( $r, 'msg.admin.wiz.gen_ab', 'messages', "📥 آیدی ادمین‌های بله (هر خط یک عدد):\n/cancel", "📥 Bale admin IDs (one per line):\n/cancel" );
		self::pair( $r, 'msg.admin.wiz.gen_pp', 'messages', "📄 شناسه صفحه پورتال وب (عدد؛ 0=پیش‌فرض /info):\n/cancel", "📄 Web portal page ID (number; 0=default /info):\n/cancel" );
		self::pair( $r, 'msg.admin.wiz.gen_dp', 'messages', "📦 پلن پیش‌فرض سرویس‌های بدون پلن — شناسه پلن Xray فعال (0=خاموش):\n/cancel", "📦 Default plan for services without plan — active Xray plan id (0=off):\n/cancel" );
		self::pair( $r, 'msg.admin.wiz.bot_tt', 'messages', "🤖 Telegram token:\n/cancel", "🤖 Telegram token:\n/cancel" );
		self::pair( $r, 'msg.admin.wiz.bot_bt', 'messages', "🤖 Bale token:\n/cancel", "🤖 Bale token:\n/cancel" );
		self::pair( $r, 'msg.admin.wiz.bot_ts', 'messages', "Secret مسیر Webhook تلگرام:\n/cancel", "Telegram webhook path secret:\n/cancel" );
		self::pair( $r, 'msg.admin.wiz.bot_bs', 'messages', "Secret مسیر Webhook بله:\n/cancel", "Bale webhook path secret:\n/cancel" );
		self::pair( $r, 'msg.admin.wiz.bot_th', 'messages', "Telegram secret header (اختیاری):\n/cancel", "Telegram secret header (optional):\n/cancel" );
		self::pair( $r, 'msg.admin.wiz.bot_bw', 'messages', "Bale wallet provider token:\n/cancel", "Bale wallet provider token:\n/cancel" );
		self::pair( $r, 'msg.admin.wiz.pan_u', 'messages', "🖥 Panel URL (3x-ui):\n/cancel", "🖥 Panel URL (3x-ui):\n/cancel" );
		self::pair( $r, 'msg.admin.wiz.pan_n', 'messages', "🖥 نام کاربری پنل:\n/cancel", "🖥 Panel username:\n/cancel" );
		self::pair( $r, 'msg.admin.wiz.pan_p', 'messages', "🖥 رمز پنل:\n/cancel", "🖥 Panel password:\n/cancel" );
		self::pair( $r, 'msg.admin.wiz.pan_a', 'messages', "🖥 API base path (مثلاً panel/api):\n/cancel", "🖥 API base path (e.g. panel/api):\n/cancel" );
		self::pair( $r, 'msg.admin.wiz.pan_l', 'messages', "🖥 Login secret (اختیاری):\n/cancel", "🖥 Login secret (optional):\n/cancel" );
		self::pair( $r, 'msg.admin.wiz.pan_s', 'messages', "🌐 subscription public base:\n/cancel", "🌐 Subscription public base:\n/cancel" );
		self::pair( $r, 'msg.admin.wiz.not_l', 'messages', "🔔 آستانه حجم کم (٪) — حداقل ۱:\n/cancel", "🔔 Low traffic threshold (%) — minimum 1:\n/cancel" );
		self::pair( $r, 'msg.admin.wiz.not_e', 'messages', "🔔 روزهای هشدار (با کاما مثل 3,1):\n/cancel", "🔔 Expiry reminder days (comma-separated, e.g. 3,1):\n/cancel" );
		self::pair( $r, 'msg.admin.wiz.not_d', 'messages', "🔔 تعداد کاربر هم‌زمان پیش‌فرض (≥0):\n/cancel", "🔔 Default concurrent users (≥0):\n/cancel" );
		self::pair( $r, 'msg.admin.wiz.not_p', 'messages', "🔔 قیمت هر کاربر اضافه (تومان):\n/cancel", "🔔 Price per extra user (Toman):\n/cancel" );
		self::pair( $r, 'msg.admin.wiz.cry_ak', 'messages', "₿ NOWPayments API key:\n/cancel", "₿ NOWPayments API key:\n/cancel" );
		self::pair( $r, 'msg.admin.wiz.cry_in', 'messages', "₿ NOWPayments IPN secret:\n/cancel", "₿ NOWPayments IPN secret:\n/cancel" );
		self::pair( $r, 'msg.admin.wiz.cry_cu', 'messages', "₿ pay_currency (مثل usdttrc20):\n/cancel", "₿ pay_currency (e.g. usdttrc20):\n/cancel" );

		self::pair( $r, 'msg.admin.wiz.pc', 'messages', "➕ دسته پلن — دو خط بفرستید:\n۱) slug (a-z0-9_)\n۲) برچسب\n/cancel", "➕ Plan category — send two lines:\n1) slug (a-z0-9_)\n2) label\n/cancel" );
		self::pair(
			$r,
			'msg.admin.wiz.pl',
			'messages',
			"➕ پلن Xray (قیمت ثابت) — ۷ خط پشت‌سرهم:\n۱ نام · ۲ slug دسته · ۳ مدت (روز) · ۴ ترافیک GB · ۵ قیمت · ۶ inbound_id · ۷ تعداد کلاینت\nمثال (هر مقدار در یک خط):\nپلن آزمایشی\nnormal\n30\n20\n100000\n1\n1\n/cancel",
			"➕ Xray plan (fixed price) — 7 lines in order:\n1 name · 2 category slug · 3 duration (days) · 4 traffic GB · 5 price · 6 inbound_id · 7 client count\nExample (one value per line):\nTrial plan\nnormal\n30\n20\n100000\n1\n1\n/cancel"
		);
		self::pair(
			$r,
			'msg.admin.wiz.cd',
			'messages',
			"➕ کارت — یک خط با | جدا کنید:\nشماره|صاحب|بانک|روش(c2c|crypto|crypto_auto)|سقف_روزانه|اولویت[|یادداشت اختیاری]\n/cancel",
			"➕ Card — one line pipe-separated:\nnumber|holder|bank|method(c2c|crypto|crypto_auto)|daily_cap|priority[|optional note]\n/cancel"
		);
		self::pair(
			$r,
			'msg.admin.wiz.l2',
			'messages',
			"➕ سرور L2TP — یک خط با | (احراز رمز SSH):\nlabel|ssh_host|port|ssh_user|l2tp_host|ssh_password|psk\n/cancel",
			"➕ L2TP server — one pipe-separated line (SSH password auth):\nlabel|ssh_host|port|ssh_user|l2tp_host|ssh_password|psk\n/cancel"
		);

		self::pair( $r, 'msg.admin.wiz.ok_gen_at', 'messages', '✅ آیدی ادمین تلگرام به‌روز شد ({count} مورد).', '✅ Telegram admin IDs updated ({count}).' );
		self::pair( $r, 'msg.admin.wiz.ok_gen_ab', 'messages', '✅ آیدی ادمین بله به‌روز شد ({count} مورد).', '✅ Bale admin IDs updated ({count}).' );
		self::pair( $r, 'msg.admin.wiz.ok_gen_pp', 'messages', '✅ portal_page_id={value}', '✅ portal_page_id={value}' );
		self::pair( $r, 'msg.admin.wiz.ok_gen_dp', 'messages', '✅ default_service_plan_id={value}', '✅ default_service_plan_id={value}' );
		self::pair( $r, 'msg.admin.wiz.ok_bot_tt', 'messages', '✅ توکن تلگرام ذخیره شد.', '✅ Telegram token saved.' );
		self::pair( $r, 'msg.admin.wiz.ok_bot_bt', 'messages', '✅ توکن بله ذخیره شد.', '✅ Bale token saved.' );
		self::pair( $r, 'msg.admin.wiz.ok_bot_ts', 'messages', '✅ Webhook secret تلگرام ذخیره شد.', '✅ Telegram webhook secret saved.' );
		self::pair( $r, 'msg.admin.wiz.ok_bot_bs', 'messages', '✅ Webhook secret بله ذخیره شد.', '✅ Bale webhook secret saved.' );
		self::pair( $r, 'msg.admin.wiz.ok_bot_th', 'messages', '✅ header ذخیره شد.', '✅ Header saved.' );
		self::pair( $r, 'msg.admin.wiz.ok_bot_bw', 'messages', '✅ توکن کیف پول بله ذخیره شد.', '✅ Bale wallet token saved.' );
		self::pair( $r, 'msg.admin.wiz.ok_pan_u', 'messages', '✅ آدرس پنل ذخیره شد.', '✅ Panel URL saved.' );
		self::pair( $r, 'msg.admin.wiz.ok_pan_n', 'messages', '✅ نام کاربری ذخیره شد.', '✅ Username saved.' );
		self::pair( $r, 'msg.admin.wiz.ok_pan_p', 'messages', '✅ رمز پنل ذخیره شد.', '✅ Panel password saved.' );
		self::pair( $r, 'msg.admin.wiz.ok_pan_a', 'messages', '✅ API base ذخیره شد.', '✅ API base saved.' );
		self::pair( $r, 'msg.admin.wiz.ok_pan_l', 'messages', '✅ login secret ذخیره شد.', '✅ Login secret saved.' );
		self::pair( $r, 'msg.admin.wiz.ok_pan_s', 'messages', '✅ آدرس subscription ذخیره شد.', '✅ Subscription URL saved.' );
		self::pair( $r, 'msg.admin.wiz.ok_not_l', 'messages', '✅ آستانه ٪{value}', '✅ Threshold %{value}' );
		self::pair( $r, 'msg.admin.wiz.ok_not_e', 'messages', '✅ روزها ذخیره شد.', '✅ Days saved.' );
		self::pair( $r, 'msg.admin.wiz.ok_not_d', 'messages', '✅ default concurrent = {value}', '✅ default concurrent = {value}' );
		self::pair( $r, 'msg.admin.wiz.ok_not_p', 'messages', '✅ قیمت ذخیره شد.', '✅ Price saved.' );
		self::pair( $r, 'msg.admin.wiz.ok_cry_ak', 'messages', '✅ API key ذخیره شد.', '✅ API key saved.' );
		self::pair( $r, 'msg.admin.wiz.ok_cry_in', 'messages', '✅ IPN secret ذخیره شد.', '✅ IPN secret saved.' );
		self::pair( $r, 'msg.admin.wiz.ok_cry_cu', 'messages', '✅ pay_currency ذخیره شد.', '✅ pay_currency saved.' );
	}

	/**
	 * Service + payment inline buttons.
	 *
	 * @param array<int, array<string, string>> $r Rows.
	 */
	private static function append_btn_svc_pay( array &$r ) {
		self::pair( $r, 'btn.common.back', 'buttons', '⬅️ بازگشت', '⬅️ Back' );
		self::pair( $r, 'btn.common.web_panel', 'buttons', 'پنل وب', 'Web panel' );
		self::pair( $r, 'btn.common.web_panel_cfg', 'buttons', '🌐 پنل وب (کانفیگ)', '🌐 Web panel (config)' );
		self::pair( $r, 'btn.common.web_panel_cfg_qr', 'buttons', '🌐 پنل وب (کانفیگ و QR)', '🌐 Web panel (config & QR)' );
		self::pair( $r, 'btn.common.copy_card', 'buttons', '📋 کپی کارت', '📋 Copy card' );
		self::pair( $r, 'btn.common.copy_amount', 'buttons', '💵 کپی مبلغ (ریال)', '💵 Copy amount (Rial)' );
		self::pair( $r, 'btn.common.copy_card_number', 'buttons', '📋 کپی شماره کارت', '📋 Copy card number' );
		self::pair( $r, 'btn.common.copy_amount_toman', 'buttons', '💵 کپی مبلغ (تومان)', '💵 Copy amount (Toman)' );
		self::pair( $r, 'btn.common.copy_wallet', 'buttons', '📋 کپی آدرس ولت', '📋 Copy wallet address' );
		self::pair( $r, 'btn.common.copy_memo', 'buttons', '📝 کپی یادداشت / ممو', '📝 Copy memo' );
		self::pair( $r, 'btn.common.link', 'buttons', '🔗', '🔗' );
		self::pair( $r, 'btn.pay.discount_code', 'buttons', '🏷 کد تخفیف', '🏷 Discount code' );
		self::pair( $r, 'btn.pay.remove_discount', 'buttons', '↩️ حذف تخفیف', '↩️ Remove discount' );
		self::pair( $r, 'btn.pay.confirm_buy', 'buttons', '✅ تایید خرید', '✅ Confirm purchase' );
		self::pair( $r, 'btn.pay.cancel', 'buttons', '❌ انصراف', '❌ Cancel' );
		self::pair( $r, 'btn.pay.confirm_pay', 'buttons', '✅ تایید و پرداخت', '✅ Confirm & pay' );
		self::pair( $r, 'btn.pay.bale_wallet', 'buttons', '💰 پرداخت با کیف پول بله', '💰 Pay with Bale wallet' );
		self::pair( $r, 'btn.pay.site_wallet', 'buttons', '💼 پرداخت با کیف پول', '💼 Pay with wallet balance' );
		self::pair( $r, 'btn.pay.wallet_partial_yes', 'buttons', '✅ بله', '✅ Yes' );
		self::pair( $r, 'btn.pay.wallet_partial_no', 'buttons', '❌ خیر', '❌ No' );
		self::pair( $r, 'btn.pay.approve_receipt', 'buttons', '✅ تایید رسید', '✅ Approve receipt' );
		self::pair( $r, 'btn.pay.reject_receipt', 'buttons', '❌ رد رسید', '❌ Reject receipt' );
		self::pair( $r, 'btn.admin.user_approve', 'buttons', '✅ کاربر {id}', '✅ User {id}' );
		self::pair( $r, 'btn.admin.user_reject', 'buttons', '❌ کاربر {id}', '❌ User {id}' );
		self::pair( $r, 'btn.admin.users_approved_list', 'buttons', '✅ لیست تأییدشده‌ها', '✅ Approved list' );
		self::pair( $r, 'btn.admin.users_approved_next', 'buttons', 'تأییدشده بعدی ▶', 'Approved next ▶' );
		self::pair( $r, 'btn.admin.users_approved_prev', 'buttons', '◀ تأییدشده قبلی', '◀ Approved prev' );
		self::pair( $r, 'btn.admin.users_pending_next', 'buttons', 'انتظار بعدی ▶', 'Pending next ▶' );
		self::pair( $r, 'btn.admin.users_pending_prev', 'buttons', '◀ انتظار قبلی', '◀ Pending prev' );
		self::pair( $r, 'btn.admin.bulk_days', 'buttons', '+{n} روز', '+{n} days' );
		self::pair( $r, 'btn.admin.bulk_gb', 'buttons', '+{n} GB', '+{n} GB' );
		self::pair( $r, 'btn.admin.reg_approve', 'buttons', '✅ ثبت‌نام #{id}', '✅ Approve signup #{id}' );
		self::pair( $r, 'btn.admin.reg_reject', 'buttons', '❌ رد ثبت‌نام #{id}', '❌ Reject signup #{id}' );
		self::pair( $r, 'btn.admin.receipt_approve', 'buttons', '✅ رسید {id}', '✅ Receipt {id}' );
		self::pair( $r, 'btn.admin.receipt_reject', 'buttons', '❌ رد رسید {id}', '❌ Reject receipt {id}' );
		self::pair( $r, 'btn.svc.show_connection', 'buttons', '🔐 نمایش اتصال', '🔐 Show connection' );
		self::pair( $r, 'btn.svc.show_usage', 'buttons', '📊 نمایش مصرف', '📊 Show usage' );
		self::pair( $r, 'btn.svc.change_password', 'buttons', '🔑 تغییر رمز عبور', '🔑 Change password' );
		self::pair( $r, 'btn.svc.renew', 'buttons', '♻️ تمدید سرویس', '♻️ Renew service' );
		self::pair( $r, 'btn.svc.renew_short', 'buttons', '♻️ تمدید', '♻️ Renew' );
		self::pair( $r, 'btn.svc.auto_renew', 'buttons', '🔁 تمدید خودکار', '🔁 Auto-renew' );
		self::pair( $r, 'btn.svc.alerts', 'buttons', '🔔 هشدارها', '🔔 Alerts' );
		self::pair( $r, 'btn.svc.rename', 'buttons', '✏️ تغییر نام', '✏️ Rename' );
		self::pair( $r, 'btn.svc.faq', 'buttons', '❓ راهنمای اتصال', '❓ Connection guide' );
		self::pair( $r, 'btn.svc.support', 'buttons', '🆘 پشتیبانی', '🆘 Support' );
		self::pair( $r, 'btn.svc.transfer', 'buttons', '🎁 انتقال سرویس', '🎁 Transfer service' );
		self::pair( $r, 'btn.svc.regenerate_key', 'buttons', '🔑 بازسازی کلید', '🔑 Regenerate key' );
		self::pair( $r, 'btn.svc.update_servers', 'buttons', '🔄 آپدیت سرورها', '🔄 Update servers' );
		self::pair( $r, 'btn.svc.add_volume', 'buttons', '➕ افزایش حجم', '➕ Add volume' );
		self::pair( $r, 'btn.svc.add_users', 'buttons', '👥 افزایش کاربر', '👥 Add users' );
		self::pair( $r, 'btn.svc.panel_note', 'buttons', '📝 یادداشت پنل', '📝 Panel note' );
		self::pair( $r, 'btn.svc.config_qr', 'buttons', '🔗 کانفیگ و QR', '🔗 Config & QR' );
		self::pair( $r, 'btn.svc.active_connections', 'buttons', '🌐 اتصالات فعال', '🌐 Active connections' );
		self::pair( $r, 'btn.svc.faq_short', 'buttons', '❓ سوالات متداول', '❓ FAQ' );
		self::pair( $r, 'btn.svc.config', 'buttons', '📋 کانفیگ', '📋 Config' );
		self::pair( $r, 'btn.svc.config_n', 'buttons', '📋 کانفیگ {n}', '📋 Config {n}' );
		self::pair( $r, 'btn.svc.back_manage', 'buttons', '⬅️ بازگشت به مدیریت سرویس', '⬅️ Back to service menu' );
		self::pair( $r, 'btn.svc.open_alerts', 'buttons', '🔔 باز کردن پنل هشدار', '🔔 Open alerts panel' );
		self::pair( $r, 'btn.svc.copy_server', 'buttons', '📋 کپی سرور', '📋 Copy server' );
		self::pair( $r, 'btn.svc.copy_psk', 'buttons', '📋 کپی PSK', '📋 Copy PSK' );
		self::pair( $r, 'btn.svc.copy_username', 'buttons', '📋 کپی نام کاربری', '📋 Copy username' );
		self::pair( $r, 'btn.svc.copy_password', 'buttons', '📋 کپی رمز عبور', '📋 Copy password' );
	}

	/**
	 * Service messages.
	 *
	 * @param array<int, array<string, string>> $r Rows.
	 */
	private static function append_msg_svc( array &$r ) {
		self::pair( $r, 'msg.svc.not_found', 'messages', '⛔ سرویس یافت نشد.', '⛔ Service not found.' );
		self::pair( $r, 'msg.svc.l2tp_password_ok', 'messages', '🔑 رمز جدید برای سرویس L2TP ساخته شد.', '🔑 A new L2TP password was created.' );
		self::pair( $r, 'msg.svc.l2tp_option_na', 'messages', 'ℹ️ این گزینه برای سرویس L2TP در دسترس نیست.', 'ℹ️ This option is not available for L2TP services.' );
		self::pair( $r, 'msg.svc.link_not_found', 'messages', '⚠️ لینک اتصال یافت نشد.', '⚠️ Connection link not found.' );
		self::pair( $r, 'msg.svc.panel_login_fail', 'messages', '⛔ ورود به پنل ناموفق است.', '⛔ Panel login failed.' );
		self::pair( $r, 'msg.svc.uuid_fail', 'messages', '⛔ دریافت UUID جدید ناموفق بود.', '⛔ Could not fetch a new UUID.' );
		self::pair( $r, 'msg.svc.inbound_not_found', 'messages', '⛔ اینباند پنل یافت نشد.', '⛔ Panel inbound not found.' );
		self::pair( $r, 'msg.svc.client_id_invalid', 'messages', '⛔ شناسه کلاینت در پنل یافت نشد (ایمیل یا UUID نامعتبر).', '⛔ Client id not found on panel (invalid email or UUID).' );
		self::pair( $r, 'msg.svc.client_list_empty', 'messages', '⛔ فهرست کلاینت خالی است.', '⛔ Client list is empty.' );
		self::pair( $r, 'msg.svc.client_not_found', 'messages', '⛔ کلاینت این سرویس روی پنل پیدا نشد.', '⛔ This service client was not found on the panel.' );
		self::pair( $r, 'msg.svc.panel_update_fail', 'messages', '⛔ بروزرسانی روی پنل انجام نشد.', '⛔ Panel update failed.' );
		self::pair( $r, 'msg.svc.uuid_regenerated', 'messages', '🔑 کلید (UUID) جدید ساخته شد و روی سرویس ثبت گردید.', '🔑 New key (UUID) created and saved on the service.' );
		self::pair( $r, 'btn.svc.regenerate_sub_id', 'buttons', '🔗 بازسازی لینک اشتراک', '🔗 Regenerate subscription' );
		self::pair( $r, 'msg.svc.sub_id_regenerated', 'messages', '🔗 شناسه اشتراک (subId) جدید ساخته شد. لینک اشتراک قبلی دیگر کار نمی‌کند.', '🔗 New subscription id (subId) created. The old subscription link will stop working.' );
		self::pair( $r, 'msg.svc.servers_refreshed', 'messages', '🔄 اطلاعات سرور به‌روز شد.', '🔄 Server info updated.' );
		self::pair( $r, 'msg.svc.auto_renew_on', 'messages', '🔁 تمدید خودکار: ✅ روشن', '🔁 Auto-renew: ✅ On' );
		self::pair( $r, 'msg.svc.auto_renew_off', 'messages', '🔁 تمدید خودکار: ❌ خاموش', '🔁 Auto-renew: ❌ Off' );
		self::pair( $r, 'msg.svc.prompt_panel_note', 'messages', '📝 یادداشت نمایش (نام روی پنل X-UI) را ارسال کنید:', '📝 Send the display note (name on X-UI panel):' );
		self::pair( $r, 'msg.svc.prompt_display_name', 'messages', '✏️ نام نمایشی این سرویس (در ربات و لیست سرویس‌ها) را ارسال کنید:', '✏️ Send this service display name (in bot and service list):' );
		self::pair( $r, 'msg.svc.default_plan_missing', 'messages', '⛔ پلن سرویس برای صدور فاکتور تنظیم نشده. در تنظیمات عمومی، «پلن پیش‌فرض سرویس‌های بدون پلن» را روی یک پلن Xray فعال بگذارید.', '⛔ No service plan for invoicing. In general settings, set “Default plan for services without a plan” to an active Xray plan.' );
		self::pair( $r, 'msg.svc.internal_button_error', 'messages', '⛔ خطای داخلی دکمه‌ها.', '⛔ Internal button error.' );
		self::pair( $r, 'msg.svc.volume_xray_only', 'messages', '⛔ افزایش حجم از این مسیر فقط برای Xray است.', '⛔ Volume add via this path is only for Xray.' );
		self::pair(
			$r,
			'msg.svc.renew_too_early_use_volume',
			'messages',
			'⛔ بیش از ۵ روز تا پایان سرویس مانده است. برای افزودن حجم از دکمه «افزایش حجم» استفاده کنید.',
			'⛔ More than 5 days remain on this service. Use “Add volume” to top up traffic.'
		);
		self::pair(
			$r,
			'msg.svc.volume_too_late_use_renew',
			'messages',
			'⛔ ۵ روز یا کمتر تا پایان سرویس مانده است. برای ادامه از دکمه «تمدید» استفاده کنید.',
			'⛔ 5 days or less remain on this service. Use “Renew” to extend it.'
		);
		self::pair( $r, 'msg.svc.prompt_add_volume_gb', 'messages', '➕ چند گیگابایت به سقف حجم اضافه شود؟ فقط عدد (گیگ) بفرستید؛ مثلاً 10', '➕ How many GB to add? Send a number only (e.g. 10).' );
		self::pair( $r, 'msg.svc.option_wrong_type', 'messages', '⛔ این گزینه برای این نوع سرویس نیست.', '⛔ This option is not for this service type.' );
		self::pair( $r, 'msg.svc.transfer_code_fail', 'messages', '⛔ امکان تولید کد انتقال نیست.', '⛔ Could not generate transfer code.' );
		self::pair( $r, 'msg.svc.invalid_service', 'messages', '⛔ سرویس نامعتبر است.', '⛔ Invalid service.' );
		self::pair( $r, 'msg.svc.alert_days_1_99', 'messages', '⛔ فقط یک عدد ۱ تا ۹۹ بفرستید.', '⛔ Send one number from 1 to 99.' );
		self::pair( $r, 'msg.svc.alert_days_range', 'messages', '⛔ عدد باید بین ۱ تا ۹۹ باشد.', '⛔ Number must be between 1 and 99.' );
		self::pair( $r, 'msg.svc.alert_days_min', 'messages', '⛔ حداقل یک روز معتبر بفرستید، مثل ۳,۱,۰', '⛔ Send at least one valid day, e.g. 3,1,0' );
		self::pair( $r, 'msg.svc.alert_pct_50_100', 'messages', '⛔ فقط یک عدد ۵۰ تا ۱۰۰ بفرستید.', '⛔ Send one number from 50 to 100.' );
		self::pair( $r, 'msg.svc.alert_pct_range', 'messages', '⛔ عدد باید بین ۵۰ تا ۱۰۰ باشد.', '⛔ Number must be between 50 and 100.' );
		self::pair( $r, 'msg.svc.invalid_session', 'messages', '⛔ جلسه نامعتبر است. دوباره از منوی سرویس شروع کنید.', '⛔ Invalid session. Start again from the service menu.' );
		self::pair( $r, 'msg.svc.integer_only', 'messages', '⛔ فقط یک عدد صحیح بفرستید (مثلا 10).', '⛔ Send one integer only (e.g. 10).' );
		self::pair( $r, 'msg.svc.min_1_gb', 'messages', '⛔ حداقل ۱ گیگ است.', '⛔ Minimum is 1 GB.' );
		self::pair( $r, 'msg.svc.volume_range', 'messages', '⛔ برای این پلن حجم اضافه باید بین {min} و {max} گیگ باشد.', '⛔ For this plan, extra volume must be between {min} and {max} GB.' );
		self::pair( $r, 'msg.svc.max_512_gb', 'messages', '⛔ حداکثر ۵۱۲ گیگ در هر درخواست.', '⛔ Maximum 512 GB per request.' );
		self::pair( $r, 'msg.svc.invalid_amount', 'messages', '⛔ مبلغ نامعتبر است. با ادمین تماس بگیرید.', '⛔ Invalid amount. Contact an admin.' );
		self::pair( $r, 'msg.svc.slots_integer', 'messages', '⛔ فقط یک عدد بفرستید مثل ۲.', '⛔ Send one number, e.g. 2.' );
		self::pair( $r, 'msg.svc.slots_range', 'messages', '⛔ عدد باید بین ۱ تا ۵۰ باشد.', '⛔ Number must be between 1 and 50.' );
		self::pair( $r, 'msg.svc.extra_user_price_zero', 'messages', '⛔ قیمت هر کاربر اضافه در تنظیمات صفر است. با ادمین تماس بگیرید.', '⛔ Extra user price is zero in settings. Contact an admin.' );
		self::pair( $r, 'msg.svc.empty_text', 'messages', '⛔ متن خالی است.', '⛔ Text is empty.' );
		self::pair( $r, 'msg.svc.note_updated', 'messages', '✅ یادداشت به‌روز شد.', '✅ Note updated.' );
		self::pair( $r, 'msg.svc.display_name_updated', 'messages', '✅ نام نمایشی به‌روز شد.', '✅ Display name updated.' );
		self::pair( $r, 'msg.svc.display_name_bot_updated', 'messages', '✅ نام نمایشی در ربات به‌روز شد.', '✅ Display name updated in bot.' );
		self::pair( $r, 'msg.svc.panel_login_retry', 'messages', '⛔ ورود به پنل ناموفق است. بعداً دوباره تلاش کنید.', '⛔ Panel login failed. Try again later.' );
		self::pair( $r, 'msg.svc.client_list_empty_panel', 'messages', '⛔ فهرست کلاینت روی پنل خالی است.', '⛔ Client list on panel is empty.' );
		self::pair( $r, 'msg.svc.client_not_found_panel', 'messages', '⛔ کلاینت روی پنل پیدا نشد.', '⛔ Client not found on panel.' );
		self::pair( $r, 'msg.svc.client_id_not_found_panel', 'messages', '⛔ شناسه کلاینت روی پنل پیدا نشد.', '⛔ Client id not found on panel.' );
		self::pair( $r, 'msg.svc.note_and_name_updated', 'messages', '✅ یادداشت روی پنل و نام نمایشی در ربات به‌روز شد.', '✅ Panel note and bot display name updated.' );
		self::pair( $r, 'msg.svc.invalid_access', 'messages', '⛔ دسترسی نامعتبر است.', '⛔ Invalid access.' );
		self::pair( $r, 'msg.svc.config_unavailable', 'messages', '⛔ این کانفیگ دیگر در دسترس نیست. منوی سرویس را دوباره باز کنید.', '⛔ This config is no longer available. Reopen the service menu.' );
		self::pair( $r, 'msg.svc.deleted_from_panel', 'messages', '⛔ این سرویس دیگر روی پنل نیست و از لیست شما حذف شد.', '⛔ This service is no longer on the panel and was removed from your list.' );
		self::pair( $r, 'msg.svc.usage_live_footer', 'messages', '📡 مصرف و وضعیت: زنده از پنل (لحظهٔ باز کردن این صفحه).', '📡 Usage & status: live from panel (when you opened this page).' );
		self::pair( $r, 'msg.svc.usage_cache_footer', 'messages', '⚠️ اتصال زنده به پنل برقرار نشد؛ اعداد مصرف از آخرین ذخیرهٔ ربات (کش DB) است.', '⚠️ Live panel unreachable; usage numbers are from the last bot cache (DB).' );
		self::pair( $r, 'msg.svc.usage_stale_footer', 'messages', 'ℹ️ مصرف از پنل خوانده شد؛ انقضای DB ممکن است با پنل چند دقیقه اختلاف داشته باشد.', 'ℹ️ Usage read from panel; DB expiry may differ by a few minutes.' );
		self::pair( $r, 'msg.svc.usage_sync_uncertain', 'messages', '⚠️ سرویس در این لحظه روی پنل در لیست کلاینت‌ها دیده نشد؛ اشتراک شما در ربات حذف نشده است. اگر این پیام تکرار شد با پشتیبانی تماس بگیرید.', '⚠️ Service not seen on panel client list right now; your subscription is not removed in the bot. Contact support if this repeats.' );
		self::pair( $r, 'msg.svc.portal_config_hint', 'messages', '🌐 کانفیگ و QR فقط داخل پنل زیر در دسترس است.', '🌐 Config and QR are only available in the panel below.' );
		self::pair( $r, 'msg.svc.list_title', 'messages', '🧰 سرویس‌های شما', '🧰 Your services' );
		self::pair( $r, 'msg.svc.summary', 'messages', "📡 سرویس: {name}\n📶 وضعیت: {status}\n⏳ انقضا: {expiry}", "📡 Service: {name}\n📶 Status: {status}\n⏳ Expiry: {expiry}" );
		self::pair( $r, 'msg.svc.connections_title', 'messages', '🌐 اتصالات فعال', '🌐 Active connections' );
		self::pair( $r, 'msg.svc.connections_empty', 'messages', '📭 هنوز موردی نیست', '📭 Nothing yet' );
		self::pair( $r, 'msg.svc.renew_l2tp_blocked', 'messages', 'ℹ️ تمدید L2TP از این مسیر پشتیبانی نمی‌شود.', 'ℹ️ L2TP renew is not supported via this path.' );
		self::pair( $r, 'msg.svc.l2tp_password_fail', 'messages', '⛔ تغییر رمز ناموفق بود. با پشتیبانی تماس بگیرید.', '⛔ Password change failed. Contact support.' );
		self::pair( $r, 'msg.svc.support_contact_admin', 'messages', '🆘 با ادمین تماس بگیرید یا از بخش پشتیبانی تیکت بفرستید.', '🆘 Contact an admin or open a support ticket.' );
		self::pair( $r, 'msg.svc.renew_xray_only', 'messages', '⛔ تمدید با پرداخت از این مسیر فقط برای سرویس‌های Xray است؛ برای L2TP با پشتیبانی تماس بگیرید.', '⛔ Paid renew via this path is Xray only; contact support for L2TP.' );
		self::pair( $r, 'msg.svc.connection_info_missing', 'messages', '⛔ اطلاعات اتصال یافت نشد. با پشتیبانی تماس بگیرید.', '⛔ Connection info not found. Contact support.' );
		self::pair( $r, 'msg.svc.pergb_plan_missing', 'messages', '⛔ پلن سرویس برای قیمت‌گذاری حجم مشخص نیست. از ادمین بخواهید در تنظیمات عمومی، «پلن پیش‌فرض سرویس‌های بدون پلن» را روی یک پلن Xray فعال بگذارد.', '⛔ No plan for volume pricing. Ask admin to set default Xray plan in general settings.' );
		self::pair( $r, 'msg.svc.extra_user_price_unset', 'messages', "👥 افزایش کاربر\n{sep}🧒 هنوز قیمتش توسط ادمین تنظیم نشده.\n✋ بعداً دوباره امتحان کن یا از پشتیبانی بپرس.", "👥 Add users\n{sep}🧒 Price not set by admin yet.\n✋ Try again later or ask support." );
		self::pair( $r, 'msg.svc.plan_missing_for_section', 'messages', '⛔ پلن سرویس برای این بخش ثبت نشده. از ادمین بخواهید در تنظیمات عمومی، «پلن پیش‌فرض سرویس‌های بدون پلن» را روی یک پلن Xray فعال بگذارد (برای هم‌خوانی با پنل).', '⛔ No plan for this section. Ask admin to set default Xray plan in general settings (panel sync).' );
		self::pair( $r, 'msg.alerts.threshold_cancel_hint', 'messages', '🔙 برای ول کردن از منوی پایین یک دکمه بزن یا از پیام قبلی «بازگشت به هشدارها» را بزن.', '🔙 Tap a bottom menu button or «Back to alerts» on the previous message to cancel.' );
		self::pair( $r, 'msg.svc.alert_threshold_saved', 'messages', "✅ ذخیره شد.\n{sep}📋 الان:\n{summary}", "✅ Saved.\n{sep}📋 Current:\n{summary}" );
		self::pair( $r, 'msg.svc.subscription_not_ready', 'messages', "⚠️ لینک اشتراک هنوز آماده نیست.\n🧒 از ادمین بخواه اشتراک را روی سرور روشن کند و آدرس عمومی اشتراک در تنظیمات سایت درست شود.", "⚠️ Subscription link not ready yet.\n🧒 Ask admin to enable subscription on the server and fix the public subscription URL in site settings." );
		self::pair( $r, 'msg.svc.subscription_link_title', 'messages', '🔗 لینک اشتراک', '🔗 Subscription link' );
		self::pair( $r, 'msg.svc.subscription_qr_caption', 'messages', '📷 QR لینک اشتراک', '📷 Subscription QR' );
		self::pair( $r, 'msg.svc.telegram_config_send_fail', 'messages', '⛔ ارسال کانفیگ/QR در تلگرام انجام نشد. دوباره تلاش کنید یا با پشتیبانی تماس بگیرید.', '⛔ Could not send config/QR on Telegram. Retry or contact support.' );
	}

	/**
	 * Buy / checkout messages.
	 *
	 * @param array<int, array<string, string>> $r Rows.
	 */
	private static function append_msg_buy( array &$r ) {
		self::pair( $r, 'msg.buy.order_brand', 'messages', '🧾 سفارش {brand} #{id}', '🧾 Order {brand} #{id}' );
		self::pair( $r, 'msg.buy.order', 'messages', '🧾 سفارش #{id}', '🧾 Order #{id}' );
		self::pair( $r, 'msg.buy.discount_line', 'messages', '🏷 کد: {code} · تخفیف: {discount} تومان', '🏷 Code: {code} · Discount: {discount} Toman' );
		self::pair( $r, 'msg.buy.before_discount', 'messages', 'قبل از تخفیف: {subtotal} تومان', 'Before discount: {subtotal} Toman' );
		self::pair( $r, 'msg.buy.payable', 'messages', '💵 قابل پرداخت: {amount} تومان', '💵 Payable: {amount} Toman' );
		self::pair( $r, 'msg.buy.wallet_balance', 'messages', '💼 موجودی کیف پول شما: {balance} تومان', '💼 Your wallet balance: {balance} Toman' );
		self::pair( $r, 'msg.buy.wallet_applied_line', 'messages', '💼 از کیف پول: {applied} تومان', '💼 From wallet: {applied} Toman' );
		self::pair( $r, 'msg.buy.wallet_remaining_line', 'messages', '💵 باقی‌مانده: {remaining} تومان', '💵 Remaining: {remaining} Toman' );
		self::pair( $r, 'msg.buy.wallet_full_confirm', 'messages', 'آیا {amount} تومان از کیف پول شما (موجودی: {balance} تومان) پرداخت شود؟', 'Pay {amount} Toman from your wallet (balance: {balance} Toman)?' );
		self::pair( $r, 'msg.buy.wallet_partial_confirm', 'messages', 'موجودی کیف پول ({balance} تومان) برای پرداخت کامل ({need} تومان) کافی نیست.\nآیا می‌خواهید {balance} تومان از کیف پول کسر شود و {remaining} تومان با کارت یا کیف پول بله پرداخت کنید؟', 'Wallet balance ({balance} Toman) is not enough for the full amount ({need} Toman).\nUse {balance} Toman from wallet and pay {remaining} Toman by card or Bale wallet?' );
		self::pair( $r, 'msg.buy.wallet_insufficient', 'messages', '⛔ موجودی کیف پول شما برای این پرداخت کافی نیست. ابتدا حساب را شارژ کنید یا روش دیگری انتخاب کنید.', '⛔ Wallet balance is insufficient. Top up your wallet or choose another payment method.' );
		self::pair( $r, 'msg.buy.pick_payment', 'messages', 'روش پرداخت را انتخاب کنید:', 'Choose a payment method:' );
		self::pair( $r, 'msg.buy.order_failed', 'messages', '⛔ ثبت سفارش ناموفق بود.', '⛔ Could not create order.' );
		self::pair( $r, 'msg.buy.no_cards', 'messages', '⛔ کارتی ثبت نشده. ادمین را مطلع کنید.', '⛔ No card on file. Notify an admin.' );
		self::pair( $r, 'msg.buy.invalid_order', 'messages', '⛔ سفارش نامعتبر است.', '⛔ Invalid order.' );
		self::pair( $r, 'msg.buy.prompt_discount', 'messages', '🏷 کد تخفیف را بفرستید. برای انصراف «لغو» بفرستید یا از منوی اصلی یک گزینه را انتخاب کنید.', '🏷 Send discount code. Send «cancel» or pick a menu option to abort.' );
		self::pair( $r, 'msg.buy.category_unavailable', 'messages', '⛔ این دسته در دسترس نیست یا غیرفعال شده است.', '⛔ This category is unavailable or disabled.' );
		self::pair( $r, 'msg.buy.plan_unavailable', 'messages', '⛔ این پلن در دسترس نیست یا غیرفعال شده است.', '⛔ This plan is unavailable or disabled.' );
		self::pair( $r, 'msg.buy.plan_misconfigured', 'messages', '⛔ پلن «{name}» اشتباه پیکربندی شده (قیمت به ازای هر گیگابایت یا محدودهٔ حجم). با ادمین تماس بگیرید.', '⛔ Plan «{name}» is misconfigured (per-GB price or volume range). Contact an admin.' );
		self::pair( $r, 'msg.buy.volume_invalid', 'messages', '⛔ حجم انتخاب‌شده معتبر نیست.', '⛔ Selected volume is invalid.' );
		self::pair( $r, 'msg.buy.section_expired', 'messages', '⛔ این بخش خرید منقضی یا نامعتبر است.', '⛔ This purchase section expired or is invalid.' );
		self::pair( $r, 'msg.buy.purchase_invalid', 'messages', '⛔ سفارش خرید نامعتبر است.', '⛔ Invalid purchase order.' );
		self::pair( $r, 'msg.buy.amount_invalid', 'messages', '⛔ مبلغ سفارش نامعتبر است.', '⛔ Invalid order amount.' );
		self::pair( $r, 'msg.buy.wallet_bale_only', 'messages', '⛔ پرداخت کیف پول فقط در بله در دسترس است.', '⛔ Wallet pay is only available on Bale.' );
		self::pair( $r, 'msg.buy.wallet_disabled', 'messages', '⛔ پرداخت کیف پول در حال حاضر غیرفعال است.', '⛔ Wallet payment is currently disabled.' );
		self::pair( $r, 'msg.buy.plan_missing', 'messages', '⛔ پلن این سفارش در دسترس نیست.', '⛔ Plan for this order is unavailable.' );
		self::pair( $r, 'msg.buy.invoice_failed', 'messages', '⛔ ارسال فاکتور ممکن نشد. کمی بعد دوباره تلاش کنید.', '⛔ Could not send invoice. Try again shortly.' );
		self::pair( $r, 'msg.buy.send_receipt_photo', 'messages', '📸 لطفاً تصویر رسید کارت‌به‌کارت را همینجا ارسال کنید.', '📸 Please send the card transfer receipt photo here.' );
		self::pair( $r, 'msg.buy.cancelled', 'messages', '❌ لغو شد.', '❌ Cancelled.' );
		self::pair( $r, 'msg.buy.session_invalid', 'messages', '⛔ جلسه نامعتبر بود.', '⛔ Invalid session.' );
		self::pair( $r, 'msg.buy.discount_cancelled', 'messages', '❌ ورود کد تخفیف لغو شد.', '❌ Discount entry cancelled.' );
		self::pair( $r, 'msg.buy.discount_applied', 'messages', '✅ کد تایید شد. تخفیف: {discount} تومان.', '✅ Code accepted. Discount: {discount} Toman.' );
		self::pair( $r, 'msg.buy.use_menu', 'messages', 'ℹ️ از دکمه‌های منو استفاده کنید.', 'ℹ️ Use the menu buttons.' );
		self::pair( $r, 'msg.buy.session_restart', 'messages', '⛔ جلسه خرید نامعتبر است. دوباره از منو شروع کنید.', '⛔ Invalid purchase session. Start again from the menu.' );
		self::pair( $r, 'msg.buy.integer_gb', 'messages', '⛔ فقط یک عدد صحیح بفرستید (مثلا 20).', '⛔ Send one integer only (e.g. 20).' );
		self::pair( $r, 'msg.buy.volume_range', 'messages', '⛔ حجم باید بین {min} و {max} گیگابایت باشد.', '⛔ Volume must be between {min} and {max} GB.' );
		self::pair( $r, 'msg.buy.id_overflow', 'messages', '⛔ خطای داخلی: شناسه بیش از حد بزرگ است. با ادمین تماس بگیرید.', '⛔ Internal error: id too large. Contact an admin.' );
		self::pair( $r, 'msg.buy.start_from_menu', 'messages', '⛔ ابتدا خرید را از منو شروع کنید.', '⛔ Start purchase from the menu first.' );
		self::pair( $r, 'msg.buy.receipt_received', 'messages', '✅ رسید دریافت شد. پس از تایید ادمین به شما اطلاع داده می‌شود.', '✅ Receipt received. You will be notified after admin approval.' );
		self::pair( $r, 'msg.receipt.rejected_with_reason', 'messages', '⛔ رسید پرداخت شما به علت ({reason}) رد شد.', '⛔ Your payment receipt was rejected because: ({reason}).' );
		self::pair( $r, 'msg.receipt.reject_pick_reason', 'messages', 'دلیل رد رسید را انتخاب کنید:', 'Choose a rejection reason:' );
		self::pair( $r, 'btn.pay.receipt_reject_back', 'buttons', '◀️ بازگشت', '◀️ Back' );
		self::pair( $r, 'msg.buy.no_plans_in_category', 'messages', '⛔ پلنی در این دسته نیست.', '⛔ No plans in this category.' );
		self::pair( $r, 'msg.buy.no_categories', 'messages', '⛔ دستهٔ معتبری برای نمایش نیست.', '⛔ No valid category to show.' );
		self::pair( $r, 'msg.buy.plan_confirm', 'messages', "📦 {name}\n💰 قیمت: {price} تومان\n⏳ مدت: {days} روز · 📊 حجم: {volume} گیگابایت\nتایید می‌کنید؟", "📦 {name}\n💰 Price: {price} Toman\n⏳ Duration: {days} days · 📊 Volume: {volume} GB\nConfirm?" );
		self::pair( $r, 'msg.buy.payment_error', 'messages', '⛔ {message}', '⛔ {message}' );
		self::pair( $r, 'msg.buy.invoice_renew', 'messages', 'تمدید: {name}', 'Renew: {name}' );
		self::pair( $r, 'msg.buy.invoice_purchase', 'messages', 'خرید: {name}', 'Purchase: {name}' );
		self::pair( $r, 'msg.buy.fulfill_failed_refunded', 'messages', '⛔ تکمیل سفارش ناموفق بود. مبلغ به کیف پول شما بازگردانده شد. با پشتیبانی تماس بگیرید.', '⛔ Order fulfillment failed. Amount refunded to your wallet. Contact support.' );
		self::pair( $r, 'msg.buy.admin_self_checkout_ok', 'messages', '✅ به‌عنوان مدیر، این خرید برای خودتان بدون پرداخت ثبت و اعمال شد.', '✅ As admin, this purchase was applied without payment.' );
		self::pair( $r, 'msg.buy.fulfill_failed_bale', 'messages', '⛔ تکمیل سفارش ناموفق بود. مبلغ از کیف پول بله کسر شده است؛ لطفاً با پشتیبانی تماس بگیرید و شماره سفارش را ارسال کنید: #{id}', '⛔ Fulfillment failed. Bale wallet was charged; contact support with order #{id}.' );
		self::pair( $r, 'msg.buy.deprecated_plan_button', 'messages', 'ℹ️ این دکمه دیگر استفاده نمی‌شود. از 🛒 خرید سرویس دوباره شروع کنید؛ همهٔ دسته‌ها در یک لیست نمایش داده می‌شوند.', 'ℹ️ This button is deprecated. Start again from 🛒 Buy service; all categories are in one list.' );
		self::pair( $r, 'msg.buy.no_active_categories', 'messages', '⛔ دستهٔ فعالی با پلن برای خرید وجود ندارد. بعداً مراجعه کنید یا با پشتیبانی تماس بگیرید.', '⛔ No active category with plans for purchase. Try later or contact support.' );
		self::pair( $r, 'msg.buy.pick_category', 'messages', '🛒 دستهٔ سرویس را انتخاب کنید:', '🛒 Pick a service category:' );
		self::pair( $r, 'msg.buy.panel_not_for_sale', 'messages', '⛔ این پنل برای فروش از طریق این ربات در دسترس نیست.', '⛔ This panel is not available for sale via this bot.' );
		self::pair( $r, 'msg.buy.no_categories_for_panel', 'messages', '⛔ برای این پنل دستهٔ فعالی با پلن برای خرید وجود ندارد. لطفاً بعداً مراجعه کنید یا با پشتیبانی تماس بگیرید.', '⛔ No active category with plans for this panel. Try later or contact support.' );
		self::pair( $r, 'msg.buy.payment_create_failed', 'messages', 'خطا در ساخت پرداخت.', 'Payment creation failed.' );
		self::pair( $r, 'msg.buy.crypto_pending_hint', 'messages', '⏳ بعد از تأیید پرداخت در NOWPayments، سفارش خودکار تکمیل می‌شود. اگر چیزی گیر کرد با پشتیبانی تماس بگیرید.', '⏳ After NOWPayments confirms payment, the order completes automatically. Contact support if stuck.' );
		self::pair( $r, 'msg.buy.pergb_confirm', 'messages', "📦 {name}\n📊 حجم: {gb} گیگابایت\n⏳ مدت: {days} روز\n💰 مبلغ قابل پرداخت: {amount} تومان\n\n➖➖➖➖➖➖➖➖\nتایید می‌کنید؟", "📦 {name}\n📊 Volume: {gb} GB\n⏳ Duration: {days} days\n💰 Payable: {amount} Toman\n\n➖➖➖➖➖➖➖➖\nConfirm?" );
		self::pair( $r, 'msg.buy.plan_checkout_summary', 'messages', "📦 {name}\n📊 حجم: {gb} گیگابایت\n⏳ مدت: {days} روز\n💰 مبلغ قابل پرداخت: {amount} تومان", "📦 {name}\n📊 Volume: {gb} GB\n⏳ Duration: {days} days\n💰 Payable: {amount} Toman" );
		self::pair( $r, 'msg.buy.plan_confirm_footer', 'messages', "➖➖➖➖➖➖➖➖\nتایید می‌کنید؟", "➖➖➖➖➖➖➖➖\nConfirm?" );
		self::pair( $r, 'msg.buy.preparing_checkout', 'messages', '⏳ در حال آماده‌سازی سفارش…', '⏳ Preparing your order…' );
		self::pair( $r, 'msg.buy.preparing_invoice', 'messages', '⏳ در حال آماده‌سازی فاکتور…', '⏳ Preparing invoice…' );
	}

	/**
	 * Admin hub / settings messages (subset; keys used in handlers).
	 *
	 * @param array<int, array<string, string>> $r Rows.
	 */
	private static function append_msg_admin( array &$r ) {
		self::pair( $r, 'msg.admin.hub_menu', 'messages', "📋 منوی مدیریت\nاز دکمه‌های پایین صفحه استفاده کنید.", "📋 Admin menu\nUse the buttons below." );
		self::pair( $r, 'msg.admin.portal_link', 'messages', '🌐 لینک پورتال کاربر #{id}', '🌐 User portal link #{id}' );
		self::pair( $r, 'msg.admin.block_user', 'messages', '⛔ بلاک #{id}', '⛔ Block #{id}' );
		self::pair( $r, 'msg.admin.unblock_user', 'messages', '✅ آنبلاک #{id}', '✅ Unblock #{id}' );
		self::pair( $r, 'msg.admin.create_service', 'messages', '➕ ساخت سرویس برای #{id}', '➕ Create service for #{id}' );
		self::pair( $r, 'msg.admin.wallet_credit', 'messages', '💰 شارژ کیف پول', '💰 Credit wallet' );
		self::pair( $r, 'msg.admin.wallet_debit', 'messages', '📉 کاهش کیف پول', '📉 Debit wallet' );
		self::pair( $r, 'msg.admin.page_next', 'messages', '📄 صفحه بعد', '📄 Next page' );
		self::pair( $r, 'msg.admin.page_prev', 'messages', '◀ قبلی', '◀ Previous' );
		self::pair( $r, 'msg.admin.page_next_arrow', 'messages', '▶ بعدی', '▶ Next' );
		self::pair( $r, 'msg.admin.queue', 'messages', '📋 صف انتظار', '📋 Queue' );
		self::pair( $r, 'msg.admin.queue_back', 'messages', '↩ صف', '↩ Queue' );
		self::pair( $r, 'msg.admin.approved_tab', 'messages', '✅ تأییدشده', '✅ Approved' );
		self::pair( $r, 'msg.admin.rejected_tab', 'messages', '❌ ردشده', '❌ Rejected' );
		self::pair( $r, 'msg.admin.bulk_confirm_days', 'messages', '✅ تأیید +{n} روز', '✅ Confirm +{n} days' );
		self::pair( $r, 'msg.admin.bulk_confirm_gb', 'messages', '✅ تأیید +{n} GB', '✅ Confirm +{n} GB' );
		self::pair( $r, 'msg.admin.bulk_cancel', 'messages', '❌ لغو گروهی', '❌ Cancel bulk' );
		self::pair( $r, 'msg.admin.tutorial.bulk', 'messages', "➕ عملیات گروهی (Xray)\n⚠️ بار زیاد روی پنل؛ حداکثر ۲۰۰ سرویس در هر اجرا.\n➖\n۱) از «🔎 جستجوی کاربر» در منوی مدیریت کاربران یک کاربر را باز کنید.\n۲) دکمهٔ سریع → یک مرحلهٔ تأیید با دکمهٔ بعدی؛ یا «📝 تأیید متنی گروهی».", "➕ Bulk operations (Xray)\n⚠️ Heavy panel load; max 200 services per run.\n➖\n1) Open a user from user search.\n2) Quick button → confirm step, or text bulk confirm." );
		self::pair( $r, 'msg.admin.bulk_confirm_days_prompt', 'messages', '⚠️ تأیید عملیات گروهی\n➖\nافزودن «{days}» روز به سرویس‌های Xray (حداکثر ۲۰۰ سرویس).\nادامه؟', '⚠️ Confirm bulk\n➖\nAdd {days} days to Xray services (max 200).\nContinue?' );
		self::pair( $r, 'msg.admin.bulk_confirm_gb_prompt', 'messages', '⚠️ تأیید عملیات گروهی\n➖\nافزودن «{gb}» گیگ به هر سرویس Xray (حداکثر ۲۰۰ سرویس).\nادامه؟', '⚠️ Confirm bulk\n➖\nAdd {gb} GB to each Xray service (max 200).\nContinue?' );
		self::pair( $r, 'msg.admin.backup.header', 'messages', '💾 بکاپ و ریستور\n➖➖➖➖', '💾 Backup & restore\n➖➖➖➖' );
		self::pair( $r, 'msg.admin.backup.interval', 'messages', '⏱ فاصله: {minutes} دقیقه', '⏱ Interval: {minutes} min' );
		self::pair( $r, 'msg.admin.backup.tg_chat', 'messages', '📢 TG chat id: {id}', '📢 TG chat id: {id}' );
		self::pair( $r, 'msg.admin.backup.bale_chat', 'messages', '💬 Bale chat id: {id}', '💬 Bale chat id: {id}' );
		self::pair( $r, 'msg.admin.backup.targets', 'messages', 'ارسال: TG ادمین {tg_admin} · Bale ادمین {bale_admin} · TG کانال {tg_channel} · Bale کانال {bale_channel}', 'Send: TG admin {tg_admin} · Bale admin {bale_admin} · TG channel {tg_channel} · Bale channel {bale_channel}' );
		self::pair( $r, 'msg.admin.backup.last_sent', 'messages', 'آخرین ارسال موفق: {ts}', 'Last successful send: {ts}' );
		self::pair( $r, 'msg.admin.backup.last_built', 'messages', 'آخرین ساخت زیپ: {ts}', 'Last zip build: {ts}' );
		self::pair( $r, 'msg.admin.backup.footer', 'messages', '➖\nدکمه‌ها: بکاپ الان، تیک‌ها، ویرایش مقدار، ریستور (۲ مرحله).', '➖\nButtons: backup now, toggles, edit values, restore (2 steps).' );
		self::pair( $r, 'msg.admin.logs.header', 'messages', '📜 لاگ ({from}–{to})', '📜 Logs ({from}–{to})' );
		self::pair( $r, 'msg.admin.logs.empty', 'messages', 'رکوردی نیست.', 'No records.' );
		self::pair( $r, 'btn.admin.logs_prev', 'buttons', '◀ لاگ قبلی', '◀ Logs prev' );
		self::pair( $r, 'btn.admin.logs_next', 'buttons', 'لاگ بعدی ▶', 'Logs next ▶' );
		self::pair( $r, 'msg.admin.pay_wallet', 'messages', '💳 کیف پول', '💳 Wallet' );
		self::pair( $r, 'msg.admin.pay_free', 'messages', '🎁 رایگان', '🎁 Free' );
		self::pair( $r, 'msg.admin.pay_invoice', 'messages', '🧾 فاکتور', '🧾 Invoice' );
		self::pair( $r, 'msg.admin.pick_user', 'messages', '👤 pick {id}', '👤 pick {id}' );
		self::pair( $r, 'msg.admin.text_saved', 'messages', '✅ متن «{key}» ذخیره شد.', '✅ Text «{key}» saved.' );
		self::pair( $r, 'msg.admin.content_invalid', 'messages', '⛔ محتوا نامعتبر.', '⛔ Invalid content.' );
		self::pair( $r, 'msg.admin.cancel_hint', 'messages', 'برای لغو /cancel بفرستید.', 'Send /cancel to abort.' );
		self::pair( $r, 'btn.admin.catalog.new_category', 'buttons', '➕ دسته جدید', '➕ New category' );
		self::pair( $r, 'btn.admin.catalog.delete_category', 'buttons', '🗑 دسته', '🗑 Category' );
		self::pair( $r, 'btn.admin.catalog.new_plan', 'buttons', '➕ پلن جدید (Xray)', '➕ New plan (Xray)' );
		self::pair( $r, 'btn.admin.catalog.new_card', 'buttons', '➕ کارت جدید', '➕ New card' );
		self::pair( $r, 'btn.admin.catalog.new_l2tp', 'buttons', '➕ سرور جدید (خطی)', '➕ New L2TP server' );
		self::pair( $r, 'btn.admin.text_prev', 'buttons', '◀ متن قبلی', '◀ Previous text' );
		self::pair( $r, 'btn.admin.text_next', 'buttons', 'متن بعدی ▶', 'Next text ▶' );
		self::pair( $r, 'btn.admin.text_reset_all', 'buttons', '🔄 همه به پیش‌فرض', '🔄 Reset all to default' );
		self::pair( $r, 'btn.admin.backup.now', 'buttons', '▶️ بکاپ الان', '▶️ Backup now' );
		self::pair( $r, 'btn.admin.backup.tg_ad', 'buttons', 'TG ad', 'TG admins' );
		self::pair( $r, 'btn.admin.backup.bl_ad', 'buttons', 'Bl ad', 'Bale admins' );
		self::pair( $r, 'btn.admin.backup.tg_ch', 'buttons', 'TG ch', 'TG channel' );
		self::pair( $r, 'btn.admin.backup.bl_ch', 'buttons', 'Bl ch', 'Bale channel' );
		self::pair( $r, 'btn.admin.backup.interval', 'buttons', '⏱ فاصله (دقیقه)', '⏱ Interval (min)' );
		self::pair( $r, 'btn.admin.backup.tg_ch_id', 'buttons', '📢 TG ch id', '📢 TG channel id' );
		self::pair( $r, 'btn.admin.backup.bl_ch_id', 'buttons', '💬 Bale ch id', '💬 Bale channel id' );
		self::pair( $r, 'btn.admin.backup.restore', 'buttons', '📥 ریستور (۲ مرحله)', '📥 Restore (2-step)' );
		self::pair( $r, 'btn.admin.backup.cancel_mode', 'buttons', '❌ لغو حالت', '❌ Cancel mode' );
		self::pair( $r, 'msg.admin.caption.user', 'messages', '👤 کاربر:', '👤 User:' );
		self::pair( $r, 'msg.admin.caption.username', 'messages', 'یوزرنیم:', 'Username:' );
		self::pair( $r, 'msg.admin.caption.telegram', 'messages', 'تلگرام:', 'Telegram:' );
		self::pair( $r, 'msg.admin.caption.bale', 'messages', 'بله:', 'Bale:' );
		self::pair( $r, 'msg.admin.caption.bot', 'messages', 'ربات:', 'Bot:' );
		self::pair( $r, 'msg.admin.receipt_new', 'messages', '🧾 رسید جدید', '🧾 New receipt' );
		self::pair( $r, 'msg.admin.signup_request', 'messages', '🔔 درخواست ثبت‌نام', '🔔 Signup request' );
		self::pair( $r, 'msg.admin.confirm_question', 'messages', 'آیا تایید می‌کنید؟', 'Do you approve?' );
	}

	/**
	 * Bot admin panel (5 sections) — labels, intros, tutorials.
	 *
	 * @param array<int, array<string, string>> $r Rows.
	 */
	private static function append_msg_admin_panel( array &$r ) {
		self::pair( $r, 'btn.admin.section.users', 'buttons', 'کاربران', 'Users' );
		self::pair( $r, 'btn.admin.section.resellers', 'buttons', 'نمایندگان', 'Resellers' );
		self::pair( $r, 'btn.admin.section.marketing', 'buttons', 'بازاریابی', 'Marketing' );
		self::pair( $r, 'btn.admin.section.finance', 'buttons', 'مالی', 'Finance' );
		self::pair( $r, 'btn.admin.section.settings', 'buttons', 'تنظیمات', 'Settings' );
		self::pair( $r, 'btn.admin.back_panel', 'buttons', '⬅️ پنل مدیریت', '⬅️ Admin panel' );
		self::pair( $r, 'btn.admin.back_section', 'buttons', '⬅️ بازگشت به بخش', '⬅️ Back to section' );
		self::pair( $r, 'btn.admin.tab.plans', 'buttons', '📦 پلن‌ها', '📦 Plans' );
		self::pair( $r, 'btn.admin.tab.plan_cats', 'buttons', '🗂 دسته‌های خرید', '🗂 Plan categories' );
		self::pair( $r, 'btn.admin.tab.cards', 'buttons', '💳 کارت‌ها', '💳 Cards' );
		self::pair( $r, 'btn.admin.tab.referral', 'buttons', '🔗 ریفرال', '🔗 Referral' );
		self::pair( $r, 'btn.admin.tab.marketing_lifecycle', 'buttons', '🔁 بازگشت مشتری', '🔁 Lifecycle' );
		self::pair( $r, 'btn.admin.tab.discounts', 'buttons', '🏷 کدهای تخفیف', '🏷 Discounts' );
		self::pair( $r, 'btn.admin.tab.resellers', 'buttons', '🏪 نمایندگان', '🏪 Resellers' );
		self::pair( $r, 'btn.admin.tab.reseller_reports', 'buttons', '📊 گزارش نمایندگان', '📊 Reseller reports' );
		self::pair( $r, 'btn.admin.tab.reseller_bots', 'buttons', '🤖 ربات نماینده', '🤖 Reseller bots' );
		self::pair( $r, 'btn.admin.tab.reseller_xui_panels', 'buttons', '🖥 پنل XUI نمایندگان', '🖥 Reseller XUI' );
		self::pair( $r, 'btn.admin.tab.referral_reports', 'buttons', '📈 گزارش رفرال', '📈 Referral reports' );
		self::pair( $r, 'btn.admin.tab.reseller_charge', 'buttons', '💰 شارژ حساب', '💰 Account charge' );
		self::pair( $r, 'btn.admin.tab.unit_economics', 'buttons', '📐 اقتصاد واحد', '📐 Unit economics' );
		self::pair( $r, 'btn.admin.tab.monitoring', 'buttons', '📡 مانیتورینگ', '📡 Monitoring' );
		self::pair( $r, 'btn.admin.tab.bot_ui', 'buttons', '🎨 صفحه‌ساز', '🎨 Bot UI' );
		self::pair( $r, 'btn.admin.tab.site_settings', 'buttons', '⚙️ تنظیمات سایت', '⚙️ Site settings' );
		self::pair( $r, 'btn.admin.tab.notifications', 'buttons', '🔔 اعلان‌ها', '🔔 Notifications' );
		self::pair( $r, 'btn.admin.tab.audit', 'buttons', '📋 ممیزی', '📋 Audit' );
		self::pair( $r, 'btn.admin.tab.logs', 'buttons', '📜 لاگ‌ها', '📜 Logs' );
		self::pair( $r, 'btn.admin.tab.reseller_settings', 'buttons', '⚙️ تنظیمات نماینده', '⚙️ Reseller settings' );
		self::pair( $r, 'btn.admin.discount_new', 'buttons', '➕ کد تخفیف جدید', '➕ New discount' );
		self::pair( $r, 'btn.admin.discount_edit', 'buttons', '✏️ ویرایش درصد', '✏️ Edit percent' );
		self::pair( $r, 'btn.admin.discount_toggle', 'buttons', '🔄 فعال/غیرفعال کد', '🔄 Toggle code' );
		self::pair( $r, 'btn.admin.discount_delete', 'buttons', '🗑 حذف کد تخفیف', '🗑 Delete discount' );
		self::pair( $r, 'btn.admin.lifecycle_toggle', 'buttons', '🔄 فعال/غیرفعال قانون', '🔄 Toggle rule' );
		self::pair( $r, 'btn.admin.lifecycle_new', 'buttons', '➕ قانون جدید', '➕ New rule' );
		self::pair( $r, 'btn.admin.lifecycle_edit', 'buttons', '✏️ ویرایش قانون', '✏️ Edit rule' );
		self::pair( $r, 'btn.admin.lifecycle_delete', 'buttons', '🗑 حذف قانون', '🗑 Delete rule' );
		self::pair( $r, 'btn.admin.lifecycle_run', 'buttons', '▶️ اجرای فوری', '▶️ Run now' );
		self::pair( $r, 'btn.admin.xui_panels_prev', 'buttons', '◀ قبلی', '◀ Prev' );
		self::pair( $r, 'btn.admin.xui_panels_next', 'buttons', 'بعدی ▶', 'Next ▶' );
		self::pair( $r, 'btn.admin.xui_panel_assign', 'buttons', '➕ تخصیص پنل', '➕ Assign panel' );
		self::pair( $r, 'btn.admin.catalog_prev', 'buttons', '◀ قبلی', '◀ Prev' );
		self::pair( $r, 'btn.admin.catalog_next', 'buttons', 'بعدی ▶', 'Next ▶' );
		self::pair( $r, 'btn.admin.reseller_topup', 'buttons', '💳 شارژ حساب', '💳 Top up wallet' );
		self::pair( $r, 'btn.admin.charges_filter_all', 'buttons', '📋 همه', '📋 All' );
		self::pair( $r, 'btn.admin.charges_filter_purchase', 'buttons', '🛒 خرید', '🛒 Purchase' );
		self::pair( $r, 'btn.admin.charges_filter_renew', 'buttons', '♻️ تمدید', '♻️ Renew' );
		self::pair( $r, 'btn.admin.charges_filter_topup', 'buttons', '💰 شارژ', '💰 Top-up' );
		self::pair( $r, 'btn.admin.referral_toggle', 'buttons', '🔄 فعال/غیرفعال ریفرال', '🔄 Toggle referral' );
		self::pair( $r, 'btn.admin.referral_percent', 'buttons', '📊 درصد ریفرال', '📊 Referral percent' );

		self::pair(
			$r,
			'msg.admin.panel_welcome',
			'messages',
			"🎛 پنل مدیریت\n➖➖➖➖➖➖➖➖\nبه پنل ادمین خوش آمدید.\nاز دکمه‌های پایین یک بخش را انتخاب کنید.\n➖\n↩️ بازگشت به منوی کاربر: /start\n🎛 بازگشت به پنل مدیریت: /panel\n➖\n• کاربران — جستجو، صف عضویت، عملیات گروهی\n• نمایندگان — لیست و گزارش\n• بازاریابی — ریفرال، بازگشت مشتری، تخفیف\n• مالی — پلن، کارت، رسید\n• تنظیمات — سرورها و تنظیمات سیستم",
			"🎛 Admin panel\n➖➖➖➖➖➖➖➖\nWelcome.\nUser menu: /start · Admin panel: /panel"
		);
		self::pair( $r, 'msg.admin.panel_denied', 'messages', '⛔ دسترسی به پنل مدیریت برای شما فعال نیست.', '⛔ You do not have access to the admin panel.' );
		self::pair( $r, 'msg.admin.panel.role_reseller', 'messages', '👤 نقش: نماینده — فقط دسترسی‌های مجاز شما فعال است.', '👤 Role: Reseller — only your allowed permissions are enabled.' );
		self::pair( $r, 'msg.admin.panel.role_site_admin', 'messages', '👤 نقش: مدیر سایت — دسترسی کامل (به‌جز بخش‌های مخصوص نماینده).', '👤 Role: Site admin — full access except reseller-only areas.' );
		self::pair( $r, 'msg.admin.denied_permission', 'messages', '⛔ این عملیات برای سطح دسترسی شما مجاز نیست.', '⛔ This action is not allowed for your permission level.' );
		self::pair( $r, 'msg.admin.denied_tab', 'messages', '⛔ این بخش برای شما نمایش داده نمی‌شود.', '⛔ This section is not available for you.' );

		self::pair( $r, 'msg.admin.section.users.intro', 'messages', "👥 بخش کاربران\n➖➖➖➖➖➖➖➖\n۱. «کاربران» — جستجو با شناسه یا @username\n۲. «عملیات گروهی» — تمدید/حجم گروهی (حداکثر ۲۰۰ سرویس)\n۳. «پیام همگانی» — ارسال به کاربران مجاز (نماینده: فقط downline)\n➖\n⚠️ نماینده فقط زیرمجموعه خود را می‌بیند.\n↩️ /start · 🎛 /panel", "👥 Users section\n1. Search 2. Bulk 3. Broadcast\n/start · /panel" );
		self::pair( $r, 'msg.admin.section.resellers.intro', 'messages', "🏪 بخش نمایندگان\n➖➖➖➖➖➖➖➖\n۱. لیست نمایندگان و موجودی\n۲. گزارش فروش ۳۰ روز\n۳. وضعیت ربات سفیدبرچسب\n۴. پنل XUI نمایندگان (فقط مدیر سایت)\n➖\n↩️ /start · 🎛 /panel", "🏪 Resellers section.\n/start · /panel" );
		self::pair( $r, 'msg.admin.section.marketing.intro', 'messages', "📣 بخش بازاریابی\n➖➖➖➖➖➖➖➖\n۱. ریفرال — لینک دعوت و درصد\n۲. بازگشت مشتری — قوانین lifecycle\n۳. کدهای تخفیف — ساخت، ویرایش، فعال/غیرفعال\n➖\n↩️ /start · 🎛 /panel", "📣 Marketing section.\n/start · /panel" );
		self::pair( $r, 'msg.admin.section.finance.intro', 'messages', "💰 بخش مالی\n➖➖➖➖➖➖➖➖\n۱. پلن‌ها و دسته‌های خرید\n۲. کارت‌های بانکی\n۳. رسیدهای pending\n۴. گزارش رفرال و شارژ نماینده\n➖\n↩️ /start · 🎛 /panel", "💰 Finance section.\n/start · /panel" );
		self::pair( $r, 'msg.admin.section.settings.intro', 'messages', "⚙️ بخش تنظیمات\n➖➖➖➖➖➖➖➖\n۱. مانیتورینگ و پنل 3x-ui\n۲. کانفیگ، بکاپ، متن‌ها\n۳. اعلان‌ها و ممیزی (مدیر سایت)\n➖\nصفحه‌ساز ربات فقط از پنل وب.\n↩️ /start · 🎛 /panel", "⚙️ Settings section.\n/start · /panel" );

		self::pair( $r, 'msg.admin.tutorial.users', 'messages', "👤 کاربران\n➖\n۱. «جستجوی کاربر» — شناسه یا @username\n۲. «صف ثبت‌نام» — تأیید/رد عضویت\n۳. از کارت کاربر: بلاک، سرویس، کیف پول\n↩️ /start · 🎛 /panel", "👤 Users: search, signup queue, user card." );
		self::pair( $r, 'msg.admin.tutorial.plans', 'messages', "📦 پلن‌ها\n➖\nمدیریت پلن‌های فروش: قیمت، حجم، مدت.\nاز دکمه‌های زیرمنو پلن جدید یا ویرایش کنید.", "📦 Plans tutorial." );
		self::pair( $r, 'msg.admin.tutorial.cards', 'messages', "💳 کارت‌ها\n➖\nکارت‌های بانکی برای پرداخت دستی.\nافزودن/حذف از همین بخش.", "💳 Cards tutorial." );
		self::pair( $r, 'msg.admin.tutorial.plan_cats', 'messages', "🗂 دسته‌های خرید\n➖\nدسته‌بندی پلن‌ها در منوی خرید کاربر.", "🗂 Plan categories." );
		self::pair( $r, 'msg.admin.tutorial.site_settings', 'messages', "⚙️ تنظیمات سایت\n➖\nتنظیمات عمومی افزونه (فقط مدیر سایت).", "⚙️ Site settings." );
		self::pair( $r, 'msg.admin.tutorial.backup', 'messages', "💾 بکاپ\n➖\nپشتیبان‌گیری و بازیابی دیتابیس.", "💾 Backup." );
		self::pair( $r, 'msg.admin.tutorial.bots', 'messages', "🤖 ربات‌ها\n➖\nتنظیم توکن و webhook ربات اصلی.", "🤖 Bots." );
		self::pair( $r, 'msg.admin.tutorial.xui_panels', 'messages', "🖥 پنل XUI\n➖\nاتصال به پنل 3x-ui و اینباندها.", "🖥 XUI panels." );
		self::pair( $r, 'msg.admin.tutorial.configs', 'messages', "📋 کانفیگ‌ها\n➖\nهمگام‌سازی و انتشار کانفیگ.", "📋 Configs." );
		self::pair( $r, 'msg.admin.tutorial.l2tp_servers', 'messages', "🔐 سرور L2TP\n➖\nمدیریت سرورهای L2TP.", "🔐 L2TP servers." );
		self::pair( $r, 'msg.admin.tutorial.notifications', 'messages', "🔔 اعلان‌ها\n➖\nتنظیم کانال/چت اعلان‌های سیستم.", "🔔 Notifications." );
		self::pair( $r, 'msg.admin.tutorial.receipts', 'messages', "🧾 رسیدها\n➖\nرسیدهای pending با عکس ارسال می‌شوند.\nتأیید = فعال‌سازی خرید · رد = اطلاع به کاربر", "🧾 Receipts tutorial." );
		self::pair( $r, 'msg.admin.tutorial.referral', 'messages', "🔗 ریفرال\n➖\nوضعیت و درصد ریفرال + لینک دعوت.\nمدیر سایت: دکمه‌های فعال/غیرفعال و درصد.", "🔗 Referral tutorial." );
		self::pair( $r, 'msg.admin.tutorial.marketing_lifecycle', 'messages', "🔁 بازگشت مشتری\n➖\nلیست قوانین فعال/غیرفعال.\nمدیر سایت: قانون جدید، ویرایش، حذف، اجرای فوری.\n«فعال/غیرفعال قانون» → شماره قانون (#id).", "🔁 Lifecycle rules.\nSite admin: new, edit, delete, run now." );
		self::pair( $r, 'msg.admin.tutorial.discounts', 'messages', "🏷 تخفیف\n➖\n«کد جدید» → نوع → مقدار → سقف استفاده → تاریخ انقضا → پلن‌ها.\n«ویرایش» / «فعال/غیرفعال» / «حذف» → نام کد.", "🏷 Discounts: full wizard via dashboard validation." );
		self::pair( $r, 'msg.admin.tutorial.bot_ui_web_only', 'messages', "🎨 صفحه‌ساز ربات\n➖\nویرایش چیدمان دکمه‌ها در پنل وب (لینک زیر).", "🎨 Bot UI is web-only." );
		self::pair( $r, 'msg.admin.tutorial.reseller_settings', 'messages', "⚙️ تنظیمات نماینده\n➖\nخلاصه دسترسی‌ها و پروفایل ربات.", "⚙️ Reseller settings." );
		self::pair( $r, 'msg.admin.tutorial.reseller_reports', 'messages', "📊 گزارش نمایندگان\n➖\nخلاصه ۳۰ روز: فروش، عمده، حاشیه، کاربران.", "📊 Reseller reports." );
		self::pair( $r, 'msg.admin.tutorial.reseller_bots', 'messages', "🤖 ربات نماینده\n➖\nوضعیت webhook و username.", "🤖 Reseller bot." );
		self::pair( $r, 'msg.admin.tutorial.reseller_xui_panels', 'messages', "🖥 پنل XUI نمایندگان\n➖\nلیست پنل‌ها با تعداد نماینده دارای دسترسی.\nفقط مدیر سایت.", "🖥 Reseller XUI panels list (site admin)." );
		self::pair( $r, 'msg.admin.tutorial.reseller_charge', 'messages', "💰 شارژ حساب\n➖\nموجودی فعلی: {balance} تومان\n«شارژ حساب» → مبلغ → checkout.\nفیلتر تراکنش‌های مشتریان از دکمه‌های زیر.", "💰 Balance: {balance}. Top-up + charge filters." );
		self::pair( $r, 'msg.admin.tutorial.unit_economics', 'messages', "📐 اقتصاد واحد\n➖\nخلاصه سودآوری پنل‌ها.", "📐 Unit economics." );
		self::pair( $r, 'msg.admin.report.sales_toman', 'messages', 'فروش (تومان)', 'Sales (Toman)' );
		self::pair( $r, 'msg.admin.report.wholesale_toman', 'messages', 'عمده (تومان)', 'Wholesale (Toman)' );
		self::pair( $r, 'msg.admin.report.margin_est', 'messages', 'حاشیه تخمینی', 'Est. margin' );
		self::pair( $r, 'msg.admin.report.downline_users', 'messages', 'کاربران زیرمجموعه', 'Downline users' );
		self::pair( $r, 'msg.admin.tutorial.audit', 'messages', "📋 ممیزی\n➖\nآخرین رویدادهای امنیتی/ادمین:", "📋 Audit log." );

		self::pair( $r, 'msg.admin.monitoring_summary', 'messages', "📡 مانیتورینگ\nفعال: {active} · منقضی: {expired}", "📡 Active: {active} · Expired: {expired}" );
		self::pair( $r, 'msg.admin.referral_reports_summary', 'messages', "📈 رفرال ۳۰ روز\nرویداد: {count} · پورسانت: {commission} تومان", "📈 Referral 30d: {count} events" );
		self::pair( $r, 'msg.admin.resellers_list_header', 'messages', "🏪 نمایندگان ({total})", "🏪 Resellers ({total})" );
		self::pair( $r, 'msg.admin.resellers_empty', 'messages', 'لیست خالی است.', 'List is empty.' );
		self::pair( $r, 'msg.admin.marketing_rules_empty', 'messages', 'قانونی ثبت نشده.', 'No rules yet.' );
		self::pair( $r, 'msg.admin.discounts_empty', 'messages', 'کد تخفیفی نیست — «کد جدید» بزنید.', 'No discount codes.' );
		self::pair( $r, 'msg.admin.prompt_discount_code', 'messages', '✋ کد تخفیف را بفرستید (حروف/عدد):', '✋ Send discount code:' );
		self::pair( $r, 'msg.admin.prompt_discount_value', 'messages', '✋ درصد تخفیف (۱–۱۰۰):', '✋ Discount percent:' );
		self::pair( $r, 'msg.admin.discount_code_invalid', 'messages', '⛔ کد کوتاه است.', '⛔ Code too short.' );
		self::pair( $r, 'msg.admin.discount_value_invalid', 'messages', '⛔ درصد نامعتبر.', '⛔ Invalid percent.' );
		self::pair( $r, 'msg.admin.discount_created', 'messages', '✅ کد «{code}» ساخته شد.', '✅ Code «{code}» created.' );
		self::pair( $r, 'msg.admin.prompt_discount_delete', 'messages', '✋ کد تخفیف برای حذف:', '✋ Discount code to delete:' );
		self::pair( $r, 'msg.admin.prompt_discount_toggle', 'messages', '✋ کد تخفیف برای فعال/غیرفعال:', '✋ Discount code to toggle:' );
		self::pair( $r, 'msg.admin.prompt_discount_edit_code', 'messages', '✋ کد تخفیف برای ویرایش درصد:', '✋ Discount code to edit:' );
		self::pair( $r, 'msg.admin.discount_not_found', 'messages', '⛔ کد یافت نشد.', '⛔ Code not found.' );
		self::pair( $r, 'msg.admin.discount_deleted', 'messages', '✅ کد «{code}» حذف شد.', '✅ Code «{code}» deleted.' );
		self::pair( $r, 'msg.admin.discount_toggled', 'messages', '✅ کد «{code}» → {state}', '✅ Code «{code}» → {state}' );
		self::pair( $r, 'msg.admin.discount_updated', 'messages', '✅ کد «{code}» → {percent}%', '✅ Code «{code}» → {percent}%' );
		self::pair( $r, 'msg.admin.reseller_xui_panels_header', 'messages', "🖥 پنل XUI نمایندگان ({total})\nنمایش {offset}–{end}", "🖥 Reseller XUI panels ({total})\nShowing {offset}–{end}" );
		self::pair( $r, 'msg.admin.reseller_xui_panels_empty', 'messages', 'پنلی ثبت نشده.', 'No panels registered.' );
		self::pair( $r, 'msg.admin.reseller_xui_panel_resellers', 'messages', 'نمایندگان: {count}', 'Resellers: {count}' );
		self::pair( $r, 'msg.admin.prompt_lifecycle_rule_id', 'messages', '✋ شماره قانون (#id) را بفرستید:', '✋ Send rule id (#id):' );
		self::pair( $r, 'msg.admin.lifecycle_rule_invalid', 'messages', '⛔ شماره قانون نامعتبر.', '⛔ Invalid rule id.' );
		self::pair( $r, 'msg.admin.lifecycle_rule_not_found', 'messages', '⛔ قانون یافت نشد.', '⛔ Rule not found.' );
		self::pair( $r, 'msg.admin.lifecycle_toggled', 'messages', '✅ قانون #{id} → {state}', '✅ Rule #{id} → {state}' );
		self::pair( $r, 'msg.admin.prompt_referral_percent', 'messages', '✋ درصد ریفرال (۰–۱۰۰):', '✋ Referral percent (0–100):' );
		self::pair( $r, 'msg.admin.referral_toggled', 'messages', '✅ ریفرال: {state}', '✅ Referral: {state}' );
		self::pair( $r, 'msg.admin.referral_percent_saved', 'messages', '✅ درصد ریفرال: {percent}%', '✅ Referral percent: {percent}%' );

		self::pair( $r, 'msg.admin.mutate_ok', 'messages', '✅ انجام شد.', '✅ Done.' );
		self::pair( $r, 'msg.admin.mutate.plan_overlap', 'messages', '⛔ تداخل کد فعال روی همان پلن.', '⛔ Active code overlaps on same plan(s).' );
		self::pair( $r, 'msg.admin.mutate.empty_code', 'messages', '⛔ کد خالی است.', '⛔ Empty code.' );
		self::pair( $r, 'msg.admin.mutate.not_found', 'messages', '⛔ یافت نشد.', '⛔ Not found.' );
		self::pair( $r, 'msg.admin.prompt_discount_type', 'messages', '✋ نوع: percent یا fixed', '✋ Type: percent or fixed' );
		self::pair( $r, 'msg.admin.discount_type_invalid', 'messages', '⛔ نوع نامعتبر (percent/fixed).', '⛔ Invalid type (percent/fixed).' );
		self::pair( $r, 'msg.admin.prompt_discount_max_uses', 'messages', '✋ حداکثر استفاده (۰ یا - = نامحدود):', '✋ Max uses (0 or - = unlimited):' );
		self::pair( $r, 'msg.admin.prompt_discount_valid_until', 'messages', '✋ تاریخ انقضا YYYY-MM-DD یا - :', '✋ Expiry YYYY-MM-DD or -:' );
		self::pair( $r, 'msg.admin.prompt_discount_plan_ids', 'messages', '✋ شناسه پلن‌ها با کاما یا - برای همه:', '✋ Plan ids comma-separated or - for all:' );
		self::pair( $r, 'msg.admin.prompt_discount_allow_flags', 'messages', '✋ مجوزها: new,renew,vol,users (با کاما؛ - = همه فعال):', '✋ Allow flags: new,renew,vol,users (comma; - = all on):' );
		self::pair( $r, 'msg.admin.prompt_discount_min_max', 'messages', '✋ حداقل/حداکثر سفارش (min,max یا -):', '✋ Min/max order (min,max or -):' );
		self::pair( $r, 'msg.admin.discount_allow_invalid', 'messages', '⛔ مجوز نامعتبر. مثال: new,renew', '⛔ Invalid allow flags. Example: new,renew' );
		self::pair( $r, 'msg.admin.discount_min_max_invalid', 'messages', '⛔ حداقل/حداکثر نامعتبر.', '⛔ Invalid min/max order.' );
		self::pair( $r, 'msg.admin.discount_date_invalid', 'messages', '⛔ تاریخ نامعتبر (YYYY-MM-DD).', '⛔ Invalid date (YYYY-MM-DD).' );
		self::pair( $r, 'msg.admin.prompt_lifecycle_segment', 'messages', "✋ segment_key:\nchurned · never_purchased · abandoned_checkout · stale_buy_funnel · expiring_renew", "✋ segment_key:\nchurned · never_purchased · abandoned_checkout · stale_buy_funnel · expiring_renew" );
		self::pair( $r, 'msg.admin.lifecycle_segment_invalid', 'messages', '⛔ segment نامعتبر.', '⛔ Invalid segment.' );
		self::pair( $r, 'msg.admin.prompt_lifecycle_seg_param', 'messages', '✋ مقدار {field}:', '✋ Value for {field}:' );
		self::pair( $r, 'msg.admin.prompt_lifecycle_cooldown', 'messages', '✋ cooldown_days (حداقل ۱):', '✋ cooldown_days (min 1):' );
		self::pair( $r, 'msg.admin.prompt_lifecycle_discount', 'messages', '✋ درصد تخفیف (۰–۱۰۰):', '✋ Discount percent (0–100):' );
		self::pair( $r, 'msg.admin.prompt_lifecycle_message', 'messages', '✋ متن پیام (- برای پیش‌فرض):', '✋ Message body (- for default):' );
		self::pair( $r, 'msg.admin.prompt_lifecycle_delete', 'messages', '✋ شماره قانون برای حذف:', '✋ Rule id to delete:' );
		self::pair( $r, 'msg.admin.prompt_lifecycle_run', 'messages', '✋ شماره قانون برای اجرای فوری:', '✋ Rule id to run now:' );
		self::pair( $r, 'msg.admin.lifecycle_rule_line', 'messages', '• #{id} {segment} — {enabled} · cd:{cooldown} · after:{after}', '• #{id} {segment} — {enabled} · cd:{cooldown} · after:{after}' );
		self::pair( $r, 'msg.admin.lifecycle_created', 'messages', '✅ قانون #{id} ساخته شد.', '✅ Rule #{id} created.' );
		self::pair( $r, 'msg.admin.lifecycle_updated', 'messages', '✅ قانون #{id} به‌روز شد.', '✅ Rule #{id} updated.' );
		self::pair( $r, 'msg.admin.lifecycle_deleted', 'messages', '✅ قانون #{id} حذف شد.', '✅ Rule #{id} deleted.' );
		self::pair( $r, 'msg.admin.lifecycle_run_ok', 'messages', '✅ قانون #{id} اجرا شد · ارسال: {sent}', '✅ Rule #{id} ran · sent: {sent}' );
		self::pair( $r, 'msg.admin.prompt_xui_assign_reseller', 'messages', '✋ شناسه نماینده (svp_users.id):', '✋ Reseller id (svp_users.id):' );
		self::pair( $r, 'msg.admin.prompt_xui_assign_panel', 'messages', '✋ شناسه پنل (panel_id):', '✋ Panel id:' );
		self::pair( $r, 'msg.admin.prompt_xui_assign_price', 'messages', '✋ price_per_gb (تومان):', '✋ price_per_gb (Toman):' );
		self::pair( $r, 'msg.admin.reseller_id_invalid', 'messages', '⛔ شناسه نماینده نامعتبر.', '⛔ Invalid reseller id.' );
		self::pair( $r, 'msg.admin.reseller_not_found', 'messages', '⛔ نماینده یافت نشد.', '⛔ Reseller not found.' );
		self::pair( $r, 'msg.admin.panel_id_invalid', 'messages', '⛔ پنل نامعتبر.', '⛔ Invalid panel.' );
		self::pair( $r, 'msg.admin.prompt_reseller_topup', 'messages', '✋ مبلغ شارژ (تومان):', '✋ Top-up amount (Toman):' );
		self::pair( $r, 'msg.admin.reseller_topup_invalid', 'messages', '⛔ مبلغ نامعتبر.', '⛔ Invalid amount.' );
		self::pair( $r, 'msg.admin.reseller_topup_sent', 'messages', '💳 لینک/دستور پرداخت ارسال شد.', '💳 Payment link/instructions sent.' );
		self::pair( $r, 'msg.admin.reseller_charges_header', 'messages', '📋 تراکنش‌های مشتریان:', '📋 Customer charges:' );
		self::pair( $r, 'msg.admin.catalog.plans_header', 'messages', '📦 پلن‌ها', '📦 Plans' );
		self::pair( $r, 'msg.admin.catalog.cards_header', 'messages', '💳 کارت‌ها', '💳 Cards' );
		self::pair( $r, 'msg.admin.catalog.plan_cats_header', 'messages', '🗂 دسته‌های خرید', '🗂 Plan categories' );
		self::pair( $r, 'msg.admin.catalog.empty', 'messages', 'لیست خالی است.', 'List is empty.' );
		self::pair( $r, 'msg.admin.catalog.delete_confirm', 'messages', '🗑 حذف #{id}؟', '🗑 Delete #{id}?' );
		self::pair( $r, 'msg.admin.catalog.actions_hint', 'messages', '⬇️ دکمه‌های زیر', '⬇️ Actions below' );
		self::pair( $r, 'msg.admin.discount_plan_pick_header', 'messages', '📦 انتخاب پلن', '📦 Pick plan' );
		self::pair( $r, 'msg.admin.prompt_economics_volume_gb', 'messages', '✋ حجم فروش GB:', '✋ Sold volume GB:' );
		self::pair( $r, 'msg.admin.prompt_economics_price_gb', 'messages', '✋ قیمت هر GB (تومان):', '✋ Price per GB (Toman):' );
		self::pair( $r, 'msg.admin.prompt_economics_panel_id', 'messages', '✋ شناسه پنل (panel_id):', '✋ Panel id:' );
		self::pair( $r, 'msg.admin.prompt_economics_line_label', 'messages', '✋ عنوان هزینه:', '✋ Cost label:' );
		self::pair( $r, 'msg.admin.prompt_economics_line_category', 'messages', '✋ دسته (external_server/internal_server/cdn/outbound/support):', '✋ Category (external_server/internal_server/cdn/outbound/support):' );
		self::pair( $r, 'msg.admin.prompt_economics_line_cost', 'messages', '✋ مبلغ هزینه (تومان):', '✋ Cost amount (Toman):' );
		self::pair( $r, 'msg.admin.prompt_economics_line_cycle', 'messages', '✋ دوره (monthly/daily/hourly/per_gb):', '✋ Billing cycle (monthly/daily/hourly/per_gb):' );
		self::pair( $r, 'msg.admin.prompt_economics_line_id', 'messages', '✋ شناسه خط هزینه (line_id):', '✋ Cost line id:' );
		self::pair( $r, 'msg.admin.prompt_economics_volume_mode', 'messages', '✋ volume_mode: manual یا auto_sales', '✋ volume_mode: manual or auto_sales' );
		self::pair( $r, 'msg.admin.prompt_economics_volume_window', 'messages', '✋ volume_window_days (۱–۳۶۵):', '✋ volume_window_days (1–365):' );
		self::pair( $r, 'msg.admin.economics_volume_mode_invalid', 'messages', '⛔ volume_mode نامعتبر (manual/auto_sales).', '⛔ Invalid volume_mode (manual/auto_sales).' );
		self::pair( $r, 'btn.admin.economics_delete_line', 'buttons', '🗑 حذف خط هزینه', '🗑 Delete cost line' );
		self::pair( $r, 'btn.admin.economics_edit_line', 'buttons', '✏️ ویرایش خط هزینه', '✏️ Edit cost line' );
		self::pair( $r, 'btn.admin.economics_deactivate_line', 'buttons', '⏸ غیرفعال خط', '⏸ Deactivate line' );
		self::pair( $r, 'msg.admin.prompt_economics_line_id_invalid', 'messages', '⛔ شناسه خط هزینه نامعتبر است.', '⛔ Invalid cost line id.' );
		self::pair( $r, 'msg.admin.prompt_economics_line_fields', 'messages', '✋ label|category|cost|cycle|active|provider|payment_method|paid_at|expires_at|host_ip|tunnel_mode|notes|sort_order:', '✋ label|category|cost|cycle|active|provider|payment_method|paid_at|expires_at|host_ip|tunnel_mode|notes|sort_order:' );
		self::pair( $r, 'msg.admin.submenu.gen', 'messages', "⚙️ عمومی\nفعال: {enabled} · تست: {test}\nادمین TG: {tg_n} · ادمین Bale: {bl_n} · صفحه: {portal_page} · پلن پیش‌فرض سرویس: {default_plan}\n➖", "⚙️ General\nEnabled: {enabled} · Test: {test}\nTG admins: {tg_n} · Bale admins: {bl_n} · Page: {portal_page} · Default service plan: {default_plan}\n➖" );
		self::pair( $r, 'msg.admin.submenu.set', 'messages', "⚙️ تنظیمات\n{body}\n➖", "⚙️ Settings\n{body}\n➖" );
		self::pair( $r, 'msg.admin.submenu.adv', 'messages', "🔧 تنظیمات پیشرفته\nعمومی، نوتیف، متن‌ها، لاگ، گزارش همگانی.\n➖", "🔧 Advanced settings\nGeneral, notifications, texts, logs, broadcast.\n➖" );
		self::pair( $r, 'msg.admin.submenu.bot', 'messages', "🤖 ربات‌ها\nطول token TG: {tg_len} · Bale: {bale_len}\n➖", "🤖 Bots\nTG token length: {tg_len} · Bale: {bale_len}\n➖" );
		self::pair( $r, 'msg.admin.submenu.pan', 'messages', "🖥 پنل 3x-ui\n{url_state}\n➖", "🖥 3x-ui panel\n{url_state}\n➖" );
		self::pair( $r, 'msg.admin.submenu.pan_has_url', 'messages', 'URL: دارد', 'URL: set' );
		self::pair( $r, 'msg.admin.submenu.pan_no_url', 'messages', 'URL: خالی', 'URL: empty' );
		self::pair( $r, 'msg.admin.submenu.not', 'messages', "🔔 نوتیف\n٪ کم: {low_pct} · هم‌زمان: {concurrent}\nهشدار روز: {expiry_days}", "🔔 Notifications\nLow %: {low_pct} · Concurrent: {concurrent}\nExpiry days: {expiry_days}" );
		self::pair( $r, 'msg.admin.submenu.inl', 'messages', "🔗 Inbound (پنل ۳x-ui)\nلیست → کلاینت‌ها → لینک به کاربر svp", "🔗 Inbound (3x-ui panel)\nList → clients → link to svp user" );
		self::pair( $r, 'msg.admin.submenu.brd', 'messages', "📣 آخرین همگانی\n➖\n{list}", "📣 Recent broadcasts\n➖\n{list}" );
		self::pair( $r, 'msg.admin.submenu.brd_empty', 'messages', 'رکوردی نیست. از دکمه «پیام همگانی» متن ارسال کنید.', 'No records. Send text via the broadcast button.' );
		self::pair( $r, 'msg.admin.submenu.bulk', 'messages', "➕ عملیات گروهی (Xray)\n⚠️ بار زیاد روی پنل؛ حداکثر ۲۰۰ سرویس در هر اجرا.\n➖\n۱) از «🔎 جستجوی کاربر» در منوی مدیریت کاربران یک کاربر را باز کنید.\n۲) دکمهٔ سریع → یک مرحلهٔ تأیید با دکمهٔ بعدی؛ یا «📝 تأیید متنی گروهی».", "➕ Bulk ops (Xray)\n⚠️ Heavy panel load; max 200 services per run.\n➖\n1) Open a user from user search.\n2) Quick button → confirm step, or group text confirm." );
		self::pair( $r, 'msg.admin.submenu.set_body', 'messages', 'پلن، کارت، پنل ۳x-ui{extra}، کانفیگ، کریپتو، ربات.', 'Plans, cards, 3x-ui panel{extra}, config, crypto, bot.' );
		self::pair( $r, 'msg.admin.submenu.set_l2tp', 'messages', '، L2TP', ', L2TP' );
		self::pair( $r, 'msg.admin.referral_status', 'messages', 'فعال: {state}', 'Enabled: {state}' );
		self::pair( $r, 'msg.admin.referral_percent', 'messages', 'درصد: {percent}', 'Percent: {percent}' );
		self::pair( $r, 'msg.admin.referral_invite_link', 'messages', "🔗 لینک دعوت:\n{url}", "🔗 Invite link:\n{url}" );
		self::pair( $r, 'msg.admin.prompt_keep_suffix', 'messages', ' (- برای نگه‌داشتن)', ' (- to keep)' );
		self::pair( $r, 'msg.admin.user_card_status', 'messages', 'وضعیت: {status}', 'Status: {status}' );
		self::pair( $r, 'msg.admin.user_card_balance', 'messages', 'موجودی: {balance}', 'Balance: {balance}' );
		self::pair( $r, 'msg.admin.user_card_services', 'messages', 'سرویس‌ها: {count}', 'Services: {count}' );
		self::pair( $r, 'msg.admin.user_card_manage_hint', 'messages', '🧰 برای مدیریت کامل (مثل کاربر)، یک سرویس را از دکمه‌های اینلاین زیر انتخاب کنید:', '🧰 To manage fully (like the user), pick a service from the inline buttons below:' );
		self::pair( $r, 'btn.admin.user_portal_link', 'buttons', '🌐 لینک پورتال کاربر #{id}', '🌐 User portal link #{id}' );
		self::pair( $r, 'btn.admin.user_block', 'buttons', '⛔ بلاک #{id}', '⛔ Block #{id}' );
		self::pair( $r, 'btn.admin.user_unblock', 'buttons', '✅ آنبلاک #{id}', '✅ Unblock #{id}' );
		self::pair( $r, 'btn.admin.user_create_service', 'buttons', '➕ ساخت سرویس برای #{id}', '➕ Create service for #{id}' );
		self::pair( $r, 'btn.admin.logs_next', 'buttons', 'لاگ بعدی ▶', 'Next logs ▶' );
		self::pair( $r, 'btn.admin.logs_prev', 'buttons', '◀ لاگ قبلی', '◀ Prev logs' );
		self::pair( $r, 'btn.admin.texts_next', 'buttons', 'متن بعدی ▶', 'Next texts ▶' );
		self::pair( $r, 'btn.admin.texts_prev', 'buttons', '◀ متن قبلی', '◀ Prev texts' );
		self::pair( $r, 'btn.admin.texts_reset_all', 'buttons', '🔄 همه به پیش‌فرض', '🔄 Reset all to default' );
		self::pair( $r, 'btn.admin.inbound_panel', 'buttons', '📡 پنل #{id}', '📡 Panel #{id}' );
		self::pair( $r, 'btn.admin.inbound_pick', 'buttons', '📌 Inbound #{id}', '📌 Inbound #{id}' );
		self::pair( $r, 'btn.admin.inbound_back_list', 'buttons', '↩ لیست Inbound', '↩ Inbound list' );
		self::pair( $r, 'msg.admin.inbound_clients_prompt', 'messages', '📎 Inbound #{id} — لینک: svp user id بفرستید (بعد از انتخاب ایمیل)\n', '📎 Inbound #{id} — send svp user id after picking email\n' );
		self::pair( $r, 'btn.admin.inbound_autolink', 'buttons', '⚡ autolink #{id}', '⚡ autolink #{id}' );
		self::pair( $r, 'btn.admin.l2_test', 'buttons', 'L2 تست {id}', 'L2 test {id}' );
		self::pair( $r, 'btn.admin.l2_toggle', 'buttons', 'L2 سوییچ {id}', 'L2 toggle {id}' );
		self::pair( $r, 'btn.admin.l2_delete', 'buttons', 'L2 حذف {id}', 'L2 delete {id}' );
		self::pair( $r, 'btn.admin.service_renew', 'buttons', '♻️ تمدید سرویس #{id}', '♻️ Renew service #{id}' );
		self::pair( $r, 'btn.admin.service_add_volume', 'buttons', '➕ حجم سرویس #{id}', '➕ Add volume #{id}' );
		self::pair( $r, 'btn.admin.service_details', 'buttons', '🖥 جزئیات #{id}', '🖥 Details #{id}' );
		self::pair( $r, 'btn.admin.service_usage', 'buttons', '📊 مصرف #{id}', '📊 Usage #{id}' );
		self::pair( $r, 'btn.admin.service_config', 'buttons', '🔗 کانفیگ #{id}', '🔗 Config #{id}' );
		self::pair( $r, 'btn.admin.service_key', 'buttons', '🔑 کلید #{id}', '🔑 Key #{id}' );
		self::pair( $r, 'btn.admin.service_servers', 'buttons', '🔄 سرورها #{id}', '🔄 Servers #{id}' );
		self::pair( $r, 'btn.admin.service_rename', 'buttons', '✏️ نام #{id}', '✏️ Rename #{id}' );
		self::pair( $r, 'btn.admin.service_note', 'buttons', '📝 یادداشت #{id}', '📝 Note #{id}' );
		self::pair( $r, 'btn.admin.service_alerts', 'buttons', '🔔 هشدار #{id}', '🔔 Alerts #{id}' );
		self::pair( $r, 'btn.admin.service_transfer', 'buttons', '🎁 انتقال سرویس #{id}', '🎁 Transfer service #{id}' );
		self::pair( $r, 'btn.admin.service_pick', 'buttons', '📡 سرویس #{id}', '📡 Service #{id}' );
		self::pair( $r, 'msg.admin.users_queue_empty', 'messages', '👥 کاربری در انتظار تایید نیست.', '👥 No users pending approval.' );
		self::pair( $r, 'msg.admin.users_pending_header', 'messages', "👥 در انتظار تایید: {total}\n🔎 «{search}»\nصفحه offset {offset}\n➖", "👥 Pending approval: {total}\n🔎 «{search}»\nPage offset {offset}\n➖" );
		self::pair( $r, 'msg.admin.users_approved_header', 'messages', "✅ کاربران تأییدشده ({total})\nصفحه offset {offset}\n➖", "✅ Approved users ({total})\nPage offset {offset}\n➖" );
		self::pair( $r, 'msg.admin.users_rejected_header', 'messages', "❌ کاربران رد شده ({total})\noffset {offset}\n➖", "❌ Rejected users ({total})\noffset {offset}\n➖" );
		self::pair( $r, 'msg.admin.prompt_catalog_card_edit', 'messages', "✏️ ویرایش کارت #{id}\ncard_number|holder|bank|method|daily_limit|priority|note|active\n\nفعلی:\n{card_number}|{holder_name}|{bank_name}|{method_key}|{daily_limit}|{priority}|{note}|{active}", "✏️ Edit card #{id}\ncard_number|holder|bank|method|daily_limit|priority|note|active\n\nCurrent:\n{card_number}|{holder_name}|{bank_name}|{method_key}|{daily_limit}|{priority}|{note}|{active}" );
		self::pair( $r, 'msg.admin.prompt_catalog_category_edit', 'messages', "✏️ ویرایش دسته #{id}\nlabel\nsort_order\nactive(0|1)\n\nفعلی:\n{label}\n{sort_order}\n{active}", "✏️ Edit category #{id}\nlabel\nsort_order\nactive(0|1)\n\nCurrent:\n{label}\n{sort_order}\n{active}" );
		self::pair( $r, 'btn.admin.backup.restore_confirm', 'buttons', '✅ ادامهٔ ریستور', '✅ Continue restore' );
		self::pair( $r, 'btn.admin.backup.restore_cancel', 'buttons', '❌ لغو ریستور', '❌ Cancel restore' );
		self::pair( $r, 'msg.admin.economics_site_header', 'messages', '📊 سایت', '📊 Site' );
		self::pair( $r, 'msg.admin.economics_panels_header', 'messages', '📦 پنل‌ها', '📦 Panels' );
		self::pair( $r, 'msg.admin.economics_sales_gb', 'messages', 'فروش GB: {value}', 'Sales GB: {value}' );
		self::pair( $r, 'msg.admin.economics_revenue', 'messages', 'درآمد: {value}', 'Revenue: {value}' );
		self::pair( $r, 'msg.admin.economics_cost', 'messages', 'هزینه: {value}', 'Cost: {value}' );
		self::pair( $r, 'msg.admin.economics_profit', 'messages', 'سود: {value}', 'Profit: {value}' );
		self::pair( $r, 'msg.admin.economics_panel_profit', 'messages', 'سود {value}', 'Profit {value}' );
		self::pair( $r, 'msg.admin.prompt_charges_date_from', 'messages', '✋ از تاریخ (YYYY-MM-DD یا -):', '✋ From date (YYYY-MM-DD or -):' );
		self::pair( $r, 'msg.admin.prompt_charges_date_to', 'messages', '✋ تا تاریخ (YYYY-MM-DD یا -):', '✋ To date (YYYY-MM-DD or -):' );
		self::pair( $r, 'msg.admin.prompt_catalog_plan_edit', 'messages', "✏️ ویرایش پلن #{id}\nهر خط یک فیلد:\nname\ncategory\nduration_days\ntraffic_gb\nprice\ninbound_id\nclients_count\nactive(0|1)\n\nفعلی:\n{name}\n{category}\n{duration_days}\n{traffic_gb}\n{price}\n{inbound_id}\n{clients_count}\n{active}", "✏️ Edit plan #{id}\nOne field per line:\nname\ncategory\nduration_days\ntraffic_gb\nprice\ninbound_id\nclients_count\nactive(0|1)\n\nCurrent:\n{name}\n{category}\n{duration_days}\n{traffic_gb}\n{price}\n{inbound_id}\n{clients_count}\n{active}" );
		self::pair( $r, 'msg.admin.prompt_lifecycle_priority', 'messages', '✋ اولویت (عدد):', '✋ Priority (number):' );
		self::pair( $r, 'msg.admin.prompt_lifecycle_channels', 'messages', '✋ کانال‌ها (telegram,bale):', '✋ Channels (telegram,bale):' );
		self::pair( $r, 'msg.admin.prompt_lifecycle_max_discount', 'messages', '✋ حداکثر تخفیف (۰–۱۰۰):', '✋ Max discount (0–100):' );
		self::pair( $r, 'msg.admin.prompt_lifecycle_code_days', 'messages', '✋ اعتبار کد (روز، - برای نگه‌داشتن):', '✋ Code valid days (- to keep):' );
		self::pair( $r, 'msg.admin.prompt_lifecycle_max_uses', 'messages', '✋ حداکثر استفاده (- برای نگه‌داشتن):', '✋ Max uses per user (- to keep):' );
		self::pair( $r, 'msg.admin.prompt_lifecycle_enabled', 'messages', '✋ فعال (1/0، - برای نگه‌داشتن):', '✋ Enabled (1/0, - to keep):' );
		self::pair( $r, 'btn.admin.charges_filter_volume', 'buttons', '📊 حجم', '📊 Volume' );
		self::pair( $r, 'btn.admin.charges_filter_dates', 'buttons', '📅 تاریخ', '📅 Dates' );
		self::pair( $r, 'btn.admin.charges_prev', 'buttons', '◀ قبلی', '◀ Prev' );
		self::pair( $r, 'btn.admin.charges_next', 'buttons', 'بعدی ▶', 'Next ▶' );
		self::pair( $r, 'btn.admin.economics_config', 'buttons', '⚙️ تنظیمات کلی', '⚙️ Global config' );
		self::pair( $r, 'btn.admin.economics_refresh', 'buttons', '🔄 بروز', '🔄 Refresh' );
		self::pair( $r, 'btn.admin.economics_panel_lines', 'buttons', '📦 هزینه پنل', '📦 Panel costs' );
		self::pair( $r, 'btn.admin.economics_shared_lines', 'buttons', '🌐 هزینه مشترک', '🌐 Shared costs' );
		self::pair( $r, 'btn.admin.economics_mark_paid', 'buttons', '✅ پرداخت خط', '✅ Mark paid' );
		self::pair( $r, 'btn.admin.catalog_add_plan', 'buttons', '➕ پلن', '➕ Plan' );
		self::pair( $r, 'btn.admin.catalog_add_card', 'buttons', '➕ کارت', '➕ Card' );
		self::pair( $r, 'btn.admin.catalog_add_category', 'buttons', '➕ دسته', '➕ Category' );
		self::pair( $r, 'btn.admin.confirm_yes', 'buttons', '✅ بله', '✅ Yes' );
		self::pair( $r, 'btn.admin.confirm_no', 'buttons', '❌ خیر', '❌ No' );
	}

	/**
	 * Support, referral, sync, apps, wallet, state.
	 *
	 * @param array<int, array<string, string>> $r Rows.
	 */
	private static function append_msg_common( array &$r ) {
		self::pair( $r, 'msg.support.intro', 'messages', "🆘 پشتیبانی\n➖➖➖➖➖➖➖➖\nچه کمکی نیاز دارید؟", "🆘 Support\n➖➖➖➖➖➖➖➖\nHow can we help?" );
		self::pair( $r, 'msg.apps.intro', 'messages', '📱 دانلود اپلیکیشن‌ها و لینک‌های رسمی کلاینت:', '📱 Download apps and official client links:' );
		self::pair( $r, 'btn.apps.v2rayng', 'buttons', '🤖 v2rayNG', '🤖 v2rayNG' );
		self::pair( $r, 'btn.apps.shadowrocket', 'buttons', '🍎 Shadowrocket', '🍎 Shadowrocket' );
		self::pair( $r, 'btn.apps.v2rayn', 'buttons', '🪟 v2rayN', '🪟 v2rayN' );
		self::pair( $r, 'btn.apps.v2rayu', 'buttons', '🖥 V2rayU', '🖥 V2rayU' );
		self::pair( $r, 'msg.state.cancelled', 'messages', 'ℹ️ درخواست قبلی لغو شد.', 'ℹ️ Previous request was cancelled.' );
		self::pair(
			$r,
			'msg.sync.code_template',
			'messages',
			"🔗 کد سینک شما:\n➖➖➖➖➖➖➖➖\n🔑 `{code}`\n➖➖➖➖➖➖➖➖\nدر ربات دیگر روی «ورود کد» بزنید و این کد را ارسال کنید.",
			"🔗 Your sync code:\n➖➖➖➖➖➖➖➖\n🔑 `{code}`\n➖➖➖➖➖➖➖➖\nIn the other bot tap “Enter code” and send this code."
		);
		self::pair( $r, 'msg.sync.invalid_code', 'messages', '⛔ کد نامعتبر است.', '⛔ Invalid code.' );
		self::pair( $r, 'msg.sync.own_code', 'messages', 'ℹ️ این کد متعلق به خودتان است.', 'ℹ️ This code belongs to you.' );
		self::pair( $r, 'msg.sync.success', 'messages', '✅ اکانت‌ها با موفقیت سینک شدند.', '✅ Accounts synced successfully.' );
		self::pair( $r, 'msg.sync.transfer_ok', 'messages', '✅ سرویس با موفقیت به شما منتقل شد.', '✅ Service transferred to you successfully.' );
		self::pair( $r, 'msg.sync.transfer_fail', 'messages', '⛔ انتقال سرویس انجام نشد: {reason}', '⛔ Service transfer failed: {reason}' );
		self::pair( $r, 'msg.sync.expired', 'messages', '⛔ کد منقضی یا اشتباه است.', '⛔ Code expired or wrong.' );
		self::pair( $r, 'msg.sync.prompt_code', 'messages', '🔑 کد ۶ رقمی را که در ربات دیگر ساخته‌اید ارسال کنید.', '🔑 Send the 6-digit code you generated in the other bot.' );
		self::pair( $r, 'msg.referral.dear_user', 'messages', 'کاربر گرامی', 'Dear user' );
		self::pair( $r, 'msg.apps.pick', 'messages', "📱 دانلود اپلیکیشن‌ها\n➖➖➖➖➖➖➖➖\nیکی را انتخاب کنید:", "📱 Download apps\n➖➖➖➖➖➖➖➖\nPick one:" );
		self::pair(
			$r,
			'msg.account.info_template',
			'messages',
			"👤 اطلاعات حساب\n➖➖➖➖➖➖➖➖\n🆔 شناسه: {id}\n👑 نقش: {role}\n💰 موجودی: {balance}\n📡 سرویس فعال: {n}\n\n🌐 صفحهٔ شما برای دیدن سرویس و لینک:\n{portal}",
			"👤 Account info\n➖➖➖➖➖➖➖➖\n🆔 ID: {id}\n👑 Role: {role}\n💰 Balance: {balance}\n📡 Active services: {n}\n\n🌐 Your page for services and links:\n{portal}"
		);
		self::pair( $r, 'btn.account.enter_code', 'buttons', '🔑 ورود کد', '🔑 Enter code' );
		self::pair( $r, 'msg.wallet.balance', 'messages', '💰 موجودی کیف پول: {balance} تومان', '💰 Wallet balance: {balance} Toman' );
		self::pair( $r, 'msg.wallet.title', 'messages', '💰 کیف پول شما', '💰 Your wallet' );
		self::pair( $r, 'msg.wallet.topup_hint', 'messages', 'برای شارژ موجودی از دکمه زیر استفاده کنید.', 'Use the button below to top up your balance.' );
		self::pair( $r, 'msg.wallet.topup_disabled_hint', 'messages', 'شارژ آنلاین کیف پول در حال حاضر غیرفعال است.', 'Online wallet top-up is currently disabled.' );
		self::pair( $r, 'msg.wallet.topup_prompt', 'messages', '💳 مبلغ شارژ را به تومان وارد کنید (یا «لغو»):', '💳 Enter top-up amount in Toman (or type cancel):' );
		self::pair( $r, 'msg.wallet.topup_invalid', 'messages', '⛔ مبلغ نامعتبر است. عدد مثبت به تومان وارد کنید.', '⛔ Invalid amount. Enter a positive number in Toman.' );
		self::pair( $r, 'msg.wallet.topup_disabled', 'messages', '⛔ شارژ کیف پول در حال حاضر غیرفعال است.', '⛔ Wallet top-up is currently disabled.' );
		self::pair( $r, 'msg.wallet.topup_order', 'messages', '🧾 شارژ کیف پول · شناسه {id}', '🧾 Wallet top-up · ID {id}' );
		self::pair( $r, 'msg.wallet.topup_checkout_title', 'messages', '🧾 شارژ کیف پول', '🧾 Wallet top-up' );
		self::pair( $r, 'msg.wallet.topup_done', 'messages', '✅ کیف پول شما با موفقیت شارژ شد.', '✅ Your wallet was topped up successfully.' );
		self::pair( $r, 'msg.wallet.topup_bale_title', 'messages', 'شارژ کیف پول', 'Wallet top-up' );
		self::pair( $r, 'msg.wallet.topup_bale_desc', 'messages', 'شارژ کیف پول · شناسه {id}', 'Wallet top-up · ID {id}' );
		self::pair( $r, 'msg.wallet.history_title', 'messages', '📜 تاریخچه', '📜 History' );
		self::pair( $r, 'msg.wallet.history_empty', 'messages', 'تراکنشی ثبت نشده است.', 'No transactions yet.' );
		self::pair( $r, 'msg.wallet.history_line', 'messages', '📌 {type} · {amount} تومان · {status} · #{id}', '📌 {type} · {amount} Toman · {status} · #{id}' );
		self::pair( $r, 'msg.tx.type.purchase', 'messages', 'خرید', 'Purchase' );
		self::pair( $r, 'msg.tx.type.topup', 'messages', 'شارژ کیف', 'Wallet top-up' );
		self::pair( $r, 'msg.tx.type.renew', 'messages', 'تمدید', 'Renewal' );
		self::pair( $r, 'msg.tx.type.other', 'messages', 'تراکنش', 'Transaction' );
		self::pair( $r, 'msg.tx.status.pending', 'messages', 'در انتظار', 'Pending' );
		self::pair( $r, 'msg.tx.status.approved', 'messages', 'تأیید شده', 'Approved' );
		self::pair( $r, 'msg.tx.status.rejected', 'messages', 'رد شده', 'Rejected' );
		self::pair( $r, 'msg.tx.status.cancelled', 'messages', 'لغو شده', 'Cancelled' );
		self::pair( $r, 'msg.tx.status.processing', 'messages', 'در حال پردازش', 'Processing' );
		self::pair( $r, 'msg.buy.no_payment_methods', 'messages', '⛔ روش پرداختی فعال نیست. با پشتیبانی تماس بگیرید.', '⛔ No payment method is enabled. Contact support.' );
		self::pair(
			$r,
			'msg.force_join.prompt',
			'messages',
			"📢 برای استفاده از ربات باید در کانال ما عضو شوید.\nپس از عضویت روی «بررسی عضویت» بزنید.",
			"📢 You must join our channel to use this bot.\nAfter joining, tap “Check membership”."
		);
		self::pair( $r, 'btn.force_join.channel', 'buttons', '📢 عضویت در کانال', '📢 Join channel' );
		self::pair( $r, 'btn.force_join.verify', 'buttons', '✅ بررسی عضویت', '✅ Check membership' );
		self::pair( $r, 'msg.force_join.success', 'messages', '✅ عضویت شما تأیید شد.', '✅ Membership verified.' );
		self::pair( $r, 'msg.force_join.fail', 'messages', '⛔ هنوز در کانال عضو نیستید. ابتدا عضو شوید.', '⛔ You are not in the channel yet. Please join first.' );
		self::pair(
			$r,
			'msg.force_join.misconfigured',
			'messages',
			'⛔ جوین اجباری کانال از طرف مدیر تنظیم نشده است.',
			'⛔ Mandatory channel join is not configured by the admin.'
		);
		self::pair( $r, 'msg.callback.history', 'messages', '📜 تاریخچه تراکنش‌ها', '📜 Transaction history' );
		self::pair( $r, 'msg.callback.contact_prompt', 'messages', '📞 برای تماس با پشتیبانی پیام خود را بفرستید.', '📞 Send your message to contact support.' );
		self::pair( $r, 'msg.callback.transfer_prompt', 'messages', '🎁 کد انتقال سرویس را بفرستید.', '🎁 Send the service transfer code.' );
		self::pair(
			$r,
			'msg.referral.screen',
			'messages',
			"💰 درآمد واقعی از معرفی سرویس‌مون!\n\n{disabled_note}اگه از سرویس راضی‌ای و دلت می‌خواد بدون هزینه تمدید کنی یا حتی پول دربیاری، این فرصت واسه توئه👇\n\n🎯 با دعوت فقط چند نفر، سرویس رایگان بگیر یا درآمد نقدی داشته باش!\n\nچطوری؟\nفقط لینک اختصاصی خودتو برای دوستات یا گروه‌هایی که داخلشی بفرست!\nهر خرید = درآمد برای تو!\n\n🔹 {pct}٪ پورسانت دائمی از هر خرید زیرمجموعه\n🔹 بدون سقف زمانی یا محدودیت تعداد\n🔹 درآمدت مستقیم توی کیف پولت ذخیره میشه\n\n🟢 با موجودی کیف پول می‌تونی:\n1️⃣ اشتراکت رو مجانی تمدید کنی\n2️⃣ یا درخواست برداشت بزنی و 💵 پول نقد بگیری!\n\n====================\n📌 مثال ساده:\nکمترین خرید = {base} تومان\n{pct}٪ پورسانت = {comm} تومان\nفقط با {ex_n} نفر = {total} تومان تو کیف پولت!\n\nیعنی نه تنها رایگان استفاده می‌کنی، بلکه سود هم داری!\n\n====================\n🎁 لینک دعوت مخصوص شما آماده‌ست!👇\n\n📎 شناسهٔ شما: #{user_id}\n{tg_block}{bl_block}",
			"💰 Real income from referring our service!\n\n{disabled_note}If you're happy with the service and want free renewals or cash income, this is for you👇\n\n🎯 Invite a few people — get free service or cash!\n\nHow?\nShare your personal link with friends or groups you're in!\nEvery purchase = income for you!\n\n🔹 {pct}% lifetime commission on sub-user purchases\n🔹 No time cap or invite limit\n🔹 Earnings go straight to your wallet\n\n🟢 With wallet balance you can:\n1️⃣ Renew for free\n2️⃣ Request withdrawal and get 💵 cash!\n\n====================\n📌 Simple example:\nMinimum purchase = {base} Toman\n{pct}% commission = {comm} Toman\nJust {ex_n} people = {total} Toman in your wallet!\n\nFree usage plus profit!\n\n====================\n🎁 Your invite link is ready!👇\n\n📎 Your id: #{user_id}\n{tg_block}{bl_block}"
		);
		self::pair( $r, 'msg.referral.disabled_note', 'messages', "⏸️ فعلاً سیستم دعوت از طرف مدیریت غیرفعال است؛ اطلاعات زیر فقط برای آشنایی با نحوهٔ کار است.\n\n", '⏸️ Referrals are disabled by admin; info below is for reference only.\n\n' );
		self::pair( $r, 'msg.referral.tg_link', 'messages', "تلگرام:\n{link}\n\n", "Telegram:\n{link}\n\n" );
		self::pair( $r, 'msg.referral.tg_fallback', 'messages', "تلگرام: نام کاربری ربات در تنظیمات (telegram_bot_username) را تنظیم کنید؛ فعلاً:\n/start ref_{id}\n\n", "Telegram: set telegram_bot_username in settings; for now:\n/start ref_{id}\n\n" );
		self::pair( $r, 'msg.referral.bl_link', 'messages', "بله:\n{link}\n", "Bale:\n{link}\n" );
		self::pair( $r, 'msg.referral.bl_fallback', 'messages', "بله: bale_bot_username را در تنظیمات وارد کنید؛ فعلاً:\n/start ref_{id}\n", "Bale: set bale_bot_username in settings; for now:\n/start ref_{id}\n" );
	}

	/**
	 * Cron / system notification templates.
	 *
	 * @param array<int, array<string, string>> $r Rows.
	 */
	private static function append_msg_cron( array &$r ) {
		self::pair(
			$r,
			'msg.cron_purge_warn',
			'messages',
			"{name} عزیز؛\n\nسرویس «{remark}» شما تا {days} روز دیگر به‌طور خودکار از ربات و پنل حذف می‌شود.\n\n⏳ مهلت پس از انقضا: {grace_days} روز\n📅 برای ادامه استفاده، از منوی همان سرویس گزینه «تمدید» را انتخاب کنید.",
			"Dear {name},\n\nYour service «{remark}» will be automatically removed from the bot and panel in {days} days.\n\n⏳ Grace period after expiry: {grace_days} days\n📅 To keep using it, open that service and tap Renew."
		);
		self::pair(
			$r,
			'msg.cron_purge_warn_tomorrow',
			'messages',
			"{name} عزیز؛\n\nسرویس «{remark}» شما فردا به‌طور خودکار از ربات و پنل حذف می‌شود.\n\n⏳ مهلت پس از انقضا: {grace_days} روز\n📅 برای ادامه استفاده، همین امروز از منوی همان سرویس «تمدید» را بزنید.",
			"Dear {name},\n\nYour service «{remark}» will be automatically removed from the bot and panel tomorrow.\n\n⏳ Grace period after expiry: {grace_days} days\n📅 To keep using it, renew from that service menu today."
		);
		self::pair(
			$r,
			'msg.cron_purge_warn_today',
			'messages',
			"{name} عزیز؛\n\nسرویس «{remark}» شما امروز به‌طور خودکار از ربات و پنل حذف می‌شود (پایان مهلت پس از انقضا).\n\n⏳ مهلت پس از انقضا: {grace_days} روز\n📅 اگر هنوز به این سرویس نیاز دارید، فوراً از منوی همان سرویس «تمدید» را بزنید.",
			"Dear {name},\n\nYour service «{remark}» will be automatically removed from the bot and panel today (end of the post-expiry grace period).\n\n⏳ Grace period after expiry: {grace_days} days\n📅 If you still need this service, renew from its menu right away."
		);
		self::pair(
			$r,
			'msg.cron_expiry_before',
			'messages',
			"{name} عزیز؛\n\nسرویس «{remark}» شما تا {days} روز دیگر منقضی می‌شود.\n\n📅 برای جلوگیری از قطع سرویس، از منوی همان سرویس «تمدید» را بزنید.",
			"Dear {name},\n\nYour service «{remark}» expires in {days} days.\n\n📅 To avoid interruption, renew from that service menu."
		);
		self::pair(
			$r,
			'msg.cron_expiry_today',
			'messages',
			"{name} عزیز؛\n\nامروز آخرین روز اعتبار سرویس «{remark}» شماست.\n\n📅 برای ادامه بدون وقفه، همین الان «تمدید» را از منوی همان سرویس انتخاب کنید.",
			"Dear {name},\n\nToday is the last day of service «{remark}».\n\n📅 Renew from that service menu now to avoid interruption."
		);
		self::pair(
			$r,
			'msg.cron_expiry_after',
			'messages',
			"{name} عزیز؛\n\nاعتبار سرویس «{remark}» شما {days} روز پیش به پایان رسیده است.\n\n📅 برای فعال‌سازی مجدد، از منوی همان سرویس «تمدید» را بزنید یا از بخش خرید اقدام کنید.",
			"Dear {name},\n\nYour service «{remark}» expired {days} days ago.\n\n📅 Renew from that service menu or buy again to reactivate."
		);
		self::pair(
			$r,
			'msg.cron_low_traffic',
			'messages',
			"{name} عزیز؛\n\nحجم باقی‌مانده سرویس «{remark}» کم شده است.\n\n📊 حدود {remaining_pct}٪ از حجم کل هنوز مانده است.\n📅 در صورت نیاز، از منوی همان سرویس «افزودن حجم» یا «تمدید» را انتخاب کنید.",
			"Dear {name},\n\nRemaining traffic for service «{remark}» is low.\n\n📊 About {remaining_pct}% of total volume remains.\n📅 If needed, add volume or renew from that service menu."
		);
		self::pair(
			$r,
			'msg.cron_after_expired',
			'messages',
			"{name} عزیز؛\n\nسرویس «{remark}» منقضی شده و دیگر قابل استفاده نیست.\n\n📅 برای ادامه، از بخش خرید سرویس جدید بگیرید یا با پشتیبانی تماس بگیرید.",
			"Dear {name},\n\nService «{remark}» has expired and is no longer usable.\n\n📅 Buy a new service or contact support to continue."
		);
	}

	/**
	 * Admin cron / system alert templates (panel health, economics).
	 *
	 * @param array<int, array<string, string>> $r Rows.
	 */
	private static function append_msg_cron_admin( array &$r ) {
		self::pair( $r, 'msg.cron.admin.panel_legacy_label', 'admin', 'پنل ذخیره‌شده در «تنظیمات افزونه → پنل X-UI» (جدول «پنل‌ها» خالی است)', 'Panel saved in Plugin settings → X-UI panel (panels table empty)' );
		self::pair( $r, 'msg.cron.admin.panel_login_failed', 'admin', 'ورود ۳x-ui از طرف سرور وردپرس/ربات برقرار نشد.', '3x-ui login from WordPress/bot server failed.' );
		self::pair( $r, 'msg.cron.admin.panel_label', 'admin', 'پنل:', 'Panel:' );
		self::pair( $r, 'msg.cron.admin.panel_db_id', 'admin', 'شناسهٔ رکورد در دیتابیس (svp_panels.id):', 'Database record id (svp_panels.id):' );
		self::pair(
			$r,
			'msg.cron.admin.panel_troubleshoot',
			'admin',
			'اگر در مرورگر پنل باز است: مسیر webBasePath در Panel URL، Secret ورود، فایروال یا TLS بین هاست وردپرس و پنل را بررسی کنید. برای غیرفعال‌کردن این هشدار: تنظیمات ربات → اعلان قطع پنل.',
			'If the panel opens in a browser: check webBasePath in Panel URL, login secret, firewall, or TLS between WordPress host and panel. To disable: bot settings → panel down alert.'
		);
		self::pair( $r, 'msg.cron.admin.panel_cost_renewal_title', 'admin', 'یادآور تمدید هزینهٔ زیرساخت پنل', 'Panel infrastructure cost renewal reminder' );
		self::pair( $r, 'msg.cron.admin.expires_today', 'admin', 'امروز منقضی می‌شود.', 'Expires today.' );
		self::pair( $r, 'msg.cron.admin.expires_tomorrow', 'admin', 'فردا منقضی می‌شود.', 'Expires tomorrow.' );
		self::pair( $r, 'msg.cron.admin.expires_in_days', 'admin', '{days} روز تا انقضا.', '{days} days until expiry.' );
		self::pair( $r, 'msg.cron.admin.category_label', 'admin', 'دسته:', 'Category:' );
		self::pair( $r, 'msg.cron.admin.title_label', 'admin', 'عنوان:', 'Title:' );
		self::pair( $r, 'msg.cron.admin.expiry_date_label', 'admin', 'تاریخ انقضا:', 'Expiry date:' );
	}

	/**
	 * Marketing automation messages.
	 *
	 * @param array<int, array<string, string>> $r Rows.
	 */
	private static function append_msg_marketing( array &$r ) {
		self::pair( $r, 'msg.marketing.template.churned', 'marketing', 'سلام! مدتی است خریدی ثبت نشده. کد تخفیف اختصاصی شما: {code}', 'Hi! No purchase in a while. Your personal discount code: {code}' );
		self::pair( $r, 'msg.marketing.template.never_purchased', 'marketing', 'اولین خریدت را با کد {code} شروع کن — از منوی ربات «خرید سرویس».', 'Start your first purchase with code {code} — from «Buy service» in the bot menu.' );
		self::pair( $r, 'msg.marketing.template.abandoned_checkout', 'marketing', 'سبد خریدت منتظر است! کد {code} را در مرحله تخفیف وارد کن.', 'Your cart is waiting! Enter code {code} at the discount step.' );
		self::pair( $r, 'msg.marketing.template.stale_buy_funnel', 'marketing', 'خریدت نیمه‌کاره مانده — کد {code} برای تکمیل خرید.', 'Purchase incomplete — code {code} to finish checkout.' );
		self::pair( $r, 'msg.marketing.template.expiring_renew', 'marketing', 'سرویست به‌زودی منقضی می‌شود. برای تمدید از کد {code} استفاده کن.', 'Your service expires soon. Use code {code} to renew.' );
		self::pair( $r, 'msg.marketing.template.default', 'marketing', 'پیشنهاد ویژه: کد {code}', 'Special offer: code {code}' );
		self::pair( $r, 'msg.marketing.apply_button_hint', 'marketing', 'برای اعمال خودکار روی خرید در انتظار، دکمه زیر را بزنید.', 'Tap the button below to apply automatically to a pending purchase.' );
		self::pair( $r, 'msg.marketing.offer_invalid', 'marketing', 'کد پیشنهاد معتبر نیست یا مربوط به حساب دیگری است.', 'Offer code invalid or belongs to another account.' );
		self::pair( $r, 'msg.marketing.code_active', 'marketing', 'کد شما فعال است:', 'Your code is active:' );
		self::pair( $r, 'btn.marketing.apply_purchase', 'buttons', 'اعمال روی خرید', 'Apply to purchase' );
		self::pair( $r, 'msg.marketing.offer_not_found', 'marketing', 'پیشنهاد یافت نشد.', 'Offer not found.' );
		self::pair( $r, 'msg.marketing.no_pending_purchase', 'marketing', 'خرید در انتظاری نیست. از منو «خرید سرویس» شروع کن و کد را در مرحله تخفیف وارد کن.', 'No pending purchase. Start from «Buy service» and enter the code at the discount step.' );
		self::pair( $r, 'msg.marketing.apply_failed', 'marketing', 'اعمال کد ناموفق بود.', 'Could not apply code.' );
		self::pair( $r, 'msg.marketing.apply_ok', 'marketing', 'کد تخفیف اعمال شد. پرداخت را تکمیل کن.', 'Discount applied. Complete payment.' );
	}

	/**
	 * Membership approval / rejection notifications.
	 *
	 * @param array<int, array<string, string>> $r Rows.
	 */
	private static function append_msg_membership( array &$r ) {
		self::pair( $r, 'msg.membership.requeued', 'notifications', '⏳ درخواست شما دوباره در صف بررسی ادمین قرار گرفت.', '⏳ Your request was requeued for admin review.' );
	}

	/**
	 * In-bot service alert help copy.
	 *
	 * @param array<int, array<string, string>> $r Rows.
	 */
	private static function append_msg_alerts( array &$r ) {
		self::pair(
			$r,
			'msg.alerts.panel_intro',
			'messages',
			"🔔 هشدارهای سرویس\n📣 ربات برای همین سرویس در تلگرام یا بله پیام کوتاه می‌فرستد.\n➖➖➖➖➖➖➖➖\n📊 حجم — وقتی حجم باقی‌مانده به آستانه‌ای که تعیین کردید برسد.\n➖➖➖➖➖➖➖➖\n⏰ زمان — در روزهای مشخص قبل از انقضا (یک‌بار برای هر روز).\n➖➖➖➖➖➖➖➖\n👥 محدودیت کاربر — وقتی استفاده هم‌زمان به سقف نزدیک شود (فقط Xray).\n➖➖➖➖➖➖➖➖\n✋ هر ردیف را روشن یا خاموش کنید؛ «آستانه‌ها» محل تنظیم اعداد است.",
			"🔔 Service alerts\n📣 The bot sends short messages on Telegram or Bale for this service.\n➖➖➖➖➖➖➖➖\n📊 Volume — when remaining traffic hits your threshold.\n➖➖➖➖➖➖➖➖\n⏰ Time — on chosen days before expiry (once per day).\n➖➖➖➖➖➖➖➖\n👥 User cap — when concurrent use nears the limit (Xray only).\n➖➖➖➖➖➖➖➖\n✋ Toggle each row; Thresholds is where you set the numbers."
		);
		self::pair(
			$r,
			'msg.alerts.thresholds_intro',
			'messages',
			"⚙️ آستانه‌های هشدار\n📉 حجم — عدد ۱ تا ۹۹ (درصد باقی‌مانده).\n➖➖➖➖➖➖➖➖\n📅 انقضا — اعداد با کاما مثل ۳,۱,۰ (روز قبل از انقضا؛ منفی = بعد از انقضا).\n➖➖➖➖➖➖➖➖\n👥 محدودیت کاربر — عدد ۵۰ تا ۱۰۰ (درصد پر شدن سقف).\n➖➖➖➖➖➖➖➖\n✋ دکمه را بزنید و فقط همان عدد یا اعداد را در چت بفرستید.",
			"⚙️ Alert thresholds\n📉 Volume — 1–99 (remaining percent).\n➖➖➖➖➖➖➖➖\n📅 Expiry — comma-separated days like 3,1,0 (before expiry; negative = after).\n➖➖➖➖➖➖➖➖\n👥 User cap — 50–100 (fill percent of slot limit).\n➖➖➖➖➖➖➖➖\n✋ Tap a button, then send only that number or list in chat."
		);
		self::pair(
			$r,
			'msg.alerts.add_users_prompt',
			'messages',
			"👥 افزایش کاربر هم‌زمان\n➖➖➖➖➖➖➖➖\nبا این گزینه می‌توانید تعداد افرادی که هم‌زمان از سرویس استفاده می‌کنند را افزایش دهید.\n➖➖➖➖➖➖➖➖\n✋ فقط یک عدد بفرستید: چند نفر اضافه شود؟ (مثلاً ۱ تا ۵۰).",
			"👥 Add concurrent users\n➖➖➖➖➖➖➖➖\nIncrease how many people can use this service at the same time.\n➖➖➖➖➖➖➖➖\n✋ Send one number: how many to add (e.g. 1–50)."
		);
		self::pair(
			$r,
			'msg.alerts.threshold_volume_prompt',
			'messages',
			"📉 آستانهٔ حجم\n➖➖➖➖➖➖➖➖\nوقتی درصد حجم باقی‌مانده به این عدد برسد، ربات یک‌بار به شما پیام می‌دهد.\n➖➖➖➖➖➖➖➖\n📋 الان: {pct}٪\n➖➖➖➖➖➖➖➖\n✋ یک عدد ۱ تا ۹۹ بفرستید (مثلاً ۲۰).",
			"📉 Volume threshold\n➖➖➖➖➖➖➖➖\nWhen remaining volume percent reaches this value, the bot notifies you once.\n➖➖➖➖➖➖➖➖\n📋 Current: {pct}%\n➖➖➖➖➖➖➖➖\n✋ Send a number from 1 to 99 (e.g. 20)."
		);
		self::pair(
			$r,
			'msg.alerts.threshold_expiry_prompt',
			'messages',
			"📅 روزهای هشدار قبل از انقضا\n➖➖➖➖➖➖➖➖\nدر هر یک از این روزها (نسبت به تاریخ انقضا) ربات یک‌بار خبر می‌دهد. عدد ۰ یعنی همان روز انقضا.\n➖➖➖➖➖➖➖➖\n📋 الان: {days}\n➖➖➖➖➖➖➖➖\n✋ چند عدد با کامای انگلیسی بفرستید مثل ۳,۱,۰ .",
			"📅 Expiry warning days\n➖➖➖➖➖➖➖➖\nOn each of these day offsets (relative to expiry) the bot notifies once. 0 = expiry day.\n➖➖➖➖➖➖➖➖\n📋 Current: {days}\n➖➖➖➖➖➖➖➖\n✋ Send comma-separated numbers like 3,1,0 ."
		);
		self::pair(
			$r,
			'msg.alerts.threshold_ip_prompt',
			'messages',
			"👥 آستانهٔ محدودیت کاربر\n➖➖➖➖➖➖➖➖\nوقتی تعداد استفاده‌کنندگان هم‌زمان به این درصد از سقف ثبت‌شده برسد، ربات هشدار می‌دهد.\n➖➖➖➖➖➖➖➖\n📋 الان: {pct}٪\n➖➖➖➖➖➖➖➖\n✋ یک عدد ۵۰ تا ۱۰۰ بفرستید (مثلاً ۸۵).",
			"👥 Concurrent user threshold\n➖➖➖➖➖➖➖➖\nWhen simultaneous use reaches this percent of your slot limit, the bot warns you.\n➖➖➖➖➖➖➖➖\n📋 Current: {pct}%\n➖➖➖➖➖➖➖➖\n✋ Send a number from 50 to 100 (e.g. 85)."
		);
	}
}
