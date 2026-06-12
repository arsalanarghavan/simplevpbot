# جدول Laravel `users` — unused (v17)

Migration پیش‌فرض Laravel (`0001_01_01_000000_create_users_table.php`) جدول `users` را می‌سازد.

**انحراف آگاهانه:** احراز هویت داشبورد از `dashboard_users` استف می‌کند؛ جدول `users` در runtime استفاده نمی‌شود. Sanctum `personal_access_tokens` برای bearer اختیاری رزرو شده (بدون endpoint صدور — [`SPEC-DEVIATIONS-FA.md`](SPEC-DEVIATIONS-FA.md) v17).

حذف migration در cutover بعدی (breaking برای fresh install) — فعلاً نگه‌داری با doc.
