<?php
/**
 * Signed web admin shell (no WP login).
 *
 * @package SimpleVPBot
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SimpleVPBot_Portal_Admin
 */
class SimpleVPBot_Portal_Admin {

	/**
	 * HTML for admin portal (RTL Persian).
	 *
	 * @param int $admin_uid Validated svp_users.id.
	 * @return string
	 */
	public static function render( $admin_uid ) {
		$uid   = (int) $admin_uid;
		$nonce = wp_create_nonce( 'svp_portal_admin_' . $uid );
		$ajax  = admin_url( 'admin-ajax.php' );
		$ipn   = esc_html( SimpleVPBot_Crypto_Payment::ipn_callback_url() );
		ob_start();
		?>
		<div class="svp-admin" data-uid="<?php echo esc_attr( (string) $uid ); ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>" data-ajax="<?php echo esc_url( $ajax ); ?>">
			<h1 class="svp-admin__title">پنل مدیریت وب</h1>
			<p class="svp-admin__hint">دسترسی با لینک امضاشده از ربات. IPN خودکار کریپتو: <code class="svp-admin__code"><?php echo $ipn; ?></code></p>
			<section class="svp-admin__card">
				<h2>آمار و پنل‌ها</h2>
				<p class="svp-admin__hint">روز نمایش برای «حداکثر آنلاین» هر پنل؛ بقیهٔ شمارش‌ها لحظه‌ای است.</p>
				<div class="svp-admin__daynav" role="group" aria-label="روز آمار">
					<?php for ( $d = 0; $d <= 7; $d++ ) : ?>
						<button type="button" class="svp-btn svp-btn--small<?php echo 0 === $d ? ' is-active' : ''; ?>" data-svp-admin-op="stats" data-svp-stats-day="<?php echo esc_attr( (string) $d ); ?>"><?php echo 0 === $d ? 'امروز' : esc_html( '-' . $d ); ?></button>
					<?php endfor; ?>
				</div>
				<pre class="svp-admin__out" id="svp-adm-stats"></pre>
				<table class="svp-admin__table" id="svp-adm-stats-table" hidden>
					<thead><tr><th>پنل</th><th>Xray فعال</th><th>Xray منقضی</th><th>حداکثر آنلاین (روز انتخاب‌شده)</th></tr></thead>
					<tbody id="svp-adm-stats-tbody"></tbody>
				</table>
			</section>
			<section class="svp-admin__card">
				<h2>صف ثبت‌نام</h2>
				<p class="svp-admin__hint">۵ نفر در هر صفحه (جدیدترین اول). تأیید و رد فقط برای وضعیت «در انتظار».</p>
				<div id="svp-mem-root" class="svp-mem" data-tab="pending" data-offset="0">
					<div class="svp-admin__daynav" role="tablist">
						<button type="button" class="svp-btn svp-btn--small is-active" data-svp-mem-tab="pending">در انتظار</button>
						<button type="button" class="svp-btn svp-btn--small" data-svp-mem-tab="approved">تأییدشده</button>
						<button type="button" class="svp-btn svp-btn--small" data-svp-mem-tab="rejected">رد شده</button>
					</div>
					<p class="svp-admin__hint"><button type="button" class="svp-btn" data-svp-mem-refresh>بارگذاری / تازه‌سازی لیست</button>
						<button type="button" class="svp-btn" data-svp-mem-prev disabled>صفحه قبل</button>
						<button type="button" class="svp-btn" data-svp-mem-next disabled>صفحه بعد</button></p>
					<table class="svp-admin__table">
						<thead><tr><th>شناسه</th><th>کاربر</th><th>وضعیت</th><th>ثبت</th><th>عملیات</th></tr></thead>
						<tbody id="svp-mem-tbody"></tbody>
					</table>
					<div class="svp-mem-detail" id="svp-mem-detail-wrap" hidden>
						<h3>جزئیات</h3>
						<div id="svp-mem-detail-img"></div>
						<pre class="svp-admin__out" id="svp-mem-detail"></pre>
					</div>
				</div>
			</section>
			<section class="svp-admin__card">
				<h2>ساخت سرویس برای کاربر</h2>
				<label>شناسه کاربر (svp_users.id)<input type="number" id="svp-cr-uid" min="1" class="svp-admin__input"/></label>
				<label>شناسه پلن<input type="number" id="svp-cr-pid" min="1" class="svp-admin__input"/></label>
				<label>حجم (گیگ، برای پلن per-GB)<input type="number" id="svp-cr-gb" min="0" class="svp-admin__input" placeholder="0"/></label>
				<label>حالت
					<select id="svp-cr-mode" class="svp-admin__input">
						<option value="free">بدون پرداخت</option>
						<option value="wallet">کسر از کیف پول کاربر</option>
						<option value="invoice">فاکتور به کاربر</option>
					</select>
				</label>
				<button type="button" class="svp-btn svp-btn--primary" data-svp-admin-op="create_service">اجرای ساخت سرویس</button>
				<pre class="svp-admin__out" id="svp-adm-create"></pre>
			</section>
			<section class="svp-admin__card">
				<h2>تمدید سرویس</h2>
				<label>شناسه سرویس<input type="number" id="svp-rn-sid" min="1" class="svp-admin__input"/></label>
				<label>حالت
					<select id="svp-rn-mode" class="svp-admin__input">
						<option value="free">بدون پرداخت</option>
						<option value="wallet">کسر از کیف پول کاربر</option>
						<option value="invoice">فاکتور به کاربر</option>
					</select>
				</label>
				<button type="button" class="svp-btn svp-btn--primary" data-svp-admin-op="renew_service">تمدید</button>
				<pre class="svp-admin__out" id="svp-adm-renew"></pre>
			</section>
			<section class="svp-admin__card">
				<h2>افزایش حجم</h2>
				<label>شناسه سرویس<input type="number" id="svp-v-sid" min="1" class="svp-admin__input"/></label>
				<label>گیگ اضافه<input type="number" id="svp-v-gb" min="1" value="1" class="svp-admin__input"/></label>
				<label>حالت
					<select id="svp-v-mode" class="svp-admin__input">
						<option value="free">بدون پرداخت</option>
						<option value="wallet">کسر از کیف پول کاربر</option>
						<option value="invoice">فاکتور به کاربر</option>
					</select>
				</label>
				<button type="button" class="svp-btn svp-btn--primary" data-svp-admin-op="add_volume">اعمال</button>
				<pre class="svp-admin__out" id="svp-adm-vol"></pre>
			</section>
			<section class="svp-admin__card">
				<h2>عملیات گروهی (فقط Xray)</h2>
				<p class="svp-admin__warn">حداکثر ۲۰۰ سرویس در هر اجرا؛ بار روی پنل ۳x-ui.</p>
				<label>افزودن روز به همه<input type="number" id="svp-bulk-d" min="1" value="1" class="svp-admin__input"/></label>
				<label class="svp-admin__check"><input type="checkbox" id="svp-bulk-ack"/> تأیید می‌کنم که می‌خواهم این عملیات گروهی اجرا شود.</label>
				<button type="button" class="svp-btn" data-svp-admin-op="bulk_days">اجرای افزودن روز</button>
				<label>افزودن گیگ به همه<input type="number" id="svp-bulk-g" min="1" value="1" class="svp-admin__input"/></label>
				<button type="button" class="svp-btn" data-svp-admin-op="bulk_gb">اجرای افزودن حجم</button>
				<pre class="svp-admin__out" id="svp-adm-bulk"></pre>
			</section>
			<section class="svp-admin__card">
				<h2>ریفرال و لینک ربات</h2>
				<p class="svp-admin__hint">برای ویرایش کامل کدهای تخفیف از وردپرس » SimpleVPBot » تب «کدهای تخفیف» استفاده کنید.</p>
				<button type="button" class="svp-btn" data-svp-admin-op="referral_load">بارگذاری تنظیمات</button>
				<label class="svp-admin__check"><input type="checkbox" id="svp-ref-en"/> فعال بودن دعوت</label>
				<label>درصد پورسانت<input type="text" id="svp-ref-pct" class="svp-admin__input" placeholder="10"/></label>
				<label>حداقل مبلغ سفارش (تومان)<input type="text" id="svp-ref-min" class="svp-admin__input" placeholder="0"/></label>
				<label>مبلغ نمونهٔ «کمترین خرید» در متن ربات<input type="text" id="svp-ref-ex-base" class="svp-admin__input" placeholder="170000"/></label>
				<label>تعداد نفر در مثال ربات<input type="number" id="svp-ref-ex-n" min="1" class="svp-admin__input" placeholder="10"/></label>
				<label class="svp-admin__check"><input type="checkbox" id="svp-ref-req"/> دعوت‌کننده باید تأییدشده باشد</label>
				<label>نام کاربری ربات تلگرام (بدون @)<input type="text" id="svp-ref-tg" class="svp-admin__input"/></label>
				<label>نام کاربری ربات بله (بدون @)<input type="text" id="svp-ref-bl" class="svp-admin__input"/></label>
				<button type="button" class="svp-btn svp-btn--primary" data-svp-admin-op="referral_save">ذخیرهٔ ریفرال</button>
				<pre class="svp-admin__out" id="svp-adm-ref"></pre>
			</section>
			<section class="svp-admin__card">
				<h2>کدهای تخفیف (فهرست / حذف)</h2>
				<button type="button" class="svp-btn" data-svp-admin-op="discount_list">بارگذاری لیست</button>
				<label>شناسه برای حذف<input type="number" id="svp-disc-del-id" min="1" class="svp-admin__input"/></label>
				<button type="button" class="svp-btn" data-svp-admin-op="discount_delete">حذف با شناسه</button>
				<pre class="svp-admin__out" id="svp-adm-disc"></pre>
			</section>
			<section class="svp-admin__card">
				<h2>تنظیمات کریپتو (NOWPayments)</h2>
				<label>API key<textarea id="svp-cry-api" class="svp-admin__textarea" rows="2"></textarea></label>
				<label>IPN secret<textarea id="svp-cry-ipn" class="svp-admin__textarea" rows="2"></textarea></label>
				<label>pay_currency<input type="text" id="svp-cry-cur" class="svp-admin__input" placeholder="usdttrc20"/></label>
				<button type="button" class="svp-btn svp-btn--primary" data-svp-admin-op="save_crypto">ذخیره</button>
				<button type="button" class="svp-btn" data-svp-admin-op="rotate_ipn_path">تولید مسیر جدید IPN</button>
				<pre class="svp-admin__out" id="svp-adm-cry"></pre>
			</section>
		</div>
		<?php
		return (string) ob_get_clean();
	}
}
