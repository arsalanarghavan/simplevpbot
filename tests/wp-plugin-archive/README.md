# WP PHPUnit tests (deprecated)

تست‌های ریشه `tests/` به `includes/` وابسته‌اند.

پس از cutover Laravel:

- اجرا روی `main` **deprecated** — از `backend/tests/` استفاده کنید
- تست‌های parity منتقل‌شده: `backend/tests/Feature/Parity/PanelTransferCompensateTest.php`
- آرشیو کامل: `backend/scripts/ops/archive-wp-plugin.sh` → branch `archive/wp-plugin`
