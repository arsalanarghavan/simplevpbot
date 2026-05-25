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
		self::pair( $r, 'btn.common.copy_amount', 'buttons', '💵 کپی مبلغ', '💵 Copy amount' );
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
		self::pair( $r, 'btn.pay.approve_receipt', 'buttons', '✅ تایید رسید', '✅ Approve receipt' );
		self::pair( $r, 'btn.pay.reject_receipt', 'buttons', '❌ رد رسید', '❌ Reject receipt' );
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
		self::pair( $r, 'msg.svc.servers_refreshed', 'messages', '🔄 اطلاعات سرور به‌روز شد.', '🔄 Server info updated.' );
		self::pair( $r, 'msg.svc.auto_renew_on', 'messages', '🔁 تمدید خودکار: ✅ روشن', '🔁 Auto-renew: ✅ On' );
		self::pair( $r, 'msg.svc.auto_renew_off', 'messages', '🔁 تمدید خودکار: ❌ خاموش', '🔁 Auto-renew: ❌ Off' );
		self::pair( $r, 'msg.svc.prompt_panel_note', 'messages', '📝 یادداشت نمایش (نام روی پنل X-UI) را ارسال کنید:', '📝 Send the display note (name on X-UI panel):' );
		self::pair( $r, 'msg.svc.prompt_display_name', 'messages', '✏️ نام نمایشی این سرویس (در ربات و لیست سرویس‌ها) را ارسال کنید:', '✏️ Send this service display name (in bot and service list):' );
		self::pair( $r, 'msg.svc.default_plan_missing', 'messages', '⛔ پلن سرویس برای صدور فاکتور تنظیم نشده. در تنظیمات عمومی، «پلن پیش‌فرض سرویس‌های بدون پلن» را روی یک پلن Xray فعال بگذارید.', '⛔ No service plan for invoicing. In general settings, set “Default plan for services without a plan” to an active Xray plan.' );
		self::pair( $r, 'msg.svc.internal_button_error', 'messages', '⛔ خطای داخلی دکمه‌ها.', '⛔ Internal button error.' );
		self::pair( $r, 'msg.svc.volume_xray_only', 'messages', '⛔ افزایش حجم از این مسیر فقط برای Xray است.', '⛔ Volume add via this path is only for Xray.' );
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
		self::pair( $r, 'msg.buy.plan_confirm', 'messages', "📦 {name}\n💰 قیمت: {price} تومان\n⏳ مدت: {days} روز · 📊 حجم: {volume}\nتایید می‌کنید؟", "📦 {name}\n💰 Price: {price} Toman\n⏳ Duration: {days} days · 📊 Volume: {volume}\nConfirm?" );
		self::pair( $r, 'msg.buy.payment_error', 'messages', '⛔ {message}', '⛔ {message}' );
		self::pair( $r, 'msg.buy.invoice_renew', 'messages', 'تمدید: {name}', 'Renew: {name}' );
		self::pair( $r, 'msg.buy.invoice_purchase', 'messages', 'خرید: {name}', 'Purchase: {name}' );
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
}
