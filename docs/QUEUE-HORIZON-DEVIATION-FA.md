# Queue & Horizon — انحراف آگاهانه (v12)

## تصمیم

| موضوع | Spec | پیاده‌سازی v12 |
|-------|------|----------------|
| Queue driver | Redis + Horizon | Redis + `queue-worker` container (`docker compose --profile workers`) |
| Local dev | `php artisan queue:listen` | `database` driver acceptable in `.env.example` for CI |
| Horizon UI | Laravel Horizon | **استفاده نمی‌شود** — worker ساده + `schedule:work` در scheduler container |

## دلیل

- Horizon وابستگی اضافه و برای scale فعلی overkill است.
- Docker profile `workers` queue + scheduler را با Redis پوشش می‌دهد.
- CI از `sync`/`database` queue برای تست‌های PHPUnit استفاده می‌کند.

## عملیاتی

```bash
docker compose --profile workers up -d queue-worker scheduler
php artisan schedule:list   # 14 svp:* jobs
```

ثبت در [`SPEC-DEVIATIONS-FA.md`](SPEC-DEVIATIONS-FA.md) v12.
