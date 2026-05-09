<?php
/**
 * Default bot text seeds (fa / en) for svp_texts.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Default pairs for SimpleVPBot_Activator::default_text_rows().
 */
class SimpleVPBot_Bot_Text_Defaults {

	/**
	 * @param array<int, array<string, string>> $rows Rows accumulator.
	 * @param string                            $key Key.
	 * @param string                            $category Category.
	 * @param string                            $fa Persian value.
	 * @param string                            $en English value.
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
	 * Default fa/en strings for a text key (from seeded defaults).
	 *
	 * @param string $key Text key e.g. btn.main.buy.
	 * @return array{fa:string,en:string}
	 */
	public static function default_pair_for_key( $key ) {
		static $map = null;
		$key = (string) $key;
		if ( '' === $key ) {
			return array( 'fa' => '', 'en' => '' );
		}
		if ( null === $map ) {
			$map = array();
			foreach ( self::all_rows() as $row ) {
				if ( empty( $row['key_name'] ) || empty( $row['locale'] ) ) {
					continue;
				}
				$k = (string) $row['key_name'];
				if ( ! isset( $map[ $k ] ) ) {
					$map[ $k ] = array();
				}
				$map[ $k ][ (string) $row['locale'] ] = (string) ( $row['value'] ?? '' );
			}
		}
		if ( ! isset( $map[ $key ] ) ) {
			return array( 'fa' => '', 'en' => '' );
		}
		return array(
			'fa' => isset( $map[ $key ]['fa'] ) ? (string) $map[ $key ]['fa'] : '',
			'en' => isset( $map[ $key ]['en'] ) ? (string) $map[ $key ]['en'] : '',
		);
	}

	/**
	 * All default rows (two locales per logical key).
	 *
	 * @return array<int, array<string, string>>
	 */
	public static function all_rows() {
		$r = array();
		self::pair( $r, 'btn.main.buy', 'buttons', '🛒 خرید سرویس', '🛒 Buy service' );
		self::pair( $r, 'btn.main.manage', 'buttons', '🧰 مدیریت سرویس', '🧰 Manage service' );
		self::pair( $r, 'btn.main.wallet', 'buttons', '💰 کیف پول', '💰 Wallet' );
		self::pair( $r, 'btn.main.apps', 'buttons', '📱 اپلیکیشن‌ها', '📱 Apps' );
		self::pair( $r, 'btn.main.support', 'buttons', '🆘 پشتیبانی', '🆘 Support' );
		self::pair( $r, 'btn.main.account', 'buttons', '👤 اطلاعات حساب', '👤 Account' );
		self::pair( $r, 'btn.main.referral', 'buttons', '💎 کسب درآمد', '💎 Refer & earn' );
		self::pair( $r, 'btn.admin.dashboard', 'buttons', '📊 آمار', '📊 Stats' );
		self::pair( $r, 'btn.admin.users', 'buttons', '👥 مدیریت کاربران', '👥 Users' );
		self::pair( $r, 'btn.admin.finance', 'buttons', '💰 مالی', '💰 Finance' );
		self::pair( $r, 'btn.admin.broadcast', 'buttons', '📣 پیام همگانی', '📣 Broadcast' );
		self::pair( $r, 'btn.admin.settings', 'buttons', '⚙️ تنظیمات', '⚙️ Settings' );
		self::pair( $r, 'btn.admin.advanced', 'buttons', '🔧 تنظیمات پیشرفته', '🔧 Advanced' );
		self::pair( $r, 'btn.admin.users_search', 'buttons', '🔎 جستجوی کاربر', '🔎 Find user' );
		self::pair( $r, 'btn.admin.users_queue', 'buttons', '📋 صف ثبت‌نام', '📋 Signup queue' );
		self::pair( $r, 'btn.admin.receipts', 'buttons', '🧾 تایید رسیدها', '🧾 Receipts' );
		self::pair( $r, 'btn.admin.backup', 'buttons', '💾 پشتیبان‌گیری', '💾 Backup' );
		self::pair( $r, 'btn.admin.full_hub', 'buttons', '🧩 پنل کامل', '🧩 Full panel' );
		self::pair( $r, 'btn.admin.back_menu', 'buttons', '⬅️ منوی مدیریت', '⬅️ Admin menu' );
		self::pair( $r, 'btn.admin.send_my_portal', 'buttons', '🌐 ارسال لینک پنل وب من', '🌐 Send my web portal link' );
		self::pair( $r, 'btn.admin.send_admin_portal', 'buttons', '🖥 ارسال لینک پنل ادمین وب', '🖥 Send admin web portal link' );
		self::pair( $r, 'btn.admin.transfer', 'buttons', '🎁 انتقال سرویس', '🎁 Transfer service' );
		self::pair( $r, 'btn.admin.dm_user', 'buttons', '✉️ پیام به کاربر', '✉️ Message user' );
		self::pair( $r, 'btn.admin.exit', 'buttons', '🚪 خروج از پنل مدیریت', '🚪 Exit admin panel' );
		self::pair( $r, 'btn.approve', 'buttons', '✅ تایید', '✅ Approve' );
		self::pair( $r, 'btn.reject', 'buttons', '❌ رد', '❌ Reject' );
		self::pair( $r, 'btn.approved_by', 'buttons', '✅ تایید شد توسط {admin}', '✅ Approved by {admin}' );
		self::pair( $r, 'btn.rejected_by', 'buttons', '❌ رد شد توسط {admin}', '❌ Rejected by {admin}' );
		self::pair( $r, 'btn.service.show_panel', 'buttons', '🖥 جزئیات سرویس', '🖥 Service details' );
		self::pair( $r, 'btn.wallet.topup', 'buttons', '➕ شارژ', '➕ Top up' );
		self::pair( $r, 'btn.wallet.history', 'buttons', '📜 تاریخچه', '📜 History' );
		self::pair( $r, 'btn.account.sync', 'buttons', '🔗 سینک با ربات دیگر', '🔗 Sync with another bot' );
		self::pair( $r, 'btn.support.contact', 'buttons', '📞 تماس با پشتیبانی', '📞 Contact support' );
		self::pair( $r, 'btn.support.faq', 'buttons', '❓ سوالات متداول', '❓ FAQ' );
		self::pair(
			$r,
			'msg.welcome',
			'messages',
			"👋 سلام {name}!\n➖➖➖➖➖➖➖➖\nبه ربات VIP ما خوش آمدید.{referrer_line}\nبرای شروع از منوی زیر استفاده کنید.",
			"👋 Hi {name}!\n➖➖➖➖➖➖➖➖\nWelcome to our VIP bot.{referrer_line}\nUse the menu below to get started."
		);
		self::pair(
			$r,
			'msg.approval_wait',
			'messages',
			"⏳ درخواست شما برای ادمین ارسال شد.\n➖➖➖➖➖➖➖➖\nلطفاً تا تایید ثبت‌نام صبر کنید.",
			"⏳ Your request was sent to an admin.\n➖➖➖➖➖➖➖➖\nPlease wait for signup approval."
		);
		self::pair( $r, 'msg.approval_rejected', 'messages', '⛔ متأسفانه ثبت‌نام شما رد شد.', '⛔ Your signup was rejected.' );
		self::pair(
			$r,
			'msg.approval_approved',
			'messages',
			"✅ ثبت‌نام شما تایید شد!\n➖➖➖➖➖➖➖➖\nاز منوی زیر ادامه دهید.",
			"✅ Your signup was approved!\n➖➖➖➖➖➖➖➖\nContinue with the menu below."
		);
		self::pair(
			$r,
			'msg.admin_approval',
			'messages',
			'متن ارسالی به ادمین برای ثبت‌نام از قالب ثابت در ربات ساخته می‌شود (نام، یوزرنیم، تلگرام، بله، شناسهٔ ربات، تأیید).',
			'The admin notification for signup is built from a fixed bot template (name, username, Telegram, Bale, bot id, confirmation).'
		);
		self::pair(
			$r,
			'msg.admin_find_user_prompt',
			'messages',
			"🔎 جستجوی کاربر\nشناسهٔ داخلی در ربات (عدد)، chat id تلگرام یا بله، @username، یا نام / بخشی از شمارهٔ تلفن را ارسال کنید.",
			"🔎 Find user\nSend internal bot id (number), Telegram or Bale chat id, @username, or name / part of phone number."
		);
		self::pair(
			$r,
			'msg.subscription_panel',
			'messages',
			"📊 وضعیت سرویس\n──────────\n🏷 نام: {remark}\n🆔 شناسه: {sub_id}\n📶 وضعیت: {status_emoji} {status}\n──────────\n⬇️ دانلود: {down_h}\n⬆️ آپلود: {up_h}\n📊 مصرف کل: {used_h}\n🧮 سهمیه: {total_quota}\n🎯 باقی‌مانده: {remained_h}\n──────────\n🕒 آخرین اتصال: {last_online}\n⏳ انقضا: {expiry}",
			"📊 Service status\n──────────\n🏷 Name: {remark}\n🆔 ID: {sub_id}\n📶 Status: {status_emoji} {status}\n──────────\n⬇️ Download: {down_h}\n⬆️ Upload: {up_h}\n📊 Total usage: {used_h}\n🧮 Quota: {total_quota}\n🎯 Remaining: {remained_h}\n──────────\n🕒 Last online: {last_online}\n⏳ Expiry: {expiry}"
		);
		$url_v2rayng    = 'https://github.com/2dust/v2rayNG/releases';
		$url_shadow     = 'https://apps.apple.com/app/shadowrocket/id932747118';
		$url_v2rayn     = 'https://github.com/2dust/v2rayN/releases';
		$url_v2rayu     = 'https://github.com/yanue/V2rayU/releases';
		self::pair( $r, 'app.v2rayng', 'apps', $url_v2rayng, $url_v2rayng );
		self::pair( $r, 'app.shadowrocket', 'apps', $url_shadow, $url_shadow );
		self::pair( $r, 'app.v2rayn', 'apps', $url_v2rayn, $url_v2rayn );
		self::pair( $r, 'app.v2rayu', 'apps', $url_v2rayu, $url_v2rayu );
		self::pair(
			$r,
			'faq.connection',
			'faq',
			"❓ مشکل اتصال\n➖➖➖➖➖➖➖➖\n📶 اینترنت پایدار داشته باشید.\n🕐 زمان دستگاه را همگام کنید.\n🔗 از لینک اشتراک تازه استفاده کنید.",
			"❓ Connection issues\n➖➖➖➖➖➖➖➖\n📶 Use a stable connection.\n🕐 Sync device time.\n🔗 Use a fresh subscription link."
		);
		self::pair(
			$r,
			'faq.speed',
			'faq',
			"❓ سرعت پایین\n➖➖➖➖➖➖➖➖\n🖥 سرور دیگری انتخاب کنید.\n📶 از Wi‑Fi بهتر استفاده کنید.",
			"❓ Low speed\n➖➖➖➖➖➖➖➖\n🖥 Try another server.\n📶 Prefer better Wi‑Fi."
		);
		self::pair(
			$r,
			'faq.install',
			'faq',
			"❓ راهنمای نصب\n➖➖➖➖➖➖➖➖\n📱 از بخش اپلیکیشن‌ها کلاینت مناسب را دانلود کنید و لینک اشتراک را وارد کنید.",
			"❓ Installation\n➖➖➖➖➖➖➖➖\n📱 Download a client from Apps and import your subscription link."
		);
		self::pair(
			$r,
			'faq.l2tp',
			'faq',
			"❓ اتصال L2TP\n➖➖➖➖➖➖➖➖\n• در ویندوز: Settings → VPN → Add → نوع L2TP/IPsec with pre-shared key.\n• در iOS/اندروید: Settings → VPN → Add VPN → L2TP.\n• اگر وصل نشد اینترنت/فایروال پورت UDP 500/4500/1701 را چک کنید.",
			"❓ L2TP connection\n➖➖➖➖➖➖➖➖\n• Windows: Settings → VPN → Add → L2TP/IPsec with pre-shared key.\n• iOS/Android: Settings → VPN → Add VPN → L2TP.\n• If it fails, check firewall for UDP 500/4500/1701."
		);
		self::pair(
			$r,
			'msg.purchase_failed',
			'messages',
			"⚠️ در آماده‌سازی سرویس مشکلی پیش آمد.\n➖➖➖➖➖➖➖➖\nلطفاً چند دقیقه دیگر دوباره تلاش کنید یا با پشتیبانی تماس بگیرید.",
			"⚠️ We could not finish provisioning your service.\n➖➖➖➖➖➖➖➖\nTry again in a few minutes or contact support."
		);
		self::pair( $r, 'msg.renewed', 'messages', '✅ تمدید خودکار سرویس «{remark}» انجام شد.', '✅ Auto-renewed service «{remark}».' );
		self::pair(
			$r,
			'msg.renew_failed_balance',
			'messages',
			'❌ تمدید خودکار ناموفق بود: موجودی کیف پول کافی نیست.',
			'❌ Auto-renew failed: insufficient wallet balance.'
		);
		self::pair(
			$r,
			'msg.panel_unreachable',
			'messages',
			'⚠️ ارتباط با سرور برقرار نشد. لطفاً بعداً دوباره تلاش کنید.',
			'⚠️ Could not reach the server. Please try again later.'
		);
		self::pair(
			$r,
			'msg.rate_limited',
			'messages',
			'⏳ تعداد درخواست شما زیاد شد. چند دقیقه بعد دوباره تلاش کنید.',
			'⏳ Too many requests. Please try again in a few minutes.'
		);
		self::pair(
			$r,
			'msg.referral_bonus_wallet',
			'messages',
			"💰 مبلغ {amount_toman} تومان پورسانت معرفی از خرید «{buyer_label}» به کیف پول شما واریز شد.\n{referrer_first}",
			"💰 Referral bonus {amount_toman} Toman from «{buyer_label}» was credited to your wallet.\n{referrer_first}"
		);
		self::pair(
			$r,
			'msg.dashboard_wallet_credit',
			'messages',
			"💰 به موجودی کیف پول شما {amount} تومان افزوده شد.\n➖➖➖➖➖➖➖➖\nمانده فعلی: {balance} تومان.",
			"💰 {amount} Toman was added to your wallet.\n➖➖➖➖➖➖➖➖\nNew balance: {balance} Toman."
		);
		self::pair(
			$r,
			'msg.dashboard_wallet_debit',
			'messages',
			"💰 از موجودی کیف پول شما {amount} تومان کسر شد.\n➖➖➖➖➖➖➖➖\nمانده فعلی: {balance} تومان.",
			"💰 {amount} Toman was deducted from your wallet.\n➖➖➖➖➖➖➖➖\nNew balance: {balance} Toman."
		);
		self::pair(
			$r,
			'msg.cron_ip_distinct_warn',
			'messages',
			"⚠️ سرویس «{remark}»\n🧒 یعنی چی؟ تعداد آدرس/IP متفاوتی که پنل برای این اشتراک ثبت کرده بالا رفته است.\n📌 الان حدود {n_ip} IP متمایز ثبت شده است.\n📌 سقف اسلات این اشتراک {lim} است (آستانهٔ هشدار حداقل {need} IP).\n✋ اگر لازم دارید از منوی همان سرویس «افزایش کاربر» را بزنید.",
			"⚠️ Service «{remark}»\n🧒 What does this mean? The panel recorded more distinct IPs for this subscription.\n📌 About {n_ip} distinct IPs seen.\n📌 This plan allows {lim} slots (warning threshold at least {need} IPs).\n✋ If needed, use “Add users” from that service menu."
		);
		self::pair(
			$r,
			'btn.admin.delete_service_soft',
			'buttons',
			'🗑 حذف از لیست ربات (غیرفعال‌سازی)',
			'🗑 Remove from bot list (deactivate)'
		);
		self::pair( $r, 'msg.no_active_services', 'messages', '🧰 سرویس فعالی ندارید.', '🧰 You have no active services.' );
		self::pair(
			$r,
			'msg.lang_usage',
			'messages',
			"🔤 برای عوض کردن زبان بفرستید:\n/lang fa یا /lang en",
			"🔤 Send:\n/lang fa or /lang en"
		);
		self::pair( $r, 'msg.lang_changed', 'messages', '✅ زبان رابط به‌روز شد.', '✅ Interface language updated.' );
		self::pair( $r, 'msg.admin_panel_enabled', 'messages', '🔐 پنل مدیریت فعال شد.', '🔐 Admin panel enabled.' );
		self::pair( $r, 'msg.admin_exit_to_user_menu', 'messages', '👋 به منوی کاربر بازگشتید.', '👋 Back to the user menu.' );
		self::pair( $r, 'msg.pick_service_inline', 'messages', '🧰 سرویس خود را انتخاب کنید:', '🧰 Pick your service:' );
		self::pair( $r, 'msg.use_reply_buttons', 'messages', 'ℹ️ از دکمه‌های پایین استفاده کنید.', 'ℹ️ Please use the buttons below.' );
		self::pair( $r, 'msg.start_first', 'messages', '⛔ ابتدا /start را بزنید.', '⛔ Please tap /start first.' );
		self::pair( $r, 'msg.blocked', 'messages', '⛔ دسترسی شما مسدود است.', '⛔ Your access is blocked.' );
		// Bot UI Studio (admin surfaces).
		self::pair( $r, 'btn.admin.bulk_short', 'buttons', '➕ گروهی', '➕ Bulk' );
		self::pair( $r, 'btn.admin.nav.catalog', 'buttons', '⚙️ تنظیمات ربات', '⚙️ Bot settings' );
		self::pair( $r, 'btn.admin.cat.plan_cats', 'buttons', '📂 دسته پلن', '📂 Plan categories' );
		self::pair( $r, 'btn.admin.cat.plans', 'buttons', '📋 پلن‌ها', '📋 Plans' );
		self::pair( $r, 'btn.admin.cat.cards', 'buttons', '💳 کارت‌ها', '💳 Cards' );
		self::pair( $r, 'btn.admin.cat.panel', 'buttons', '🖥 پنل', '🖥 Panel' );
		self::pair( $r, 'btn.admin.cat.l2tp', 'buttons', '🔌 L2TP', '🔌 L2TP' );
		self::pair( $r, 'btn.admin.cat.config', 'buttons', '🔗 کانفیگ', '🔗 Configs' );
		self::pair( $r, 'btn.admin.cat.crypto', 'buttons', '₿ کریپتو', '₿ Crypto' );
		self::pair( $r, 'btn.admin.cat.bots', 'buttons', '🤖 ربات‌ها', '🤖 Bots' );
		self::pair( $r, 'btn.admin.adv.general', 'buttons', '⚙️ عمومی', '⚙️ General' );
		self::pair( $r, 'btn.admin.adv.notif', 'buttons', '🔔 نوتیف', '🔔 Notifications' );
		self::pair( $r, 'btn.admin.adv.texts', 'buttons', '📝 متن‌ها', '📝 Texts' );
		self::pair( $r, 'btn.admin.adv.logs', 'buttons', '📜 لاگ', '📜 Logs' );
		self::pair( $r, 'btn.admin.adv.broadcast', 'buttons', '📣 گزارش همگانی', '📣 Broadcast report' );
		self::pair( $r, 'btn.admin.wiz.gen_at', 'buttons', '📥 ادمین TG', '📥 Admin TG' );
		self::pair( $r, 'btn.admin.wiz.gen_ab', 'buttons', '📥 ادمین Bl', '📥 Admin Bale' );
		self::pair( $r, 'btn.admin.wiz.gen_pp', 'buttons', '📄 ID پورتال', '📄 Portal page ID' );
		self::pair( $r, 'btn.admin.wiz.gen_dp', 'buttons', '📦 پلن پیش‌فرض سرویس', '📦 Default plan' );
		self::pair( $r, 'btn.admin.wiz.bot_tt', 'buttons', 'tok TG', 'tok TG' );
		self::pair( $r, 'btn.admin.wiz.bot_bt', 'buttons', 'tok Bl', 'tok Bale' );
		self::pair( $r, 'btn.admin.wiz.bot_ts', 'buttons', 'wh sec TG', 'wh sec TG' );
		self::pair( $r, 'btn.admin.wiz.bot_bs', 'buttons', 'wh sec Bl', 'wh sec Bale' );
		self::pair( $r, 'btn.admin.wiz.bot_th', 'buttons', 'hdr', 'hdr' );
		self::pair( $r, 'btn.admin.wiz.bot_bw', 'buttons', 'Bale $', 'Bale $' );
		self::pair( $r, 'btn.admin.op.getme', 'buttons', 'getMe', 'getMe' );
		self::pair( $r, 'btn.admin.op.wh_tg', 'buttons', 'Set WH TG', 'Set WH TG' );
		self::pair( $r, 'btn.admin.op.wh_bl', 'buttons', 'Set WH Bl', 'Set WH Bale' );
		self::pair( $r, 'btn.admin.wiz.pan_u', 'buttons', 'URL', 'URL' );
		self::pair( $r, 'btn.admin.wiz.pan_n', 'buttons', 'User', 'User' );
		self::pair( $r, 'btn.admin.wiz.pan_p', 'buttons', 'Pass', 'Pass' );
		self::pair( $r, 'btn.admin.wiz.pan_a', 'buttons', 'API', 'API' );
		self::pair( $r, 'btn.admin.wiz.pan_l', 'buttons', 'Log sec', 'Login secret' );
		self::pair( $r, 'btn.admin.wiz.pan_s', 'buttons', 'Sub URL', 'Sub URL' );
		self::pair( $r, 'btn.admin.op.pan_test', 'buttons', '🔬 تست اتصال', '🔬 Test connection' );
		self::pair( $r, 'btn.admin.wiz.not_l', 'buttons', '٪ کمی', '% Low traffic' );
		self::pair( $r, 'btn.admin.wiz.not_e', 'buttons', 'روز هشدار', 'Expiry days' );
		self::pair( $r, 'btn.admin.wiz.not_d', 'buttons', 'سقف کاربر', 'Slots' );
		self::pair( $r, 'btn.admin.wiz.not_p', 'buttons', 'قیمت+کاربر', 'Price per user' );
		self::pair( $r, 'btn.admin.wiz.cry_ak', 'buttons', '₿ API', '₿ API' );
		self::pair( $r, 'btn.admin.wiz.cry_in', 'buttons', '₿ IPN', '₿ IPN' );
		self::pair( $r, 'btn.admin.wiz.cry_cu', 'buttons', '₿ Cur', '₿ Cur' );
		self::pair( $r, 'btn.admin.hub.crypto_ipn_path', 'buttons', '🔄 مسیر IPN', '🔄 IPN path' );
		self::pair( $r, 'btn.admin.bulk.d1', 'buttons', '+۱ روز', '+1 day' );
		self::pair( $r, 'btn.admin.bulk.d7', 'buttons', '+۷ روز', '+7 days' );
		self::pair( $r, 'btn.admin.bulk.d30', 'buttons', '+۳۰ روز', '+30 days' );
		self::pair( $r, 'btn.admin.bulk.g1', 'buttons', '+۱ GB', '+1 GB' );
		self::pair( $r, 'btn.admin.bulk.g5', 'buttons', '+۵ GB', '+5 GB' );
		self::pair( $r, 'btn.admin.bulk.confirm_text', 'buttons', '📝 تأیید متنی گروهی', '📝 Text bulk confirm' );
		self::pair( $r, 'btn.admin.inbound.list', 'buttons', '📋 لیست Inbound', '📋 Inbound list' );
		self::pair( $r, 'btn.admin.hub.toggle_enabled', 'buttons', '🔛 ربات فعال/غیر', '🔛 Bot on/off' );
		self::pair( $r, 'btn.admin.hub.toggle_test', 'buttons', '🧪 تست فعال/غیر', '🧪 Test acct on/off' );
		return $r;
	}
}
