<?php
/**
 * Portal content (HMAC user): shown at /info; shortcode is optional/legacy.
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Shortcode_Portal
 */
class SimpleVPBot_Shortcode_Portal {

	/**
	 * @param array<string, string> $atts Shortcode atts.
	 * @return string
	 */
	public static function render( $atts = array() ) {
		unset( $atts );
		return self::render_content();
	}

	/**
	 * HTML for portal: designed card with QR, stats, config, app deeplinks.
	 *
	 * @return string
	 */
	public static function render_content() {
		$adm_uid = SimpleVPBot_Portal_Link::current_admin_user_id();
		if ( $adm_uid > 0 && class_exists( 'SimpleVPBot_Portal_Admin' ) ) {
			return SimpleVPBot_Portal_Admin::render( $adm_uid );
		}
		$uid = SimpleVPBot_Portal_Link::current_user_id();
		if ( $uid < 1 ) {
			return '<div class="svp-error">' . esc_html__( 'لینک معتبر نیست یا منقضی شده است. از ربات دوباره لینک بگیرید.', 'simplevpbot' ) . '</div>';
		}
		$user = SimpleVPBot_Model_User::find( $uid );
		if ( ! $user || 'approved' !== (string) $user->status ) {
			return '<div class="svp-error">' . esc_html__( 'دسترسی ندارید.', 'simplevpbot' ) . '</div>';
		}
		$sid = SimpleVPBot_Portal_Link::current_service_id();
		if ( $sid > 0 ) {
			$svc = SimpleVPBot_Model_Service::find( $sid );
			if ( ! $svc ) {
				return '<div class="svp-error">' . esc_html__( 'این اشتراک در سیستم ثبت نیست یا حذف شده است.', 'simplevpbot' ) . '</div>';
			}
			// HMAC already binds (user_id, service_id); do not require svc.user_id === uid — DB drift or
			// transfers made that check falsely reject valid signed links for customers.
			if ( (int) $svc->user_id !== (int) $uid && class_exists( 'SimpleVPBot_Logger' ) ) {
				SimpleVPBot_Logger::warning(
					'portal: signed uid differs from service.user_id (still showing; link HMAC is authoritative)',
					array(
						'service_id' => $sid,
						'signed_uid' => (int) $uid,
						'db_user_id' => (int) $svc->user_id,
					)
				);
			}
			$list = array( $svc );
		} else {
			$list = SimpleVPBot_Model_Service::by_user( $uid );
		}
		if ( empty( $list ) ) {
			return '<div class="svp-empty">' . esc_html__( 'سرویسی ثبت نشده است.', 'simplevpbot' ) . '</div>';
		}

		$cards = array();
		foreach ( $list as $svc ) {
			$chunk = self::render_service_card( $svc, (int) $uid );
			if ( '' !== $chunk ) {
				$cards[] = $chunk;
			}
		}
		if ( empty( $cards ) ) {
			$msg = $sid > 0
				? __( 'این اشتراک دیگر وجود ندارد یا از پنل حذف شده است.', 'simplevpbot' )
				: __( 'سرویسی ثبت نشده است.', 'simplevpbot' );
			return '<div class="svp-empty">' . esc_html( $msg ) . '</div>';
		}

		ob_start();
		echo '<div class="svp-shell">';
		echo '<div class="svp-header-brand"><strong>' . esc_html( get_bloginfo( 'name' ) ) . '</strong>' . esc_html__( 'پنل مدیریت اشتراک شما', 'simplevpbot' ) . '</div>';
		foreach ( $cards as $chunk ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo $chunk;
		}
		echo '</div>';
		return (string) ob_get_clean();
	}

	/**
	 * Card surface + pill classes from portal usage row (Xray + L2TP).
	 *
	 * @param array<string, mixed> $data Row from handler.
	 * @return array{card_class:string,pill_class:string,pill_label:string}
	 */
	private static function portal_card_visual_state( array $data ) {
		$enabled = ! isset( $data['panel_client_enabled'] ) || (int) $data['panel_client_enabled'] !== 0;
		$date_e  = ! empty( $data['date_expired'] );
		$vol_e   = ! empty( $data['volume_exhausted'] );
		$exhausted = ( $date_e || $vol_e ) && $enabled;
		$label     = isset( $data['status_label'] ) ? (string) $data['status_label'] : ( isset( $data['status'] ) ? (string) $data['status'] : '' );
		if ( '' === $label ) {
			$label = __( 'فعال', 'simplevpbot' );
		}
		if ( ! $enabled ) {
			return array(
				'card_class' => 'svp-card--state-disabled',
				'pill_class' => 'svp-pill--muted',
				'pill_label' => $label,
			);
		}
		if ( $exhausted ) {
			return array(
				'card_class' => 'svp-card--state-exhausted',
				'pill_class' => 'svp-pill--bad',
				'pill_label' => $label,
			);
		}
		return array(
			'card_class' => '',
			'pill_class' => 'svp-pill--ok',
			'pill_label' => $label,
		);
	}

	/**
	 * One service card markup.
	 *
	 * @param object $svc     Service row.
	 * @param int    $user_id svp user.
	 * @return string
	 */
	private static function render_service_card( $svc, $user_id = 0 ) {
		if ( SimpleVPBot_Model_Service::is_l2tp( $svc ) ) {
			return self::render_l2tp_card( $svc, (int) $user_id );
		}
		$data = SimpleVPBot_Handler_Service::get_portal_service_data( $svc, (int) $user_id );
		if ( ! empty( $data['_deleted'] ) ) {
			return '';
		}
		$sub_url = (string) ( $data['subscription_url'] ?? '' );
		$uris    = isset( $data['config_uris'] ) && is_array( $data['config_uris'] ) ? $data['config_uris'] : array();
		$cfg_uri = (string) ( $data['config_uri'] ?? ( ! empty( $uris ) ? $uris[0] : '' ) );
		$primary = (string) ( $data['primary_link'] ?? '' );
		$portal  = (string) ( $data['portal_url'] ?? '' );
		$remark = (string) ( $data['remark'] ?? $svc->remark );
		$sub_id = (string) ( $data['sub_id'] ?? '' );

		$qr_payload = '' !== $cfg_uri ? $cfg_uri : ( '' !== $primary ? $primary : $portal );
		$qr_src = '';
		if ( $qr_payload && class_exists( 'SimpleVPBot_Qr' ) && SimpleVPBot_Qr::is_available() ) {
			$bytes = SimpleVPBot_Qr::png_bytes( $qr_payload );
			if ( $bytes ) {
				$qr_src = 'data:image/png;base64,' . base64_encode( $bytes ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			}
		}

		$viz        = self::portal_card_visual_state( $data );
		$card_extra = trim( (string) $viz['card_class'] );
		$article_cls = trim( 'svp-card ' . $card_extra );

		$out  = '<article class="' . esc_attr( $article_cls ) . '" data-sub="' . esc_attr( $sub_url ? $sub_url : $primary ) . '" data-cfg="' . esc_attr( $cfg_uri ) . '" data-remark="' . esc_attr( $remark ) . '">';
		$out .= '<header class="svp-card__head">';
		$out .= '<span class="svp-chip">' . esc_html__( 'اطلاعات اشتراک', 'simplevpbot' ) . '</span>';
		$out .= '<span class="svp-subid">' . esc_html( $sub_id ) . '</span>';
		$out .= '<button type="button" class="svp-gear" aria-label="' . esc_attr__( 'تنظیمات', 'simplevpbot' ) . '">' . self::svg_gear() . '</button>';
		$out .= '<div class="svp-gear__menu">';
		if ( $sub_id ) {
			$out .= '<button type="button" data-copy="' . esc_attr( $sub_id ) . '">' . esc_html__( 'کپی شناسه اشتراک', 'simplevpbot' ) . '</button>';
		}
		if ( $portal ) {
			$out .= '<a class="button button-small" href="' . esc_url( $portal ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'پنل وب', 'simplevpbot' ) . '</a> ';
		}
		if ( $cfg_uri ) {
			$out .= '<button type="button" data-copy="' . esc_attr( $cfg_uri ) . '">' . esc_html__( 'کپی کانفیگ', 'simplevpbot' ) . '</button>';
		}
		$out .= '</div></header>';

		if ( $qr_src ) {
			$out .= '<div class="svp-qr"><img src="' . esc_attr( $qr_src ) . '" width="220" height="220" alt="' . esc_attr__( 'کد QR', 'simplevpbot' ) . '"/></div>';
		}

		$out .= '<dl class="svp-rows">';
		$out .= self::row( __( 'شناسه اشتراک', 'simplevpbot' ), esc_html( $sub_id ), true );
		$out .= self::row( __( 'وضعیت', 'simplevpbot' ), '<span class="svp-pill ' . esc_attr( (string) $viz['pill_class'] ) . '">' . esc_html( (string) $viz['pill_label'] ) . '</span>' );
		$out .= self::row( __( 'دانلود', 'simplevpbot' ), esc_html( (string) ( $data['down_h'] ?? '0 B' ) ), true );
		$out .= self::row( __( 'آپلود', 'simplevpbot' ), esc_html( (string) ( $data['up_h'] ?? '0 B' ) ), true );
		$out .= self::row( __( 'مصرف', 'simplevpbot' ), esc_html( (string) ( $data['used_h'] ?? '0 B' ) ), true );
		$out .= self::row( __( 'حجم کل', 'simplevpbot' ), esc_html( (string) ( $data['total_quota'] ?? '➖' ) ), true );
		$out .= self::row( __( 'باقی‌مانده', 'simplevpbot' ), esc_html( (string) ( $data['remained_h'] ?? '➖' ) ), true );
		$out .= self::row( __( 'آخرین اتصال', 'simplevpbot' ), esc_html( (string) ( $data['last_online_fa'] ?? '➖' ) ), true );
		$out .= self::row( __( 'انقضا', 'simplevpbot' ), esc_html( (string) ( $data['expiry_fa'] ?? '➖' ) ), true );
		$out .= '</dl>';

		if ( ! empty( $uris ) ) {
			$idx = 1;
			foreach ( $uris as $uri ) {
				$uri = (string) $uri;
				if ( '' === $uri ) {
					continue;
				}
				$tag = count( $uris ) > 1 ? ( $remark . ' · ' . $idx ) : $remark;
				$out .= '<div class="svp-cfg">';
				$out .= '<span class="svp-cfg__tag">' . esc_html( $tag ) . '</span>';
				$out .= '<code class="svp-cfg__code">' . esc_html( $uri ) . '</code>';
				$out .= '<button type="button" class="svp-cfg__copy" data-copy="' . esc_attr( $uri ) . '">' . self::svg_copy() . '<span>' . esc_html__( 'کپی', 'simplevpbot' ) . '</span></button>';
				$out .= '</div>';
				$idx++;
			}
		} else {
			$out .= '<div class="svp-cfg">';
			$out .= '<span class="svp-cfg__tag">' . esc_html__( 'اتصال', 'simplevpbot' ) . '</span>';
			$msg = '' !== $sub_url
				? esc_html__( 'کانفیگ‌ها از لینک اشتراک گرفته می‌شوند. روی «کپی لینک اشتراک» بزنید و داخل برنامهٔ اتصال (v2rayNG / Hiddify و غیره) وارد کنید.', 'simplevpbot' )
				: esc_html__( 'هنوز لینک اشتراک برای شما روشن نشده؛ از ادمین بخواهید بررسی کند.', 'simplevpbot' );
			$out .= '<code class="svp-cfg__code">' . $msg . '</code>';
			$out .= '</div>';
		}

		$out .= self::render_apps_block();
		$out .= '</article>';
		return $out;
	}

	/**
	 * L2TP service card (no QR; credentials + copy buttons).
	 *
	 * @param object $svc     Service.
	 * @param int    $user_id svp user.
	 * @return string
	 */
	private static function render_l2tp_card( $svc, $user_id = 0 ) {
		$data   = SimpleVPBot_Handler_Service::get_portal_service_data( $svc, (int) $user_id );
		$remark = (string) ( $data['remark'] ?? $svc->remark );
		$sub_id = (string) ( $data['sub_id'] ?? '' );
		$l2tp   = is_array( $data['l2tp'] ?? null ) ? $data['l2tp'] : array();
		$host    = (string) ( $l2tp['host'] ?? '' );
		$psk     = (string) ( $l2tp['psk'] ?? '' );
		$l2_user = (string) ( $l2tp['username'] ?? '' );
		$pass    = (string) ( $l2tp['password'] ?? '' );

		$viz         = self::portal_card_visual_state( $data );
		$article_cls = trim( 'svp-card svp-card--l2tp ' . (string) $viz['card_class'] );

		$out  = '<article class="' . esc_attr( $article_cls ) . '">';
		$out .= '<header class="svp-card__head">';
		$out .= '<span class="svp-chip">' . esc_html__( 'اتصال L2TP / IPsec', 'simplevpbot' ) . '</span>';
		$out .= '<span class="svp-subid">' . esc_html( $sub_id ) . '</span>';
		$out .= '</header>';

		$out .= '<dl class="svp-rows">';
		$out .= self::row( __( 'نوع اتصال', 'simplevpbot' ), esc_html__( 'L2TP/IPsec با PSK', 'simplevpbot' ) );
		$out .= self::row( __( 'وضعیت', 'simplevpbot' ), '<span class="svp-pill ' . esc_attr( (string) $viz['pill_class'] ) . '">' . esc_html( (string) $viz['pill_label'] ) . '</span>' );
		$out .= self::row( __( 'سرور', 'simplevpbot' ), esc_html( $host ), true );
		$out .= self::row( __( 'PSK', 'simplevpbot' ), esc_html( $psk ), true );
		$out .= self::row( __( 'نام کاربری', 'simplevpbot' ), esc_html( $l2_user ), true );
		$out .= self::row( __( 'رمز عبور', 'simplevpbot' ), esc_html( $pass ), true );
		$out .= self::row( __( 'مصرف', 'simplevpbot' ), esc_html( (string) ( $data['used_h'] ?? '➖' ) ), true );
		$out .= self::row( __( 'حجم کل', 'simplevpbot' ), esc_html( (string) ( $data['total_quota'] ?? '➖' ) ), true );
		$out .= self::row( __( 'انقضا', 'simplevpbot' ), esc_html( (string) ( $data['expiry_fa'] ?? '➖' ) ), true );
		$out .= '</dl>';

		$out .= '<div class="svp-cfg">';
		$out .= '<span class="svp-cfg__tag">' . esc_html( $remark ) . '</span>';
		$copies = array(
			array( 'label' => __( 'کپی سرور', 'simplevpbot' ),       'val' => $host ),
			array( 'label' => __( 'کپی PSK', 'simplevpbot' ),        'val' => $psk ),
			array( 'label' => __( 'کپی نام کاربری', 'simplevpbot' ), 'val' => $l2_user ),
			array( 'label' => __( 'کپی رمز', 'simplevpbot' ),       'val' => $pass ),
		);
		foreach ( $copies as $b ) {
			if ( '' === (string) $b['val'] ) {
				continue;
			}
			$out .= '<button type="button" class="svp-cfg__copy" data-copy="' . esc_attr( (string) $b['val'] ) . '">' . self::svg_copy() . '<span>' . esc_html( (string) $b['label'] ) . '</span></button>';
		}
		$out .= '</div>';

		$out .= '<div class="svp-apps">';
		$out .= '<div class="svp-apps__col"><div class="svp-apps__menu">';
		$out .= '<div class="svp-app"><span class="svp-app__icon">W</span><div class="svp-app__name">' . esc_html__( 'ویندوز', 'simplevpbot' ) . '<small>' . esc_html__( 'تنظیمات ← شبکه و اینترنت ← VPN ← افزودن VPN، نوع را L2TP/IPsec with pre-shared key انتخاب کنید.', 'simplevpbot' ) . '</small></div></div>';
		$out .= '<div class="svp-app"><span class="svp-app__icon">i</span><div class="svp-app__name">' . esc_html__( 'آی‌اواس', 'simplevpbot' ) . '<small>' . esc_html__( 'تنظیمات ← عمومی ← VPN ← افزودن پیکربندی VPN (نوع L2TP).', 'simplevpbot' ) . '</small></div></div>';
		$out .= '<div class="svp-app"><span class="svp-app__icon">A</span><div class="svp-app__name">' . esc_html__( 'اندروید', 'simplevpbot' ) . '<small>' . esc_html__( 'تنظیمات ← اتصالات ← VPN ← افزودن VPN (نوع L2TP/IPsec PSK).', 'simplevpbot' ) . '</small></div></div>';
		$out .= '</div></div></div>';

		$out .= '</article>';
		return $out;
	}

	/**
	 * Single stat row.
	 *
	 * @param string $label Label.
	 * @param string $value HTML-safe value (use esc_html beforehand).
	 * @param bool   $ltr   Render value in LTR (for numbers/ids).
	 * @return string
	 */
	private static function row( $label, $value, $ltr = false ) {
		return '<div><dt>' . esc_html( $label ) . '</dt><dd' . ( $ltr ? ' class="ltr"' : '' ) . '>' . $value . '</dd></div>';
	}

	/**
	 * Android/iOS bottom apps block.
	 *
	 * @return string
	 */
	private static function render_apps_block() {
		$android = self::android_apps();
		$ios     = self::ios_apps();
		$out     = '<nav class="svp-apps">';

		$out .= '<div class="svp-apps__col">';
		$out .= '<button type="button" class="svp-apps__btn">' . self::svg_android() . '<span>' . esc_html__( 'اندروید', 'simplevpbot' ) . '</span>' . self::svg_chev() . '</button>';
		$out .= '<div class="svp-apps__menu">' . self::render_app_items( $android ) . '</div>';
		$out .= '</div>';

		$out .= '<div class="svp-apps__col">';
		$out .= '<button type="button" class="svp-apps__btn">' . self::svg_apple() . '<span>' . esc_html__( 'آی‌اواس', 'simplevpbot' ) . '</span>' . self::svg_chev() . '</button>';
		$out .= '<div class="svp-apps__menu">' . self::render_app_items( $ios ) . '</div>';
		$out .= '</div>';

		$out .= '</nav>';
		return $out;
	}

	/**
	 * @param array<int, array<string, string>> $items Items.
	 * @return string
	 */
	private static function render_app_items( $items ) {
		$out = '';
		foreach ( $items as $app ) {
			$out .= '<div class="svp-app">';
			$out .= '<span class="svp-app__icon">' . esc_html( mb_substr( (string) $app['name'], 0, 1 ) ) . '</span>';
			$out .= '<div class="svp-app__name">' . esc_html( (string) $app['name'] ) . '<small>' . esc_html( (string) ( $app['note'] ?? '' ) ) . '</small></div>';
			if ( ! empty( $app['deeplink'] ) ) {
				$out .= '<button type="button" class="svp-app__action svp-app__action--primary" data-deeplink="' . esc_attr( (string) $app['deeplink'] ) . '">' . esc_html__( 'افزودن خودکار', 'simplevpbot' ) . '</button>';
			}
			if ( ! empty( $app['url'] ) ) {
				$out .= '<a class="svp-app__action" href="' . esc_url( (string) $app['url'] ) . '" target="_blank" rel="noopener">' . esc_html__( 'دانلود اپ', 'simplevpbot' ) . '</a>';
			}
			$out .= '</div>';
		}
		return $out;
	}

	/**
	 * @return array<int, array<string, string>>
	 */
	private static function android_apps() {
		return array(
			array(
				'name'     => 'v2rayNG',
				'note'     => __( 'افزودن اشتراک یک‌کلیکی', 'simplevpbot' ),
				'deeplink' => 'v2rayng',
				'url'      => SimpleVPBot_Texts::get( 'app.v2rayng', 'https://github.com/2dust/v2rayNG/releases' ),
			),
			array(
				'name'     => 'Hiddify',
				'note'     => __( 'ساده، سریع', 'simplevpbot' ),
				'deeplink' => 'hiddify',
				'url'      => 'https://hiddify.com/app/',
			),
			array(
				'name'     => 'NekoBox',
				'note'     => __( 'پیشرفته', 'simplevpbot' ),
				'deeplink' => 'nekobox',
				'url'      => 'https://github.com/MatsuriDayo/NekoBoxForAndroid/releases',
			),
			array(
				'name'     => 'Clash Meta',
				'note'     => __( 'پشتیبانی پروتکل‌های متعدد', 'simplevpbot' ),
				'deeplink' => 'clashmeta',
				'url'      => 'https://github.com/MetaCubeX/ClashMetaForAndroid/releases',
			),
		);
	}

	/**
	 * @return array<int, array<string, string>>
	 */
	private static function ios_apps() {
		return array(
			array(
				'name'     => 'Streisand',
				'note'     => __( 'رایگان در App Store', 'simplevpbot' ),
				'deeplink' => 'streisand',
				'url'      => 'https://apps.apple.com/app/streisand/id6450534064',
			),
			array(
				'name'     => 'Shadowrocket',
				'note'     => __( 'محبوب و پرکاربرد', 'simplevpbot' ),
				'deeplink' => 'shadowrocket',
				'url'      => SimpleVPBot_Texts::get( 'app.shadowrocket', 'https://apps.apple.com/app/shadowrocket/id932747118' ),
			),
			array(
				'name'     => 'Hiddify',
				'note'     => __( 'نصب آسان، اتصال یک‌کلیکی', 'simplevpbot' ),
				'deeplink' => 'hiddify',
				'url'      => 'https://apps.apple.com/app/hiddify-proxy-vpn/id6596777532',
			),
			array(
				'name'     => 'FairVPN',
				'note'     => __( 'رایگان', 'simplevpbot' ),
				'deeplink' => 'fair',
				'url'      => 'https://apps.apple.com/app/fairvpn/id1533873048',
			),
		);
	}

	/** Gear SVG. */
	private static function svg_gear() {
		return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1A1.7 1.7 0 0 0 9 19.4a1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1A1.7 1.7 0 0 0 4.6 9a1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1c0 .7.4 1.3 1 1.5a1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9c.3.6.8 1 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/></svg>';
	}

	/** Copy SVG. */
	private static function svg_copy() {
		return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>';
	}

	/** Chevron SVG. */
	private static function svg_chev() {
		return '<svg class="chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"/></svg>';
	}

	/** Android SVG. */
	private static function svg_android() {
		return '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.5 10.5c-.8 0-1.5.7-1.5 1.5v5c0 .8.7 1.5 1.5 1.5S19 17.8 19 17v-5c0-.8-.7-1.5-1.5-1.5zm-11 0c-.8 0-1.5.7-1.5 1.5v5c0 .8.7 1.5 1.5 1.5S8 17.8 8 17v-5c0-.8-.7-1.5-1.5-1.5zM9 21c0 .6.4 1 1 1h1v-3H9v2zm4 0c0 .6.4 1 1 1h1v-3h-2v2zM9 9v10h6V9H9zm6.4-5.4l.9-.9c.2-.2.2-.5 0-.7-.2-.2-.5-.2-.7 0l-1 1C13.9 2.7 12.9 2.5 12 2.5s-1.9.2-2.6.5l-1-1c-.2-.2-.5-.2-.7 0-.2.2-.2.5 0 .7l.9.9C7.4 4.5 7 6.2 7 8h10c0-1.8-.4-3.5-1.6-4.4zM10 6c-.3 0-.5-.2-.5-.5S9.7 5 10 5s.5.2.5.5S10.3 6 10 6zm4 0c-.3 0-.5-.2-.5-.5s.2-.5.5-.5.5.2.5.5-.2.5-.5.5z"/></svg>';
	}

	/** Apple SVG. */
	private static function svg_apple() {
		return '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M17.05 20.28c-.98.95-2.05.8-3.08.35-1.09-.46-2.09-.48-3.24 0-1.44.62-2.2.44-3.06-.35-4.87-5-4.15-12.62 1.39-12.9 1.35.07 2.29.74 3.08.8 1.18-.24 2.31-.93 3.57-.84 1.51.12 2.65.72 3.4 1.8-3.12 1.87-2.38 5.98.48 7.13-.57 1.5-1.31 2.99-2.54 4.01zM12.03 7.25c-.15-2.23 1.66-4.07 3.74-4.25.29 2.58-2.34 4.5-3.74 4.25z"/></svg>';
	}
}
