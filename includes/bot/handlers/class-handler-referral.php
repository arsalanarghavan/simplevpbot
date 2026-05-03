<?php
/**
 * Referral / earn screen for users.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Handler_Referral
 */
class SimpleVPBot_Handler_Referral {

	/**
	 * Format percent for labels (no trailing .00).
	 *
	 * @param float $pct Percent value.
	 * @return string ASCII digits (then pass through SimpleVPBot_Bot_Persian_Text::digits_to_fa if needed).
	 */
	private static function format_percent_ascii( $pct ) {
		$p = (float) $pct;
		if ( $p <= 0 ) {
			return '0';
		}
		if ( abs( $p - round( $p ) ) < 0.005 ) {
			return (string) (int) round( $p );
		}
		return rtrim( rtrim( number_format( $p, 2, '.', '' ), '0' ), '.' );
	}

	/**
	 * Show referral stats and links (reply keyboard entry).
	 *
	 * @param string $platform telegram|bale.
	 * @param int    $chat_id  Chat id.
	 * @param object $user     svp_users row.
	 */
	public static function show( $platform, $chat_id, $user ) {
		$enabled = (bool) SimpleVPBot_Settings::get( 'referral_enabled', false );
		$pct     = (float) SimpleVPBot_Settings::get( 'referral_percent', 0 );
		$base    = max( 0.0, (float) SimpleVPBot_Settings::get( 'referral_example_base_toman', 170000 ) );
		$ex_n    = max( 1, (int) SimpleVPBot_Settings::get( 'referral_example_invite_count', 10 ) );

		$pct_ascii = self::format_percent_ascii( $pct );
		$pct_fa    = SimpleVPBot_Bot_Persian_Text::digits_to_fa( $pct_ascii );
		$comm      = (int) round( $base * $pct / 100.0 );
		$total_ex  = $comm * $ex_n;

		$t  = "💰 درآمد واقعی از معرفی سرویس‌مون!\n\n";
		if ( ! $enabled ) {
			$t .= "⏸️ فعلاً سیستم دعوت از طرف مدیریت غیرفعال است؛ اطلاعات زیر فقط برای آشنایی با نحوهٔ کار است.\n\n";
		}
		$t .= "اگه از سرویس راضی‌ای و دلت می‌خواد بدون هزینه تمدید کنی یا حتی پول دربیاری، این فرصت واسه توئه👇\n\n";
		$t .= "🎯 با دعوت فقط چند نفر، سرویس رایگان بگیر یا درآمد نقدی داشته باش!\n\n";
		$t .= "چطوری؟\n";
		$t .= "فقط لینک اختصاصی خودتو برای دوستات یا گروه‌هایی که داخلشی بفرست!\n";
		$t .= "هر خرید = درآمد برای تو!\n\n";
		$t .= '🔹 ' . $pct_fa . "٪ پورسانت دائمی از هر خرید زیرمجموعه\n";
		$t .= "🔹 بدون سقف زمانی یا محدودیت تعداد\n";
		$t .= "🔹 درآمدت مستقیم توی کیف پولت ذخیره میشه\n\n";
		$t .= "🟢 با موجودی کیف پول می‌تونی:\n";
		$t .= "1️⃣ اشتراکت رو مجانی تمدید کنی\n";
		$t .= "2️⃣ یا درخواست برداشت بزنی و 💵 پول نقد بگیری!\n\n";
		$t .= "====================\n";
		$t .= "📌 مثال ساده:\n";
		$t .= 'کمترین خرید = ' . SimpleVPBot_Bot_Persian_Text::format_toman_fa( $base ) . " تومان\n";
		$t .= $pct_fa . '٪ پورسانت = ' . SimpleVPBot_Bot_Persian_Text::format_toman_fa( (float) $comm ) . " تومان\n";
		$t .= 'فقط با ' . SimpleVPBot_Bot_Persian_Text::digits_to_fa( (string) $ex_n ) . ' نفر =  ' . SimpleVPBot_Bot_Persian_Text::format_toman_fa( (float) $total_ex ) . " تومان تو کیف پولت!\n\n";
		$t .= "یعنی نه تنها رایگان استفاده می‌کنی، بلکه سود هم داری!\n\n";
		$t .= "====================\n";
		$t .= "🎁 لینک دعوت مخصوص شما آماده‌ست!👇\n\n";

		$tg_link = SimpleVPBot_Referral_Service::invite_link_for_platform( 'telegram', (int) $user->id );
		$bl_link = SimpleVPBot_Referral_Service::invite_link_for_platform( 'bale', (int) $user->id );

		$t .= '📎 شناسهٔ شما: #' . (int) $user->id . "\n";
		if ( $tg_link !== '' ) {
			$t .= "تلگرام:\n" . $tg_link . "\n\n";
		} else {
			$t .= "تلگرام: نام کاربری ربات در تنظیمات (telegram_bot_username) را تنظیم کنید؛ فعلاً:\n/start ref_" . (int) $user->id . "\n\n";
		}
		if ( $bl_link !== '' ) {
			$t .= "بله:\n" . $bl_link . "\n";
		} else {
			$t .= "بله: bale_bot_username را در تنظیمات وارد کنید؛ فعلاً:\n/start ref_" . (int) $user->id . "\n";
		}

		SimpleVPBot_Bot_Runtime::send_message( $platform, (int) $chat_id, $t );
	}
}
