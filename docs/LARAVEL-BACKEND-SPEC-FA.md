# مشخصات فنی Backend — مهاجرت SimpleVPBot از WordPress به Laravel 11

> **نسخه سند:** 1.0  
> **تاریخ:** ۱۴۰۵/۰۳/۲۱ (۲۰۲۶-۰۶-۱۱)  
> **مخاطب:** تیم توسعه Backend، DevOps، و Frontend Dashboard  
> **وضعیت:** پیش‌نویس مشخصات اجرایی (Implementation Spec)

---

## فهرست

1. [مقدمه و اهداف](#۱-مقدمه-و-اهداف)
2. [تصمیم‌های معماری](#۲-تصمیم‌های-معماری)
3. [دیاگرام معماری](#۳-دیاگرام-معماری)
4. [ساختار Monorepo](#۴-ساختار-monorepo)
5. [Docker Compose](#۵-docker-compose)
6. [سیستم ماژول‌ها](#۶-سیستم-ماژول‌ها)
7. [API Mapping (WP → Laravel)](#۷-api-mapping-wp--laravel)
8. [Authentication & Authorization](#۸-authentication--authorization)
9. [Secret Management](#۹-secret-management)
10. [Permission Matrix (Reseller)](#۱۰-permission-matrix-reseller)
11. [Database — ۴۳ جدول `svp_*`](#۱۱-database--۴۳-جدول-svp_)
12. [Cron / Scheduler — ۱۴ Job](#۱۲-cron--scheduler--۱۴-job)
13. [Webhook Ingress](#۱۳-webhook-ingress)
14. [صفحه‌به‌صفحه Dashboard](#۱۴-صفحه‌به‌صفحه-dashboard)
15. [لیست کامل Mutate Ops](#۱۵-لیست-کامل-mutate-ops)
16. [فازبندی ۰–۱۲](#۱۶-فازبندی-۰۱۲)
17. [Migration WP → Laravel](#۱۷-migration-wp--laravel)
18. [Observability، Rate Limits، Error Format](#۱۸-observability-rate-limits-error-format)

---

## ۱. مقدمه و اهداف

### ۱.۱ زمینه

SimpleVPBot یک پلتفرم مدیریت VPN/پروکسی مبتنی بر ربات تلگرام/بله و پنل 3x-ui است. نسخه فعلی به‌صورت **پلاگین WordPress** (`includes/`, `simplevpbot.php`) با **Dashboard React** (`frontend/`) و **Relay Node.js** (`relay-server/`) اجرا می‌شود.

### ۱.۲ هدف مهاجرت

جایگزینی کامل لایه Backend از WordPress با **Laravel 11**، بدون بازنویسی Dashboard React و بدون تغییر قرارداد API سمت کلاینت (تا حد امکان). Relay Server مستقل باقی می‌ماند.

### ۱.۳ اهداف کلیدی

| هدف | معیار موفقیت |
|-----|-------------|
| حذف وابستگی WP | هیچ endpoint تولیدی به `wp-json` یا `admin-ajax.php` وابسته نباشد |
| حفظ SPA | `frontend` بدون تغییر breaking در URLها و payloadهای `admin/state` |
| ماژولار بودن | هر قابلیت (telegram، bale، relay، crypto، l2tp، …) قابل enable/disable |
| داده یکسان | ۴۳ جدول `svp_*` + settings با `wp:import` قابل مهاجرت |
| امنیت | Sanctum، secret rotation، rate limit، audit log |
| عملیات | ۱۴ scheduled job معادل WP-Cron |
| Observability | structured logging، health check، metrics پایه |

### ۱.۴ خارج از محدوده (Out of Scope)

- بازنویسی `frontend` (فقط تغییر `restUrl` / `apiBase`)
- جایگزینی Relay با سرویس دیگر
- مهاجرت کاربران WP (`wp_users`) — فقط اپراتورهای dashboard به `users` لاراول
- Multi-tenant SaaS — هر deploy = یک tenant

### ۱.۵ منابع مرجع کد فعلی

| فایل | نقش |
|------|-----|
| `includes/admin/class-dashboard-admin-mutations.php` | ۱۴۱ mutate op |
| `includes/api/class-rest-dashboard.php` | REST dashboard |
| `frontend/src/config/admin-nav.ts` | ناوبری و tab keys |
| `includes/cron/class-cron-manager.php` | ثبت cron |
| `relay-server/SETUP-GUIDE-FA.md` | ماژول relay |

---

## ۲. تصمیم‌های معماری

### ۲.۱ حذف WordPress

| قبل (WP) | بعد (Laravel) |
|----------|---------------|
| `get_option('simplevpbot_settings')` | جدول `svp_settings` + `SettingsRepository` |
| `wp_users` + meta | جدول `users` + Spatie Permission / custom roles |
| `rest_api_init` | `routes/api.php` + `RouteServiceProvider` |
| `wp-cron.php` | `php artisan schedule:run` + Supervisor |
| `wp_remote_post` | `Http::` facade + Guzzle |
| `dbDelta` migrations | Laravel migrations |
| `admin-ajax.php` portal | `POST /api/v1/portal/{signed}` |

**اصل:** هیچ فایل PHP وردپرس در production باقی نماند.

### ۲.۲ حفظ React Dashboard

- Build فرانت در `frontend/dist/` و سرو از nginx (Docker `web`) انجام می‌شود
- `window.__SIMPLEVPBOT_DASH__` از Blade view تزریق می‌شود:

```php
// resources/views/dashboard.blade.php
window.__SIMPLEVPBOT_DASH__ = {
    restUrl: '{{ url('/api/v1') }}',
    nonce: '', // حذف — Sanctum cookie/token
    dashboardBaseUrl: '{{ url('/dashboard') }}',
    // ...
};
```

- احراز هویت: **Laravel Sanctum** (SPA cookie برای same-origin)
- Header قدیمی `X-WP-Nonce` → `X-XSRF-TOKEN` + `Authorization: Bearer` (اختیاری)

### ۲.۳ Docker-first Deployment

```
┌─────────────┐     ┌──────────────┐     ┌─────────────┐
│   nginx     │────▶│  laravel-app │────▶│   mysql     │
│  (reverse)  │     │  (php-fpm)   │     │   8.0+      │
└─────────────┘     └──────────────┘     └─────────────┘
       │                    │
       │                    ├── redis (cache, queue, RL)
       │                    └── scheduler + horizon (queue)
       ▼
┌─────────────┐
│  dashboard  │  (static از public/dashboard)
└─────────────┘
```

### ۲.۴ Module System

هر ماژول = `app/Modules/{Name}/` با:
- `ModuleServiceProvider`
- `routes.php` (اختیاری)
- `config.php` → `config/modules.php` registry
- Flag: `MODULE_{NAME}_ENABLED=true`

### ۲.۵ Queue Strategy

| نوع کار | مکانیزم |
|---------|---------|
| Broadcast send | `database` queue + `BroadcastWorkerJob` |
| Users bulk | `database` queue + `UsersBulkWorkerJob` |
| Webhook async | `InboundQueueJob` (معادل `svp_inbound_queue`) |
| Deferred bot ops | Laravel `dispatch()->afterResponse()` |
| Backup | scheduled + manual dispatch |

### ۲.۶ Database

- MySQL 8.0+ (همان engine فعلی)
- Prefix جداول: `svp_` (بدون `wp_`)
- JSON columns برای `meta_json` fields
- Soft delete فقط جایی که WP داشت (services: خیر)

---

## ۳. دیاگرام معماری

```mermaid
flowchart TB
    subgraph Clients
        TG[Telegram API]
        BL[Bale API]
        ADM[Admin Browser - React SPA]
        USR[End Users - Bot]
    end

    subgraph Edge
        NGX[nginx]
        RELAY[svp-relay Node.js VPS]
    end

    subgraph Laravel["Laravel 11 Backend"]
        API[API Layer /api/v1]
        MOD[Module Registry]
        SCH[Scheduler]
        Q[Queue Workers]
        subgraph Modules
            CORE[core]
            TEL[telegram]
            BALE[bale]
            XUI[xui_panel]
            RLY[relay]
            CRY[crypto]
            L2[l2tp]
            MKT[marketing]
            RSL[reseller]
            BAK[backup]
        end
    end

    subgraph Data
        MY[(MySQL svp_*)]
        RD[(Redis)]
        FS[File Storage - backups]
    end

    TG -->|webhook| RELAY
    RELAY -->|forward POST| API
    TG -->|direct webhook optional| API
    BL -->|webhook| API
    ADM -->|Sanctum SPA| NGX
    NGX --> API
    USR -->|bot messages| TG
    USR -->|bot messages| BL

    API --> MOD
    MOD --> CORE & TEL & BALE & XUI & RLY & CRY & L2 & MKT & RSL & BAK
    API --> MY
    API --> RD
    SCH --> Q
    Q --> MY
    BAK --> FS
    CRY -->|IPN| API
```

### ۳.۱ جریان درخواست Dashboard

```mermaid
sequenceDiagram
    participant B as Browser
    participant N as nginx
    participant L as Laravel
    participant S as Sanctum
    participant DB as MySQL

    B->>N: GET /dashboard
    N->>L: dashboard.blade.php + static assets
    B->>L: POST /api/v1/dashboard/login
    L->>DB: verify credentials
    L-->>B: Set-Cookie sanctum
    B->>L: GET /api/v1/dashboard/admin/state
    L->>S: authenticate
    S-->>L: User + role
    L->>DB: aggregate state
    L-->>B: JSON state payload
    B->>L: POST /api/v1/dashboard/admin/mutate
    L-->>B: {ok, message}
```

### ۳.۲ جریان Webhook با Relay

```mermaid
sequenceDiagram
    participant TG as Telegram
    participant R as svp-relay
    participant L as Laravel
    participant Q as Queue

    TG->>R: POST /webhook/telegram/{secret}
    R-->>TG: 200 {ok:true}
    R->>L: POST /api/v1/webhook/telegram/{secret}
    L->>Q: enqueue InboundQueueJob
    Q->>L: process update
    L->>L: Bot handlers
```

---

## ۴. ساختار Monorepo

```
simplevpbot/
├── backend/                          # Laravel 11 application
│   ├── app/
│   │   ├── Http/Controllers/Api/V1/
│   │   ├── Models/                   # SvpUser, SvpService, ...
│   │   ├── Modules/
│   │   │   ├── Core/
│   │   │   ├── Telegram/
│   │   │   ├── Bale/
│   │   │   ├── XuiPanel/
│   │   │   ├── Relay/
│   │   │   ├── Crypto/
│   │   │   ├── L2tp/
│   │   │   ├── Marketing/
│   │   │   ├── Reseller/
│   │   │   └── Backup/
│   │   ├── Jobs/                     # Cron job classes
│   │   ├── Services/                 # Domain services
│   │   └── Console/Commands/
│   │       └── WpImportCommand.php   # wp:import
│   ├── config/modules.php
│   ├── database/migrations/
│   ├── routes/api.php
│   └── tests/
├── frontend/                         # React SPA
│   ├── src/
│   ├── shared/locales/               # i18n مشترک
│   └── dist/                         # خروجی build
├── relay-server/                     # Node relay
└── docs/
    └── LARAVEL-BACKEND-SPEC-FA.md    # این سند
```

### ۴.۱ قرارداد نام‌گذاری Laravel

| WP Class | Laravel Equivalent |
|----------|-------------------|
| `SimpleVPBot_Model_User` | `App\Models\SvpUser` |
| `SimpleVPBot_Rest_Dashboard` | `App\Http\Controllers\Api\V1\DashboardController` |
| `SimpleVPBot_Dashboard_Admin_Mutations` | `App\Services\Dashboard\MutateDispatcher` |
| `SimpleVPBot_Cron_Manager` | `App\Console\Kernel` + `routes/console.php` |
| `SimpleVPBot_Settings` | `App\Services\SettingsService` |

---

## ۵. Docker Compose

### ۵.۱ سرویس‌ها

| سرویس | Image | پورت | نقش |
|--------|-------|------|-----|
| `nginx` | nginx:1.27-alpine | 80, 443 | reverse proxy، static dashboard |
| `app` | php:8.3-fpm (custom) | 9000 | Laravel application |
| `mysql` | mysql:8.0 | 3306 | primary database |
| `redis` | redis:7-alpine | 6379 | cache، queue، rate limit |
| `scheduler` | same as app | — | `schedule:work` |
| `queue` | same as app | — | `queue:work` |
| `relay` | optional profile | 8787 | فقط dev local؛ prod روی VPS جدا |

### ۵.۲ `docker-compose.yml` (نمونه)

```yaml
services:
  nginx:
    image: nginx:1.27-alpine
    ports: ["8080:80"]
    volumes:
      - ./backend:/var/www/html
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf
    depends_on: [app]

  app:
    build: ./docker/php
    volumes: ["./backend:/var/www/html"]
    environment:
      APP_ENV: local
      DB_HOST: mysql
      REDIS_HOST: redis
    depends_on: [mysql, redis]

  mysql:
    image: mysql:8.0
    environment:
      MYSQL_DATABASE: simplevpbot
      MYSQL_ROOT_PASSWORD: secret
    volumes: ["svp_mysql:/var/lib/mysql"]

  redis:
    image: redis:7-alpine

  scheduler:
    build: ./docker/php
    command: php artisan schedule:work
    volumes: ["./backend:/var/www/html"]
    depends_on: [app]

  queue:
    build: ./docker/php
    command: php artisan queue:work --sleep=1 --tries=3
    volumes: ["./backend:/var/www/html"]
    depends_on: [app]

volumes:
  svp_mysql:
```

### ۵.۳ متغیرهای محیطی نمونه (`.env`)

```env
APP_NAME=SimpleVPBot
APP_URL=https://panel.example.com
APP_KEY=base64:...

DB_CONNECTION=mysql
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=simplevpbot
DB_USERNAME=svp
DB_PASSWORD=...

REDIS_HOST=redis
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

# Modules
MODULE_CORE_ENABLED=true
MODULE_TELEGRAM_ENABLED=true
MODULE_BALE_ENABLED=true
MODULE_XUI_PANEL_ENABLED=true
MODULE_RELAY_ENABLED=true
MODULE_CRYPTO_ENABLED=false
MODULE_L2TP_ENABLED=false
MODULE_MARKETING_ENABLED=true
MODULE_RESELLER_ENABLED=true
MODULE_BACKUP_ENABLED=true

# Sanctum
SANCTUM_STATEFUL_DOMAINS=panel.example.com,localhost:8080
SESSION_DOMAIN=.example.com

# Secrets (see section 9)
SVP_TELEGRAM_TOKEN=
SVP_TELEGRAM_WEBHOOK_SECRET=
SVP_BALE_TOKEN=
SVP_BALE_WEBHOOK_SECRET=
SVP_RELAY_SHARED_SECRET=
SVP_CRYPTO_IPN_PATH_SECRET=
SVP_CRYPTO_NOWPAYMENTS_API_KEY=
SVP_CRYPTO_NOWPAYMENTS_IPN_SECRET=
SVP_PORTAL_LINK_SECRET=
SVP_QUEUE_DRAIN_KEY=

# Relay (when MODULE_RELAY_ENABLED)
SVP_RELAY_VPS_IP=
SVP_RELAY_ADMIN_URL=https://203.0.113.5
SVP_RELAY_PUBLIC_URL=https://tg.example.com
SVP_RELAY_SSL_VERIFY=false

# Backup
SVP_BACKUP_INTERVAL_MINUTES=60
SVP_BACKUP_STORE_ON_SITE=true
SVP_BACKUP_TELEGRAM_CHAT_ID=

# Rate limits
SVP_WEBHOOK_RATE_LIMIT_PER_MIN=120
SVP_WEBHOOK_RESELLER_RATE_LIMIT_PER_MIN=60
```

---

## ۶. سیستم ماژول‌ها

### ۶.۱ جدول ماژول‌ها

| ماژول | Env Flag | پیش‌فرض | وابستگی‌ها | توضیح |
|-------|----------|---------|------------|-------|
| **core** | `MODULE_CORE_ENABLED` | `true` | — | users، services، plans، settings، audit، texts |
| **telegram** | `MODULE_TELEGRAM_ENABLED` | `true` | core | webhook، handlers، keyboards |
| **bale** | `MODULE_BALE_ENABLED` | `true` | core | webhook بله، handlers |
| **xui_panel** | `MODULE_XUI_PANEL_ENABLED` | `true` | core | panels، configs sync، provision |
| **relay** | `MODULE_RELAY_ENABLED` | `false` | telegram | Telegram relay VPS integration |
| **crypto** | `MODULE_CRYPTO_ENABLED` | `false` | core | NOWPayments IPN |
| **l2tp** | `MODULE_L2TP_ENABLED` | `false` | core | `svp_l2tp_servers`، bot L2TP flows |
| **marketing** | `MODULE_MARKETING_ENABLED` | `true` | core | rules، offers، idle cron |
| **reseller** | `MODULE_RESELLER_ENABLED` | `true` | core, telegram/bale | wholesale، bot profiles، permissions |
| **backup** | `MODULE_BACKUP_ENABLED` | `true` | core | zip backup، restore، cron |

### ۶.۲ قوانین Enable/Disable

1. **core** غیرقابل غیرفعال‌سازی — پایه سیستم
2. غیرفعال کردن **telegram** → tabهای relay و bot telegram مخفی؛ webhook 503
3. غیرفعال کردن **xui_panel** → tabs `xui_panels`، `configs` مخفی؛ provision متوقف
4. غیرفعال کردن **reseller** → tabs نمایندگی مخفی؛ `owner_svp_user_id` فقط 0
5. غیرفعال کردن **l2tp** → `features.l2tp=false` در bootstrap؛ tab `l2tp_servers` حذف
6. غیرفعال کردن **relay** → مستقیم webhook به Laravel (یا بدون relay)
7. غیرفعال کردن **crypto** → کارت `crypto_auto` در UI غیرفعال
8. غیرفعال کردن **backup** → cron backup و tab backup مخفی
9. غیرفعال کردن **marketing** → cron marketing/idle و tab lifecycle مخفی

### ۶.۳ `config/modules.php` (نمونه)

```php
return [
    'core' => ['enabled' => env('MODULE_CORE_ENABLED', true), 'required' => true],
    'telegram' => ['enabled' => env('MODULE_TELEGRAM_ENABLED', true), 'requires' => ['core']],
    'bale' => ['enabled' => env('MODULE_BALE_ENABLED', true), 'requires' => ['core']],
    'xui_panel' => ['enabled' => env('MODULE_XUI_PANEL_ENABLED', true), 'requires' => ['core']],
    'relay' => ['enabled' => env('MODULE_RELAY_ENABLED', false), 'requires' => ['core', 'telegram']],
    'crypto' => ['enabled' => env('MODULE_CRYPTO_ENABLED', false), 'requires' => ['core']],
    'l2tp' => ['enabled' => env('MODULE_L2TP_ENABLED', false), 'requires' => ['core']],
    'marketing' => ['enabled' => env('MODULE_MARKETING_ENABLED', true), 'requires' => ['core']],
    'reseller' => ['enabled' => env('MODULE_RESELLER_ENABLED', true), 'requires' => ['core']],
    'backup' => ['enabled' => env('MODULE_BACKUP_ENABLED', true), 'requires' => ['core']],
];
```

### ۶.۴ Boot Sequence

```
1. Load ModuleServiceProviders (topological sort by requires)
2. Register routes per enabled module
3. Register scheduled jobs per module
4. Expose enabled features in GET /dashboard/bootstrap → features{}
```

---

## ۷. API Mapping (WP → Laravel)

Namespace قدیمی: `simplevpbot/v1` → جدید: `api/v1`

### ۷.۱ Dashboard — Auth & Session

| WP Route | Method | Laravel Route | Controller@method | Auth |
|----------|--------|---------------|-------------------|------|
| `/dashboard/bootstrap` | GET | `/api/v1/dashboard/bootstrap` | `DashboardController@bootstrap` | sanctum (optional) |
| `/dashboard/login` | POST | `/api/v1/dashboard/login` | `AuthController@login` | public |
| `/dashboard/me/state` | GET | `/api/v1/dashboard/me/state` | `DashboardController@meState` | sanctum |
| `/dashboard/persona` | POST | `/api/v1/dashboard/persona` | `DashboardController@setPersona` | sanctum |
| `/dashboard/ui-preferences` | POST | `/api/v1/dashboard/ui-preferences` | `DashboardController@uiPreferences` | sanctum |
| `/dashboard/impersonate/start` | POST | `/api/v1/dashboard/impersonate/start` | `ImpersonationController@start` | admin |
| `/dashboard/impersonate/stop` | POST | `/api/v1/dashboard/impersonate/stop` | `ImpersonationController@stop` | sanctum |

### ۷.۲ Dashboard — Admin State & Reads

| WP Route | Method | Laravel Route | Controller@method | Auth |
|----------|--------|---------------|-------------------|------|
| `/dashboard/admin/state` | GET | `/api/v1/dashboard/admin/state` | `AdminStateController@index` | admin\|reseller |
| `/dashboard/admin/user/{id}` | GET | `/api/v1/dashboard/admin/user/{id}` | `AdminUserController@show` | admin\|reseller |
| `/dashboard/admin/user-search` | GET | `/api/v1/dashboard/admin/user-search` | `AdminUserController@search` | admin\|reseller |
| `/dashboard/admin/inbound-display-catalog` | GET | `/api/v1/dashboard/admin/inbound-display-catalog` | `ConfigsController@inboundCatalog` | admin\|reseller |
| `/dashboard/admin/panel-inbounds` | GET | `/api/v1/dashboard/admin/panel-inbounds` | `PanelController@inbounds` | manage |
| `/dashboard/admin/panel-inbound-clients` | GET | `/api/v1/dashboard/admin/panel-inbound-clients` | `PanelController@inboundClients` | manage |
| `/dashboard/admin/configs-snapshot` | GET | `/api/v1/dashboard/admin/configs-snapshot` | `ConfigsController@snapshot` | manage |
| `/dashboard/admin/configs-portal-payload` | GET | `/api/v1/dashboard/admin/configs-portal-payload` | `ConfigsController@portalPayload` | manage |
| `/dashboard/admin/broadcast-queue` | GET | `/api/v1/dashboard/admin/broadcast-queue` | `BroadcastController@queue` | manage\|broadcast |
| `/dashboard/admin/users-bulk-jobs` | GET | `/api/v1/dashboard/admin/users-bulk-jobs` | `UsersBulkController@jobs` | admin\|reseller |
| `/dashboard/admin/users-bulk-job-items` | GET | `/api/v1/dashboard/admin/users-bulk-job-items` | `UsersBulkController@jobItems` | admin\|reseller |
| `/dashboard/admin/audit` | GET | `/api/v1/dashboard/admin/audit` | `AuditController@index` | manage |
| `/dashboard/admin/logs` | GET | `/api/v1/dashboard/admin/logs` | `LogsController@index` | manage |
| `/dashboard/admin/purge-expired` | GET | `/api/v1/dashboard/admin/purge-expired` | `PurgeExpiredController@index` | manage |
| `/dashboard/admin/backups` | GET | `/api/v1/dashboard/admin/backups` | `BackupController@index` | manage |
| `/dashboard/admin/backup/status` | GET | `/api/v1/dashboard/admin/backup/status` | `BackupController@status` | manage |
| `/dashboard/admin/backup/download` | GET | `/api/v1/dashboard/admin/backup/download` | `BackupController@download` | manage |
| `/dashboard/admin/panel/inbound-map` | GET | `/api/v1/dashboard/admin/panel/inbound-map` | `PanelController@inboundMapGet` | manage |

### ۷.۳ Dashboard — Writes (غیر mutate)

| WP Route | Method | Laravel Route | Controller@method |
|----------|--------|---------------|-------------------|
| `/dashboard/admin/mutate` | POST | `/api/v1/dashboard/admin/mutate` | `MutateController@handle` |
| `/dashboard/admin/media` | POST | `/api/v1/dashboard/admin/media` | `MediaController@upload` |
| `/dashboard/admin/configs-sync` | POST | `/api/v1/dashboard/admin/configs-sync` | `ConfigsController@sync` |
| `/dashboard/admin/backup/run` | POST | `/api/v1/dashboard/admin/backup/run` | `BackupController@run` |
| `/dashboard/admin/backup/reset-stuck` | POST | `/api/v1/dashboard/admin/backup/reset-stuck` | `BackupController@resetStuck` |
| `/dashboard/admin/backup/restore` | POST | `/api/v1/dashboard/admin/backup/restore` | `BackupController@restore` |
| `/dashboard/admin/backup/restore-upload` | POST | `/api/v1/dashboard/admin/backup/restore-upload` | `BackupController@restoreUpload` |
| `/dashboard/admin/panel/rebuild-from-db` | POST | `/api/v1/dashboard/admin/panel/rebuild-from-db` | `PanelController@rebuildFromDb` |
| `/dashboard/admin/panel/fix-51200-traffic` | POST | `/api/v1/dashboard/admin/panel/fix-51200-traffic` | `PanelController@fix51200Traffic` |
| `/dashboard/admin/panel/inbound-map` | POST | `/api/v1/dashboard/admin/panel/inbound-map` | `PanelController@inboundMapSave` |

### ۷.۴ Webhooks & Internal

| WP Route | Method | Laravel Route | Module |
|----------|--------|---------------|--------|
| `/webhook/{platform}/{secret}` | POST | `/api/v1/webhook/{platform}/{secret}` | telegram/bale |
| `/webhook/{platform}/reseller/{id}/{secret}` | POST | `/api/v1/webhook/{platform}/reseller/{id}/{secret}` | reseller |
| `/webhook-queue/drain` | POST | `/api/v1/webhook-queue/drain` | core |
| `/crypto-ipn/{path_secret}` | POST | `/api/v1/crypto-ipn/{path_secret}` | crypto |
| `/relay/config` | GET | `/api/v1/relay/config` | relay |

### ۷.۵ Portal (جایگزین admin-ajax)

| Legacy | Laravel |
|--------|---------|
| `admin-ajax.php?action=svp_portal_admin` | `POST /api/v1/portal/admin` |
| Signed HMAC payload | `PortalSignatureMiddleware` |

### ۷.۶ Response Compatibility

- `admin/state` payload structure **بدون تغییر** (camelCase keys در JSON)
- `pagination` nested object حفظ شود
- خطاهای mutate: `{ "ok": false, "message": "code" }`

---

## ۸. Authentication & Authorization

### ۸.۱ Laravel Sanctum (SPA)

```
1. GET /sanctum/csrf-cookie
2. POST /api/v1/dashboard/login {username, password}
3. Session cookie + XSRF-TOKEN
4. Subsequent requests: credentials:include + X-XSRF-TOKEN
```

### ۸.۲ Roles

| Role | Laravel | شرایط |
|------|---------|-------|
| **admin** | `role:admin` | super-admin؛ دسترسی کامل |
| **reseller** | `role:reseller` | `svp_users.is_reseller=1` + لینک `users.svp_user_id` |
| **user** | `role:user` | persona کاربر نهایی (portal) |

### ۸.۳ Admin User Model

```php
// users table (Laravel)
id, name, email, password, svp_user_id (FK nullable), role, ...
```

- اپراتور dashboard = رکورد `users`
- کاربر bot = رکورد `svp_users` (جدا)

### ۸.۴ Reseller Scoping

هر درخواست reseller:
1. `actorUserId` = `svp_users.id` از session
2. فقط descendants در `svp_reseller_closure`
3. `filterAdminNavForReseller()` سمت سرور → `allowedTabs[]`

### ۸.۵ Impersonation

| Endpoint | قانون |
|----------|-------|
| `impersonate/start` | فقط admin؛ target باید reseller باشد |
| Session flag: `impersonating_reseller_id` | |
| `impersonate/stop` | بازگشت به admin اصلی |
| Audit: `impersonation_start` / `impersonation_stop` | |

### ۸.۶ Middleware Stack

```
api → sanctum → EnsureDashboardEnabled → RoleMiddleware → ResellerScopeMiddleware
```

---

## ۹. Secret Management

### ۹.۱ دسته‌بندی Secrets

| Secret | Storage | Rotation |
|--------|---------|----------|
| `telegram_token` | `.env` + `svp_settings` encrypted | dashboard bots tab |
| `telegram_webhook_secret` | encrypted DB | `bot_set_webhook` / rotate |
| `bale_token` | encrypted DB | bots tab |
| `bale_webhook_secret` | encrypted DB | rotate |
| `panel_password` | encrypted DB per panel | panel edit |
| `relay_shared_secret` | `.env` + relay `.env` | `telegram_relay_rotate_secret` |
| `crypto_ipn_path_secret` | encrypted DB | manual regenerate |
| `crypto_nowpayments_ipn_secret` | encrypted DB | NOWPayments dashboard |
| `portal_link_secret` | encrypted DB | settings |
| `queue_drain_key` | `.env` | deploy-time |
| Reseller bot tokens | `svp_reseller_bot_profiles` encrypted | reseller UI |

### ۹.۲ Laravel Encryption

```php
// config/svp.php
'encryption_key' => env('SVP_ENCRYPTION_KEY'), // یا APP_KEY

SettingsService::setEncrypted('telegram_token', $value);
```

### ۹.۳ Relay Secret Sync

طبق `relay-server/SETUP-GUIDE-FA.md`:
1. `RELAY_MASTER_SECRET` روی VPS = `SVP_RELAY_SHARED_SECRET` در Laravel
2. Rotate از dashboard → به‌روزرسانی هر دو طرف
3. Admin API relay از IP:443 با cert خودامضا

### ۹.۴ عدم Log کردن Secrets

- `Log::` middleware redact: `token`, `secret`, `password`, `api_key`
- Audit log: فقط `key` names نه values

---

## ۱۰. Permission Matrix (Reseller)

۷ کلید permission (از `SimpleVPBot_Model_User::RESELLER_PERMISSION_KEYS`):

| Key | Tab‌های مرتبط | Mutate Ops نمونه |
|-----|-------------|------------------|
| `users.manage` | users, resellers, referral, referral_reports, reseller_reports | `user_status`, `user_balance_delta`, `membership`, `reseller_panel_prices_save`, `reseller_wp_provision` |
| `users.bulk` | users_bulk | `users_bulk_*` |
| `broadcast.send` | broadcast | `broadcast_send`, `broadcast_cancel` |
| `receipts.review` | receipts | `receipt_action`, `receipt_set_status`, `receipt_update` |
| `plans.manage` | plans, plan_cats, cards, discounts, reseller_charge, unit_economics (read) | `plan`, `plan_category`, `card_*`, `discount_redemptions`, `reseller_wallet_topup_checkout`, `reseller_payment_methods_save` |
| `services.manage` | monitoring, bots (reseller), bot_ui, reseller_bots, user services | `user_create_service`, `service_*`, `bot_reseller_*`, `configs_client_*` |
| `marketing.lifecycle` | marketing_lifecycle | read-only در SPA؛ write از portal |

### ۱۰.۱ Tab → Permission (از `App.tsx`)

| tabKey | Permission لازم |
|--------|----------------|
| `dashboard` | — (همیشه) |
| `monitoring` | `services.manage` |
| `users` | `users.manage` |
| `users_bulk` | `users.bulk` |
| `broadcast` | `broadcast.send` |
| `plans` | `plans.manage` |
| `plan_cats` | `plans.manage` |
| `cards` | `plans.manage` |
| `receipts` | `receipts.review` |
| `referral` | `users.manage` |
| `referral_reports` | `users.manage` |
| `reseller_reports` | `users.manage` |
| `marketing_lifecycle` | `marketing.lifecycle` |
| `discounts` | `plans.manage` |
| `reseller_bots` | `services.manage` |
| `bot_ui` | `services.manage` |
| `reseller_charge` | `plans.manage` |
| `reseller_settings` | — (همیشه برای reseller) |

### ۱۰.۲ Admin-only Tabs

`audit`, `site_settings`, `backup`, `configs`, `texts`, `notifications`, `logs`, `reseller_bots` (admin view), `reseller_xui_panels`, `reseller_settings` (admin), `unit_economics`, `bots`, `xui_panels`, `l2tp_servers`

---

## ۱۱. Database — ۴۳ جدول `svp_*`

> Prefix در Laravel: `svp_` (بدون `wp_`). جدول ۴۳: ۴۲ جدول داده WP + `svp_settings` (جایگزین `wp_options.simplevpbot_settings`).

| # | Table | Laravel Model | Module | توضیح کوتاه |
|---|-------|---------------|--------|-------------|
| 1 | `svp_users` | `SvpUser` | core | کاربران bot (tg/bale/wp link) |
| 2 | `svp_services` | `SvpService` | core | سرویس‌های VPN |
| 3 | `svp_transactions` | `SvpTransaction` | core | تراکنش‌های مالی |
| 4 | `svp_receipts` | `SvpReceipt` | core | رسیدهای کارت‌به‌کارت |
| 5 | `svp_cards` | `SvpCard` | core | روش‌های پرداخت |
| 6 | `svp_plans` | `SvpPlan` | core | پلن‌ها |
| 7 | `svp_plan_categories` | `SvpPlanCategory` | core | دسته‌بندی پلن |
| 8 | `svp_panels` | `SvpPanel` | xui_panel | پنل‌های 3x-ui |
| 9 | `svp_panel_inbound_clients` | `SvpPanelInboundClient` | xui_panel | cache کلاینت‌های inbound |
| 10 | `svp_panel_inbound_api` | `SvpPanelInboundApi` | xui_panel | cache API inbound |
| 11 | `svp_panel_online_daily` | `SvpPanelOnlineDaily` | xui_panel | آمار آنلاین روزانه |
| 12 | `svp_panel_economics_lines` | `SvpPanelEconomicsLine` | xui_panel | خطوط اقتصاد پنل |
| 13 | `svp_texts` | `SvpText` | core | متون bot (fa/en) |
| 14 | `svp_logs` | `SvpLog` | core | لاگ اپلیکیشن |
| 15 | `svp_audit_log` | `SvpAuditLog` | core | audit dashboard |
| 16 | `svp_broadcasts` | `SvpBroadcast` | core | پیام‌های گروهی |
| 17 | `svp_broadcast_queue` | `SvpBroadcastQueue` | core | صف ارسال broadcast |
| 18 | `svp_users_bulk_jobs` | `SvpUsersBulkJob` | core | job عملیات گروهی |
| 19 | `svp_users_bulk_job_items` | `SvpUsersBulkJobItem` | core | آیتم‌های bulk job |
| 20 | `svp_pending_approvals` | `SvpPendingApproval` | core | تأیید عضویت |
| 21 | `svp_sync_codes` | `SvpSyncCode` | core | کد همگام‌سازی |
| 22 | `svp_referral_events` | `SvpReferralEvent` | core | رویدادهای referral |
| 23 | `svp_user_activity` | `SvpUserActivity` | core | فعالیت کاربر |
| 24 | `svp_service_ip_log` | `SvpServiceIpLog` | core | لاگ IP سرویس |
| 25 | `svp_marketing_rules` | `SvpMarketingRule` | marketing | قوانین lifecycle |
| 26 | `svp_marketing_offers` | `SvpMarketingOffer` | marketing | پیشنهادهای ارسالی |
| 27 | `svp_discount_codes` | `SvpDiscountCode` | core | کدهای تخفیف |
| 28 | `svp_discount_redemptions` | `SvpDiscountRedemption` | core | استفاده از تخفیف |
| 29 | `svp_l2tp_servers` | `SvpL2tpServer` | l2tp | سرورهای L2TP |
| 30 | `svp_monitor_hosts` | `SvpMonitorHost` | core | hostهای monitoring |
| 31 | `svp_unit_economics_config` | `SvpUnitEconomicsConfig` | xui_panel | تنظیمات اقتصاد واحد |
| 32 | `svp_unit_economics_servers` | `SvpUnitEconomicsServer` | xui_panel | سرورهای اقتصاد واحد |
| 33 | `svp_reseller_bot_profiles` | `SvpResellerBotProfile` | reseller | پروفایل ربات نماینده |
| 34 | `svp_reseller_closure` | `SvpResellerClosure` | reseller | درخت نمایندگان |
| 35 | `svp_reseller_panel_prices` | `SvpResellerPanelPrice` | reseller | قیمت پنل per reseller |
| 36 | `svp_reseller_inbound_display_names` | `SvpResellerInboundDisplayName` | reseller | برچسب inbound سفارشی |
| 37 | `svp_reseller_wholesale_lines` | `SvpResellerWholesaleLine` | reseller | خطوط عمده‌فروشی |
| 38 | `svp_reseller_wholesale_tiers` | `SvpResellerWholesaleTier` | reseller | سطوح عمده |
| 39 | `svp_reseller_wholesale_line_assignments` | `SvpResellerWholesaleAssignment` | reseller | تخصیص خط به reseller |
| 40 | `svp_reseller_wholesale_accruals` | `SvpResellerWholesaleAccrual` | reseller | تعهدات عمده |
| 41 | `svp_reseller_parent_panel_floors` | `SvpResellerParentPanelFloor` | reseller | کف قیمت parent→child |
| 42 | `svp_inbound_queue` | `SvpInboundQueue` | core | صف webhook async |
| 43 | `svp_settings` | `SvpSetting` | core | key-value settings (مهاجرت از WP options) |

### ۱۱.۱ Indexes حیاتی (حفظ از WP)

- `svp_users`: UNIQUE `tg_user_id`, `bale_user_id`, `wp_user_id`
- `svp_discount_codes`: UNIQUE `owner_svp_user_id, code`
- `svp_reseller_bot_profiles`: UNIQUE `reseller_svp_user_id`
- `svp_transactions`: KEY `billing_reseller_svp_id`

### ۱۱.۲ Laravel Migrations Strategy

```
database/migrations/
├── 0001_create_svp_users_table.php
├── 0002_create_svp_services_table.php
...
├── 0043_create_svp_settings_table.php
```

هر migration DDL را از `includes/class-activator.php` mirror کند.

---

## ۱۲. Cron / Scheduler — ۱۴ Job

| # | WP Hook | Interval | Laravel Class | Module |
|---|---------|----------|---------------|--------|
| 1 | `simplevpbot_cron_backup` | every N min (۵–۱۴۴۰) | `App\Jobs\Cron\BackupJob` | backup |
| 2 | `simplevpbot_cron_expiry` | hourly | `App\Jobs\Cron\ExpiryJob` | core |
| 3 | `simplevpbot_cron_purge_expired` | hourly | `App\Jobs\Cron\PurgeExpiredJob` | core |
| 4 | `simplevpbot_cron_autorenew` | hourly | `App\Jobs\Cron\AutorenewJob` | core |
| 5 | `simplevpbot_cron_broadcast` | every 1 min | `App\Jobs\Cron\BroadcastWorkerJob` | core |
| 6 | `simplevpbot_cron_users_bulk` | every 1 min | `App\Jobs\Cron\UsersBulkWorkerJob` | core |
| 7 | `simplevpbot_cron_panel_online` | every 10 min | `App\Jobs\Cron\PanelOnlineJob` | xui_panel |
| 8 | `simplevpbot_cron_panel_service_sync` | every 10 min | `App\Jobs\Cron\PanelServiceSyncJob` | xui_panel |
| 9 | `simplevpbot_cron_inbound_clients_cache` | every 10 min | `App\Jobs\Cron\InboundClientsCacheJob` | xui_panel |
| 10 | `simplevpbot_cron_idle_offers` | hourly | `App\Jobs\Cron\IdleOffersJob` | marketing |
| 11 | `simplevpbot_cron_marketing` | hourly | `App\Jobs\Cron\MarketingJob` | marketing |
| 12 | `simplevpbot_cron_admin_alerts` | every 10 min | `App\Jobs\Cron\AdminAlertsJob` | core |
| 13 | `simplevpbot_cron_panel_economics_renewal` | hourly | `App\Jobs\Cron\PanelEconomicsRenewalJob` | xui_panel |
| 14 | `simplevpbot_cron_inbound_queue` | every 1 min | `App\Jobs\Cron\InboundQueueDrainJob` | core |

### ۱۲.۱ `routes/console.php`

```php
Schedule::job(new BackupJob)->cron('*/'.max(5, config('svp.backup_interval_minutes')).' * * * *');
Schedule::job(new ExpiryJob)->hourly();
Schedule::job(new PurgeExpiredJob)->hourly();
Schedule::job(new AutorenewJob)->hourly();
Schedule::job(new BroadcastWorkerJob)->everyMinute();
Schedule::job(new UsersBulkWorkerJob)->everyMinute();
Schedule::job(new PanelOnlineJob)->everyTenMinutes();
Schedule::job(new PanelServiceSyncJob)->everyTenMinutes();
Schedule::job(new InboundClientsCacheJob)->everyTenMinutes();
Schedule::job(new IdleOffersJob)->hourly();
Schedule::job(new MarketingJob)->hourly();
Schedule::job(new AdminAlertsJob)->everyTenMinutes();
Schedule::job(new PanelEconomicsRenewalJob)->hourly();
Schedule::job(new InboundQueueDrainJob)->everyMinute();
```

### ۱۲.۲ Manual Triggers (معادل mutate)

| Mutate Op | Job |
|-----------|-----|
| `broadcast_run_worker` | `BroadcastWorkerJob::dispatchSync()` |
| `users_bulk_run_worker` | `UsersBulkWorkerJob::dispatchSync()` |
| `purge_expired_run_cron` | `PurgeExpiredJob::dispatchSync()` |
| `backup/run` REST | `BackupJob::dispatch()` |

---

## ۱۳. Webhook Ingress

### ۱۳.۱ Telegram / Bale (مستقیم)

```
POST /api/v1/webhook/{platform}/{secret}
```

- Auth: path `secret` === `settings.{platform}_webhook_secret`
- Optional header: `X-Telegram-Bot-Api-Secret-Token`
- Rate limit: `webhook_rate_limit_per_min` (default 120)
- Flow: validate → enqueue `svp_inbound_queue` → 200 OK → drain async

### ۱۳.۲ Reseller Webhook

```
POST /api/v1/webhook/{platform}/reseller/{reseller_id}/{secret}
```

- Secret از `svp_reseller_bot_profiles.{platform}_webhook_secret`
- Rate limit: `webhook_reseller_rate_limit_per_min` (default 60)
- Scope: handlers با `reseller_svp_user_id` context

### ۱۳.۳ Relay Forward

طبق `relay-server/SETUP-GUIDE-FA.md`:

```
Telegram → https://tg.example.com/webhook/telegram/{secret}
         → svp-relay (200 OK فوری)
         → POST https://panel.example.com/api/v1/webhook/telegram/{secret}
```

- Laravel همان handler مستقیم را اجرا می‌کند
- Config pull: `GET /api/v1/relay/config` + header `X-SVP-RELAY-SECRET`
- Admin proxy ops: `telegram_relay_admin_*` mutate → HTTP به relay VPS

### ۱۳.۴ Crypto IPN (NOWPayments)

```
POST /api/v1/crypto-ipn/{path_secret}
```

- Path secret === `crypto_ipn_path_secret`
- Body HMAC: `x-nowpayments-sig` با `crypto_nowpayments_ipn_secret`
- Module: `crypto` باید enabled باشد

### ۱۳.۵ Webhook Queue Drain

```
POST /api/v1/webhook-queue/drain
Header: X-SVP-QUEUE-KEY
```

- Internal only (loopback / relay shutdown)
- پردازش batch از `svp_inbound_queue`

### ۱۳.۶ Portal Ingress

```
POST /api/v1/portal/admin
```

- Signed URL از bot admin menu
- جایگزین `admin-ajax.php?action=svp_portal_admin`
- Discount/marketing write برای reseller از portal (نه SPA)

---

## ۱۴. صفحه‌به‌صفحه Dashboard

> **قالب هر صفحه:** Route، Roles، Component، Module(s)، Fields، GET endpoints، Mutate ops، Models، Jobs، Acceptance criteria

---

### گروه A — Overview & Auth

#### A.1 Overview (Dashboard)

| فیلد | مقدار |
|------|-------|
| **Route** | `/dashboard?tab=dashboard` |
| **Roles** | admin، reseller |
| **Component** | `frontend/src/components/dashboard-overview.tsx` |
| **Module(s)** | core، xui_panel |

**Fields/Forms:** فیلتر بازه متریک (۷/۳۰/۹۰ روز)، stats day، لینک‌های سریع

**GET endpoints:**
- `GET /api/v1/dashboard/admin/state?overview_metrics_window_days=30&stats_day=0`

**Mutate ops:** — (read-only؛ economics refresh از overview card)

**Models:** `SvpPanel`, `SvpUser`, `SvpReceipt`, `SvpBroadcast`, `SvpService`

**Jobs:** —

**Acceptance criteria:**
- [ ] کارت‌های آمار (users، receipts، panels) با داده واقعی
- [ ] reseller فقط متریک‌های زیرمجموعه خود را ببیند
- [ ] panel health badge قابل refresh
- [ ] لینک سریع به tabهای مجاز reseller کار کند
- [ ] economics overview card به `unit_economics` لینک دهد (admin)

---

#### A.2 Monitoring

| فیلد | مقدار |
|------|-------|
| **Route** | `/dashboard?tab=monitoring` |
| **Roles** | admin؛ reseller با `services.manage` |
| **Component** | `frontend/src/components/dashboard-monitoring.tsx` |
| **Module(s)** | core، xui_panel |

**Fields/Forms:** — (viz panels + monitor hosts)

**GET endpoints:**
- `GET /api/v1/dashboard/admin/state` (panels، monitorHosts، overview.live)

**Mutate ops:** —

**Models:** `SvpPanel`, `SvpMonitorHost`, `SvpPanelOnlineDaily`

**Jobs:** `PanelOnlineJob`, `AdminAlertsJob`

**Acceptance criteria:**
- [ ] نمودار وضعیت پنل‌ها real-time refresh
- [ ] monitor hosts ping status
- [ ] reseller فقط پنل‌های مجاز
- [ ] دکمه refresh live metrics کار کند

---

#### A.3 Login

| فیلد | مقدار |
|------|-------|
| **Route** | `/dashboard/login` |
| **Roles** | public |
| **Component** | `frontend/src/components/dashboard-login.tsx` |
| **Module(s)** | core |

**Fields/Forms:** username/email، password

**GET endpoints:**
- `GET /api/v1/dashboard/bootstrap`

**Mutate ops:** —

**POST endpoints:**
- `POST /api/v1/dashboard/login`

**Models:** `User` (Laravel)

**Jobs:** —

**Acceptance criteria:**
- [ ] login موفق → redirect به dashboard
- [ ] session Sanctum برقرار شود
- [ ] خطای credential → پیام `{ok:false}`
- [ ] CSRF cookie قبل از login

---

### گروه B — Site Settings (۹ زیرتب)

**Route:** `/dashboard?tab=site_settings&site_subtab={subtab}`  
**Roles:** admin only  
**Component:** `frontend/src/components/dashboard-site-settings-admin.tsx`  
**Module(s):** core، telegram، relay، backup

---

#### B.1 Whitelabel (`whitelabel`)

**Fields:** brand name، logo URL، favicon، colors، CSS variables، portal page، accent presets

**GET:** `admin/state` → `settings`, `wpPages`, `branding`

**Mutate:** `settings_tab` (tab=`whitelabel`)

**Models:** `SvpSetting`

**Acceptance criteria:**
- [ ] ذخیره branding و اعمال CSS vars در SPA
- [ ] preview logo/favicon
- [ ] portal page selector از pages list

---

#### B.2 Service Naming (`service_naming`)

**Fields:** label overrides per service type، naming templates

**Mutate:** `settings_tab` (tab=`service_naming`)

**Models:** `SvpSetting`

**Acceptance criteria:**
- [ ] overrideها در bot و dashboard نمایش داده شوند
- [ ] reset به default ممکن باشد

---

#### B.3 Proxy (`proxy`)

**Fields:** telegram HTTP proxy URL، test button

**Mutate:** `settings_tab` (tab=`proxy`)، `telegram_proxy_test`

**Module:** telegram

**Acceptance criteria:**
- [ ] proxy test به Telegram API موفق/ناموفض
- [ ] bot requests از proxy عبور کنند

---

#### B.4 Relay (`relay`)

**Fields:** enabled، force، VPS IP، admin URL، public URL، SSL verify، forward URL، allowed IPs، shared secret  
**Component sub:** `site-settings-relay-tab.tsx`, `relay-control-center.tsx`

**Mutate:** `settings_tab` (tab=`relay`)، `telegram_relay_*` (۲۶ op — بخش ۱۵)

**Module:** relay

**Acceptance criteria:**
- [ ] Sync config → tenant روی relay
- [ ] Set webhook via relay
- [ ] Control center: doctor، logs، nginx، SSL
- [ ] مطابق `relay-server/SETUP-GUIDE-FA.md` ترتیب راه‌اندازی

---

#### B.5 Notifications (`notifications`)

**Fields:** expiry days، low traffic %، admin panel down، idle user، panel cost reminders

**Mutate:** `settings_tab` (tab=`notifications`)

**Jobs:** `ExpiryJob`, `AdminAlertsJob`, `IdleOffersJob`, `PanelEconomicsRenewalJob`

**Acceptance criteria:**
- [ ] تنظیمات notify در cronها اعمال شود
- [ ] cooldown fields respected

---

#### B.6 Purge Expired (`purge_expired`)

**Fields:** enabled، grace days، warn days[]، notify user  
**Component:** `site-settings-purge-tab.tsx`

**GET:** `GET /api/v1/dashboard/admin/purge-expired`

**Mutate:** `settings_tab` (tab=`purge_expired`)، `purge_expired_run_cron`، `purge_expired_purge_ready`، `purge_expired_purge_one`

**Jobs:** `PurgeExpiredJob`

**Acceptance criteria:**
- [ ] لیست سرویس‌های آماده purge
- [ ] manual purge one/all
- [ ] cron scan اجرا شود

---

#### B.7 Finance (`finance`)

**Fields:** default concurrent users، price per extra user، test account، crypto settings (if module)

**Mutate:** `settings_tab` (tab=`finance`)، `crypto_settings`

**Module:** crypto (optional)

**Acceptance criteria:**
- [ ] crypto settings فقط با MODULE_CRYPTO_ENABLED
- [ ] NOWPayments keys encrypted

---

#### B.8 Logs (`logs`)

**Fields:** filter level، search q، pagination  
**Component:** `site-settings-logs-tab.tsx`

**GET:** `GET /api/v1/dashboard/admin/logs`

**Mutate:** `logs_clear`

**Models:** `SvpLog`

**Acceptance criteria:**
- [ ] pagination و filter
- [ ] clear با confirm

---

#### B.9 Resellers Defaults (`resellers`)

**Fields:** default reseller permissions (۷ checkbox)  
**Component:** `site-settings-resellers-tab.tsx`

**Mutate:** `settings_tab` (tab=`resellers_defaults`)

**Acceptance criteria:**
- [ ] defaults روی reseller جدید اعمال شود
- [ ] map permissions در admin/state

---

### گروه C — Users

#### C.1 Users List

| فیلد | مقدار |
|------|-------|
| **Route** | `/dashboard?tab=users` |
| **Roles** | admin؛ reseller با `users.manage` |
| **Component** | `frontend/src/components/dashboard-users-admin.tsx` |
| **Module(s)** | core، reseller |

**Fields:** search q، filters (status، platform، balance range، date)، pending users tab

**GET:** `admin/state?users_page&users_per_page&users_q&...`

**Mutate:** `user_manual_create`، `membership`، `user_status`، `link_wp_user`

**Models:** `SvpUser`, `SvpPendingApproval`

**Acceptance criteria:**
- [ ] pagination users + pending
- [ ] reseller فقط subtree
- [ ] click → user detail
- [ ] manual create user

---

#### C.2 User Detail

| فیلد | مقدار |
|------|-------|
| **Route** | `/dashboard?tab=users&user_id={id}` |
| **Component** | `frontend/src/components/dashboard-user-detail-admin.tsx` |
| **Module(s)** | core، xui_panel، reseller |

**Fields:** balance delta، services cards، receipts، transactions، role، referrer، admin message

**GET:** `GET /api/v1/dashboard/admin/user/{id}`

**Mutate:** `user_balance_delta`، `user_create_service`، `user_renew_service`، `user_add_volume`، `user_reduce_volume`، `user_add_days`، `user_reduce_days`، `user_service_*`، `service_*`، `receipt_*`، `user_set_role`، `user_set_referrer`، `user_admin_message`، `inbound_link`، `inbound_autolink`

**Models:** `SvpUser`, `SvpService`, `SvpReceipt`, `SvpTransaction`

**Jobs:** provision jobs (deferred)

**Acceptance criteria:**
- [ ] تمام service ops کار کنند
- [ ] panel sync/regen/transfer
- [ ] reseller permission gates
- [ ] activity log نمایش داده شود

---

#### C.3 Users Bulk

| فیلد | مقدار |
|------|-------|
| **Route** | `/dashboard?tab=users_bulk` |
| **Component** | `frontend/src/components/dashboard-users-bulk-admin.tsx` |
| **Module(s)** | core |

**Fields:** job type (wallet/volume/extend/alerts/slots)، CSV/targets، panel scope

**GET:** `users-bulk-jobs`، `users-bulk-job-items`

**Mutate:** `users_bulk_wallet`، `users_bulk_volume`، `users_bulk_extend`، `users_bulk_alerts`، `users_bulk_slots`، `users_bulk_run_worker`، `users_bulk_job_cancel`، `users_bulk_job_resume`

**Jobs:** `UsersBulkWorkerJob`

**Acceptance criteria:**
- [ ] ایجاد job و پیشرفت itemها
- [ ] cancel/resume
- [ ] worker cron هر دقیقه

---

#### C.4 User Merge

| فیلد | مقدار |
|------|-------|
| **Route** | embedded در tab users |
| **Component** | `frontend/src/components/dashboard-user-merge-admin.tsx` |
| **Roles** | admin only |

**Fields:** source id، target id، preview

**Mutate:** `user_merge_preview`، `user_merge`

**Models:** `SvpUser` (+ cascade services، txs)

**Acceptance criteria:**
- [ ] preview تفاوت‌ها را نشان دهد
- [ ] merge اتمی — یک user باقی بماند
- [ ] audit log ثبت شود

---

### گروه D — Bot Settings

#### D.1 Bots (Site)

| **Route** | `/dashboard?tab=bots` |
| **Component** | `dashboard-bots-admin.tsx` (variant=site) |
| **Roles** | admin |
| **Module(s)** | telegram، bale |

**Fields:** token per platform، enabled، webhook status، admin IDs

**Mutate:** `settings_tab` (tab=`bots`)، `bot_toggle_enabled`، `bot_toggle_platform_enabled`، `bot_test_telegram`، `bot_test_bale`، `bot_diagnostics`، `bot_set_webhook`، `bot_delete_webhook`، `bot_admin_id_add`، `bot_admin_id_remove`

**Acceptance criteria:**
- [ ] webhook register/delete
- [ ] test connection هر platform
- [ ] diagnostics dialog اطلاعات مفید

---

#### D.2 Force Join

| **Route** | embedded در bots tab |
| **Component** | `dashboard-force-join-admin.tsx` |

**Fields:** telegram/bale channel id، invite link، prompt text، enabled

**Mutate:** `settings_tab` (tab=`force_join`)، `force_join_publish`

**Acceptance criteria:**
- [ ] publish announcement به channel
- [ ] gate در bot handler فعال شود

---

#### D.3 Texts

| **Route** | `/dashboard?tab=texts` |
| **Component** | `dashboard-texts-admin.tsx` |
| **Roles** | admin |

**Mutate:** `texts_save`، `text_reset_one`، `texts_reset`

**Models:** `SvpText`

**Acceptance criteria:**
- [ ] edit fa/en per key
- [ ] reset one/all به defaults

---

#### D.4 Bot UI Studio

| **Route** | `/dashboard?tab=bot_ui` |
| **Component** | `dashboard-bot-ui-studio.tsx` |
| **Roles** | admin؛ reseller (read-only layout) |

**Mutate:** `bot_ui_layout_save`، `bot_ui_layout_reset`

**Acceptance criteria:**
- [ ] drag-drop layout ذخیره شود
- [ ] reseller نتواند layout را تغییر دهد

---

#### D.5 Reseller Bots

| **Route** | `/dashboard?tab=reseller_bots` |
| **Component** | `dashboard-bots-admin.tsx` (variant=reseller_*) |
| **Module(s)** | reseller، telegram، bale |

**Mutate:** `bot_reseller_save`، `bot_reseller_toggle_enabled`، `bot_reseller_secret_rotate`، `bot_reseller_delete`، `reseller_bot_tokens_save`، `reseller_bot_webhook_set`، `reseller_bot_webhook_delete`، `telegram_relay_set_webhook_reseller`

**Models:** `SvpResellerBotProfile`

**Acceptance criteria:**
- [ ] admin: لیست همه reseller bots
- [ ] reseller: فقط bot خود
- [ ] webhook + relay per reseller domain

---

### گروه E — Servers / 3x-ui

#### E.1 XUI Panels

| **Route** | `/dashboard?tab=xui_panels` |
| **Component** | `dashboard-panels-admin.tsx` |
| **Module(s)** | xui_panel |

**Fields:** name، URL، credentials، inbound map، economics sheet

**Mutate:** `panel_xp`، `panel_test`، `panel_economics_save`، `panel_economics_mark_paid`، `shared_economics_save`

**REST:** `panel/rebuild-from-db`، `panel/fix-51200-traffic`، `panel/inbound-map`

**Models:** `SvpPanel`, `SvpPanelEconomicsLine`

**Jobs:** `PanelOnlineJob`, `PanelServiceSyncJob`, `InboundClientsCacheJob`

**Acceptance criteria:**
- [ ] CRUD panel
- [ ] test connection 3x-ui
- [ ] economics per panel
- [ ] pagination

---

#### E.2 Configs

| **Route** | `/dashboard?tab=configs` |
| **Component** | `dashboard-configs-admin.tsx` |

**GET:** `configs-snapshot`، `panel-inbounds`، `panel-inbound-clients`، `configs-portal-payload`

**Mutate:** `configs_sync` (REST)، `configs_client_toggle_enable`، `configs_client_reset_traffic`، `configs_client_delete`، `configs_delete_expired_linked`، `configs_panel_client_patch`، `configs_clients_batch`، `configs_assign_plan`، `service_panel_transfer`

**Models:** `SvpPanelInboundClient`, `SvpService`, `SvpPlan`

**Acceptance criteria:**
- [ ] snapshot sync از پنل
- [ ] batch ops روی clients
- [ ] assign plan به orphan clients
- [ ] stale cache indicator

---

#### E.3 Panel Economics (sheet)

| **Route** | sheet روی xui_panels / unit_economics |
| **Component** | `dashboard-panel-economics-sheet.tsx` |

**Mutate:** `panel_economics_save`، `panel_economics_mark_paid`

**Acceptance criteria:**
- [ ] خطوط هزینه ماهانه
- [ ] mark paid → extend due date

---

#### E.4 Reseller XUI Panels

| **Route** | `/dashboard?tab=reseller_xui_panels` |
| **Component** | `dashboard-reseller-panels-admin.tsx` |
| **Roles** | admin |

**Mutate:** `reseller_panel_prices_save`، `reseller_inbound_labels_save`

**Models:** `SvpResellerPanelPrice`, `SvpResellerInboundDisplayName`

**Acceptance criteria:**
- [ ] قیمت per GB per panel per reseller
- [ ] panel access toggle
- [ ] inbound display labels

---

### گروه F — Finance

#### F.1 Plans

| **Route** | `/dashboard?tab=plans` |
| **Component** | `dashboard-plans-admin.tsx` |

**Mutate:** `plan` (create/update/delete/toggle)

**Models:** `SvpPlan`

**Acceptance criteria:**
- [ ] CRUD plan با panel/category binding
- [ ] reseller floors نمایش (reseller mode)
- [ ] wholesale line binding

---

#### F.2 Plan Categories

| **Route** | `/dashboard?tab=plan_cats` |
| **Component** | `dashboard-plan-cats-admin.tsx` |

**Mutate:** `plan_category`

**Models:** `SvpPlanCategory`

---

#### F.3 Cards (Payment Methods)

| **Route** | `/dashboard?tab=cards` |
| **Component** | `dashboard-cards-admin.tsx` |

**Mutate:** `card_add`، `card_update`، `card_delete`، `card_reorder`، `reseller_payment_methods_save`

**Models:** `SvpCard`

---

#### F.4 Receipts

| **Route** | `/dashboard?tab=receipts` |
| **Component** | `dashboard-receipts-admin.tsx` |

**Mutate:** `receipt_action`، `receipt_set_status`، `receipt_update`، `receipt_reject_reasons_save`

**Models:** `SvpReceipt`

**Acceptance criteria:**
- [ ] approve/reject با delivery
- [ ] filters و aggregates
- [ ] reseller scope

---

#### F.5 Discounts

| **Route** | `/dashboard?tab=discounts` |
| **Component** | `dashboard-discounts-admin.tsx` |

**Mutate:** `discount_save`، `discount_delete`، `discount_redemptions` (read)

**Note:** reseller write از portal (نه SPA)

---

#### F.6 Unit Economics

| **Route** | `/dashboard?tab=unit_economics` |
| **Component** | `dashboard-unit-economics-admin.tsx` |
| **Roles** | admin |

**Mutate:** `unit_economics_save`، `unit_economics_config_save`

**Models:** `SvpUnitEconomicsConfig`, `SvpUnitEconomicsServer`

---

#### F.7 Reseller Charge

| **Route** | `/dashboard?tab=reseller_charge` |
| **Component** | `dashboard-reseller-charge-admin.tsx` |
| **Roles** | reseller |

**Mutate:** `reseller_wallet_topup_checkout`

**Acceptance criteria:**
- [ ] customer charges list
- [ ] wallet topup checkout flow

---

### گروه G — Marketing & Resellers

#### G.1 Broadcast

| **Route** | `/dashboard?tab=broadcast` |
| **Component** | `dashboard-broadcast-admin.tsx` |

**GET:** `broadcast-queue`

**Mutate:** `broadcast_send`، `broadcast_cancel`، `broadcast_run_worker`

**Jobs:** `BroadcastWorkerJob`

---

#### G.2 Marketing Lifecycle

| **Route** | `/dashboard?tab=marketing_lifecycle` |
| **Component** | `dashboard-marketing-lifecycle-admin.tsx` |

**Mutate:** `marketing_rule_save`، `marketing_rule_delete`، `marketing_send_manual`، `marketing_run_rule_now`

**Jobs:** `MarketingJob`, `IdleOffersJob`

---

#### G.3 Referral Settings

| **Route** | `/dashboard?tab=referral` |
| **Component** | `dashboard-referral-admin.tsx` (mode=settings) |

**Mutate:** `settings_tab` (tab=`referral`)

---

#### G.4 Referral Reports

| **Route** | `/dashboard?tab=referral_reports` |
| **Component** | `dashboard-referral-admin.tsx` (mode=reports) |

**Models:** `SvpReferralEvent`

---

#### G.5 Resellers

| **Route** | `/dashboard?tab=resellers` |
| **Component** | `dashboard-resellers-admin.tsx` |

**Mutate:** `reseller_permissions_save`، `reseller_wp_provision`، `reseller_bind_users`، `reseller_panel_prices_save`، `wholesale_line_save`، `wholesale_line_delete`، `reseller_wholesale_lines_assign`، `reseller_backfill_run`

**Models:** `SvpUser`, `SvpResellerClosure`, wholesale tables

---

#### G.6 Reseller Reports

| **Route** | `/dashboard?tab=reseller_reports` |
| **Component** | `dashboard-reseller-reports-admin.tsx` |

**Acceptance criteria:**
- [ ] stats + daily chart
- [ ] impersonate از admin

---

#### G.7 Reseller Settings

| **Route** | `/dashboard?tab=reseller_settings` |
| **Component** | `dashboard-reseller-settings.tsx` |
| **Roles** | reseller |

**Mutate:** `reseller_inbound_labels_save`، `reseller_payment_methods_save`

---

### گروه H — System

#### H.1 L2TP Servers

| **Route** | `/dashboard?tab=l2tp_servers` |
| **Component** | `dashboard-l2tp-admin.tsx` |
| **Module** | l2tp (feature flag) |

**Mutate:** `l2tp_add`، `l2tp_update`، `l2tp_delete`

**Models:** `SvpL2tpServer`

---

#### H.2 Backup

| **Route** | `/dashboard?tab=backup` |
| **Component** | `dashboard-backup-admin.tsx` |

**GET/POST:** `backups`، `backup/status`، `backup/run`، `backup/download`، `backup/restore`

**Jobs:** `BackupJob`

---

#### H.3 Audit

| **Route** | `/dashboard?tab=audit` |
| **Component** | `dashboard-audit-admin.tsx` |

**GET:** `admin/audit`

**Models:** `SvpAuditLog`

**Acceptance criteria:**
- [ ] filter domain/event_type/q
- [ ] pagination
- [ ] impersonation events visible

---

## ۱۵. لیست کامل Mutate Ops

> Endpoint یکسان: `POST /api/v1/dashboard/admin/mutate` با body `{ "op": "...", ...params }`  
> منبع: `includes/admin/class-dashboard-admin-mutations.php` (۱۴۱ op)

| # | Op | Module | Page/Context | Reseller Perm |
|---|-----|--------|--------------|---------------|
| 1 | `settings_tab` | core | site_settings (all subtabs) | — |
| 2 | `force_join_publish` | telegram/bale | bots/force_join | — |
| 3 | `receipt_reject_reasons_save` | core | site_settings/finance یا receipts | — |
| 4 | `telegram_proxy_test` | telegram | site_settings/proxy | — |
| 5 | `telegram_relay_test` | relay | site_settings/relay | — |
| 6 | `telegram_relay_sync` | relay | site_settings/relay | — |
| 7 | `telegram_relay_set_webhook` | relay | site_settings/relay | — |
| 8 | `telegram_relay_rotate_secret` | relay | site_settings/relay | — |
| 9 | `telegram_relay_status` | relay | site_settings/relay | — |
| 10 | `telegram_relay_domains_sync` | relay | site_settings/relay | — |
| 11 | `telegram_relay_set_webhook_reseller` | relay | reseller_bots | services.manage |
| 12 | `telegram_relay_admin_dashboard` | relay | relay control center | — |
| 13 | `telegram_relay_admin_doctor` | relay | relay control center | — |
| 14 | `telegram_relay_admin_logs` | relay | relay control center | — |
| 15 | `telegram_relay_admin_ssl_status` | relay | relay control center | — |
| 16 | `telegram_relay_admin_domain_add` | relay | relay control center | — |
| 17 | `telegram_relay_admin_domain_remove` | relay | relay control center | — |
| 18 | `telegram_relay_admin_nginx_render` | relay | relay control center | — |
| 19 | `telegram_relay_admin_nginx_test` | relay | relay control center | — |
| 20 | `telegram_relay_admin_nginx_reload` | relay | relay control center | — |
| 21 | `telegram_relay_admin_ssl_issue` | relay | relay control center | — |
| 22 | `telegram_relay_admin_ssl_renew` | relay | relay control center | — |
| 23 | `telegram_relay_admin_service_restart` | relay | relay control center | — |
| 24 | `telegram_relay_admin_update` | relay | relay control center | — |
| 25 | `telegram_relay_admin_job` | relay | relay control center | — |
| 26 | `telegram_relay_auto_sync` | relay | site_settings/relay | — |
| 27 | `logs_clear` | core | site_settings/logs | — |
| 28 | `plan` | core | plans | plans.manage |
| 29 | `plan_category` | core | plan_cats | plans.manage |
| 30 | `panel_xp` | xui_panel | xui_panels | — |
| 31 | `panel_test` | xui_panel | xui_panels | — |
| 32 | `crypto_settings` | crypto | site_settings/finance | — |
| 33 | `unit_economics_save` | xui_panel | unit_economics | — |
| 34 | `unit_economics_config_save` | xui_panel | unit_economics | — |
| 35 | `panel_economics_save` | xui_panel | xui_panels | — |
| 36 | `shared_economics_save` | xui_panel | xui_panels | — |
| 37 | `panel_economics_mark_paid` | xui_panel | xui_panels | — |
| 38 | `card_add` | core | cards | plans.manage |
| 39 | `card_update` | core | cards | plans.manage |
| 40 | `card_delete` | core | cards | plans.manage |
| 41 | `card_reorder` | core | cards | plans.manage |
| 42 | `reseller_payment_methods_save` | reseller | cards/reseller_settings | plans.manage |
| 43 | `l2tp_add` | l2tp | l2tp_servers | — |
| 44 | `l2tp_update` | l2tp | l2tp_servers | — |
| 45 | `l2tp_delete` | l2tp | l2tp_servers | — |
| 46 | `texts_save` | core | texts | — |
| 47 | `text_reset_one` | core | texts | — |
| 48 | `texts_reset` | core | texts | — |
| 49 | `bot_ui_layout_save` | core | bot_ui | — |
| 50 | `bot_ui_layout_reset` | core | bot_ui | — |
| 51 | `membership` | core | users | users.manage |
| 52 | `receipt_set_status` | core | receipts/user detail | receipts.review |
| 53 | `receipt_action` | core | receipts/user detail | receipts.review |
| 54 | `receipt_update` | core | receipts | receipts.review |
| 55 | `broadcast_send` | core | broadcast | broadcast.send |
| 56 | `broadcast_cancel` | core | broadcast | broadcast.send |
| 57 | `broadcast_run_worker` | core | broadcast | — |
| 58 | `discount_save` | core | discounts (portal) | — |
| 59 | `discount_delete` | core | discounts (portal) | — |
| 60 | `discount_redemptions` | core | discounts | plans.manage |
| 61 | `marketing_rule_save` | marketing | marketing_lifecycle | — |
| 62 | `marketing_rule_delete` | marketing | marketing_lifecycle | — |
| 63 | `marketing_send_manual` | marketing | marketing_lifecycle | — |
| 64 | `marketing_run_rule_now` | marketing | marketing_lifecycle | — |
| 65 | `link_wp_user` | core | users | — |
| 66 | `service_delete` | core | user detail | services.manage |
| 67 | `service_apply_canonical_panel_identity` | xui_panel | user detail | — |
| 68 | `user_status` | core | users | users.manage |
| 69 | `user_balance_delta` | core | user detail | users.manage |
| 70 | `user_create_service` | xui_panel | user detail | services.manage |
| 71 | `user_renew_service` | xui_panel | user detail | services.manage |
| 72 | `user_add_volume` | xui_panel | user detail | services.manage |
| 73 | `user_reduce_volume` | xui_panel | user detail | services.manage |
| 74 | `user_add_days` | xui_panel | user detail | services.manage |
| 75 | `user_reduce_days` | xui_panel | user detail | services.manage |
| 76 | `user_service_reduce_slots` | xui_panel | user detail | services.manage |
| 77 | `user_service_transfer` | xui_panel | user detail | services.manage |
| 78 | `user_manual_create` | core | users | users.manage |
| 79 | `user_merge_preview` | core | users/merge | — |
| 80 | `user_merge` | core | users/merge | — |
| 81 | `users_bulk_wallet` | core | users_bulk | users.bulk |
| 82 | `users_bulk_volume` | core | users_bulk | users.bulk |
| 83 | `users_bulk_extend` | core | users_bulk | users.bulk |
| 84 | `users_bulk_alerts` | core | users_bulk | users.bulk |
| 85 | `users_bulk_slots` | core | users_bulk | users.bulk |
| 86 | `users_bulk_run_worker` | core | users_bulk | — |
| 87 | `users_bulk_job_cancel` | core | users_bulk | users.bulk |
| 88 | `users_bulk_job_resume` | core | users_bulk | users.bulk |
| 89 | `reseller_wallet_topup_checkout` | reseller | reseller_charge | plans.manage |
| 90 | `reseller_wp_provision` | reseller | resellers | users.manage |
| 91 | `reseller_panel_prices_save` | reseller | resellers/reseller_xui | users.manage |
| 92 | `wholesale_line_save` | reseller | resellers | — |
| 93 | `wholesale_line_delete` | reseller | resellers | — |
| 94 | `reseller_wholesale_lines_assign` | reseller | resellers | — |
| 95 | `reseller_permissions_save` | reseller | resellers | — |
| 96 | `reseller_bot_tokens_save` | reseller | reseller_bots | services.manage |
| 97 | `reseller_bot_webhook_set` | reseller | reseller_bots | services.manage |
| 98 | `reseller_bot_secret_rotate` | reseller | reseller_bots | services.manage |
| 99 | `reseller_bind_users` | reseller | resellers | — |
| 100 | `user_set_role` | core | user detail | — |
| 101 | `user_set_referrer` | core | user detail | — |
| 102 | `user_service_toggle_enable` | xui_panel | user detail | services.manage |
| 103 | `reseller_backfill_run` | reseller | resellers | — |
| 104 | `inbound_link` | xui_panel | user detail | — |
| 105 | `inbound_autolink` | xui_panel | user detail | — |
| 106 | `user_admin_message` | core | user detail | users.manage |
| 107 | `service_alerts_patch` | core | user detail | services.manage |
| 108 | `service_set_note` | core | user detail | services.manage |
| 109 | `service_panel_sync` | xui_panel | user detail | services.manage |
| 110 | `service_regen_key` | xui_panel | user detail | services.manage |
| 111 | `service_regen_sub_id` | xui_panel | user detail | services.manage |
| 112 | `service_panel_refresh` | xui_panel | user detail | services.manage |
| 113 | `service_panel_delete_client` | xui_panel | user detail | services.manage |
| 114 | `user_service_add_slots` | xui_panel | user detail | services.manage |
| 115 | `service_set_limit_ip` | xui_panel | user detail | services.manage |
| 116 | `configs_client_toggle_enable` | xui_panel | configs | — |
| 117 | `configs_client_reset_traffic` | xui_panel | configs | — |
| 118 | `configs_client_delete` | xui_panel | configs | — |
| 119 | `configs_delete_expired_linked` | xui_panel | configs | — |
| 120 | `purge_expired_run_cron` | core | site_settings/purge | — |
| 121 | `purge_expired_purge_ready` | core | site_settings/purge | — |
| 122 | `purge_expired_purge_one` | core | site_settings/purge | — |
| 123 | `configs_panel_client_patch` | xui_panel | configs | — |
| 124 | `configs_clients_batch` | xui_panel | configs | — |
| 125 | `configs_assign_plan` | xui_panel | configs | — |
| 126 | `service_panel_transfer` | xui_panel | configs/user detail | — |
| 127 | `bot_toggle_enabled` | telegram/bale | bots | — |
| 128 | `bot_toggle_platform_enabled` | telegram/bale | bots | — |
| 129 | `bot_test_telegram` | telegram | bots | services.manage |
| 130 | `bot_test_bale` | bale | bots | services.manage |
| 131 | `bot_diagnostics` | telegram/bale | bots | services.manage |
| 132 | `bot_set_webhook` | telegram/bale | bots | — |
| 133 | `bot_delete_webhook` | telegram/bale | bots | — |
| 134 | `reseller_bot_webhook_delete` | reseller | reseller_bots | services.manage |
| 135 | `bot_admin_id_add` | telegram/bale | bots | services.manage |
| 136 | `bot_admin_id_remove` | telegram/bale | bots | services.manage |
| 137 | `bot_reseller_toggle_enabled` | reseller | reseller_bots | services.manage |
| 138 | `bot_reseller_secret_rotate` | reseller | reseller_bots | services.manage |
| 139 | `bot_reseller_delete` | reseller | reseller_bots | — |
| 140 | `bot_reseller_save` | reseller | reseller_bots | services.manage |
| 141 | `reseller_inbound_labels_save` | reseller | reseller_xui/reseller_settings | services.manage |

### ۱۵.۱ Settings Tab Keys (داخل `settings_tab`)

| tab param | محتوا |
|-----------|--------|
| `general` | enabled، test account |
| `bots` | tokens، platform enabled |
| `panel` | legacy single panel (deprecated) |
| `backup` | backup settings |
| `whitelabel` | branding |
| `service_naming` | labels |
| `relay` | relay connection |
| `proxy` | telegram proxy |
| `resellers_defaults` | default permissions |
| `notifications` | alert thresholds |
| `purge_expired` | purge policy |
| `finance` | pricing defaults |
| `plans_catalog` | catalog defaults |
| `referral` | referral program |
| `cards` | global card display |
| `force_join` | channel gate |
| `receipts` | reject reasons |

---

## ۱۶. فازبندی ۰–۱۲

### فاز ۰ — آماده‌سازی (۱ هفته)

**کارها:** repo layout، Docker skeleton، CI pipeline، `backend/` Laravel 11 init

**معیار پذیرش:**
- [ ] `docker compose up` → nginx + mysql + redis + app healthy
- [ ] `php artisan test` green (smoke)
- [ ] `frontend` build به `frontend/dist/` و mount در nginx

---

### فاز ۱ — Core + Database (۲ هفته)

**کارها:** ۴۳ migration، Models، `SettingsService`، `SvpSetting`

**معیار پذیرش:**
- [ ] `php artisan migrate` بدون خطا
- [ ] Model factories برای users/services
- [ ] settings CRUD unit test

---

### فاز ۲ — Auth + Dashboard Bootstrap (۱ هفته)

**کارها:** Sanctum، login، bootstrap، `admin/state` skeleton

**معیار پذیرش:**
- [ ] login از React SPA کار کند
- [ ] bootstrap `features`، `branding`، `navTabs` برگردد
- [ ] role admin/reseller تشخیص داده شود

---

### فاز ۳ — Admin State Aggregator (۲ هفته)

**کارها:** port `route_admin_state` از WP — تمام list payloads + pagination

**معیار پذیرش:**
- [ ] تب users، plans، panels داده واقعی نشان دهند
- [ ] pagination keys سازگار با SPA
- [ ] reseller scoping verified

---

### فاز ۴ — Mutate Dispatcher (۳ هفته)

**کارها:** `MutateController` + ۱۴۱ handler classes یا grouped actions

**معیار پذیرش:**
- [ ] smoke test هر op → `{ok:true}` یا خطای معنادار
- [ ] reseller policy matrix enforce شود
- [ ] audit log برای ops حساس

---

### فاز ۵ — Telegram + Bale Modules (۲ هفته)

**کارها:** webhooks، bot handlers port، keyboards، texts

**معیار پذیرش:**
- [ ] buy flow end-to-end در staging
- [ ] service delivery بعد از receipt approve
- [ ] rate limit webhook تست شود

---

### فاز ۶ — XUI Panel Module (۲ هفته)

**کارها:** XuiClient، provision، configs sync، panel crons

**معیار پذیرش:**
- [ ] create service روی 3x-ui
- [ ] configs snapshot + batch ops
- [ ] panel_online cron data

---

### فاز ۷ — Reseller Module (۲ هفته)

**کارها:** closure tree، permissions، bot profiles، wholesale

**معیار پذیرش:**
- [ ] reseller login + scoped data
- [ ] sub-reseller hierarchy
- [ ] reseller bot webhook

---

### فاز ۸ — Relay Module (۱ هفته)

**کارها:** `TelegramRelayService`، admin proxy ops

**معیار پذیرش:**
- [ ] sync config/domains با VPS relay
- [ ] set webhook via relay
- [ ] control center ops از dashboard

---

### فاز ۹ — Marketing + Broadcast + Bulk (۱.۵ هفته)

**کارها:** broadcast queue، users bulk jobs، marketing rules

**معیار پذیرش:**
- [ ] broadcast 1000+ users بدون timeout
- [ ] bulk wallet job complete
- [ ] marketing cron sends offers

---

### فاز ۱۰ — Backup + Crypto + L2TP (۱.۵ هفته)

**کارها:** backup zip، restore، NOWPayments IPN، L2TP CRUD

**معیار پذیرش:**
- [ ] backup دانلود و restore در staging
- [ ] crypto IPN → transaction confirmed
- [ ] L2TP tab با feature flag

---

### فاز ۱۱ — wp:import + Cutover (۱ هفته)

**کارها:** import command، data validation، DNS cutover

**معیار پذیرش:**
- [ ] import از DB وردپرس بدون از دست رفتن داده
- [ ] row counts match
- [ ] parallel run WP+Laravel در staging

---

### فاز ۱۲ — Production Hardening (۱ هفته)

**کارها:** observability، load test، runbook، WP decommission

**معیار پذیرش:**
- [ ] ۲۴h soak test بدون error spike
- [ ] alerting روی panel down
- [ ] WP خاموش — فقط Laravel

---

## ۱۷. Migration WP → Laravel

### ۱۷.۱ Command

```bash
php artisan wp:import \
  --wp-prefix=wp_ \
  --wp-host=127.0.0.1 \
  --wp-database=wordpress \
  --wp-user=root \
  --wp-password=secret \
  --dry-run
```

### ۱۷.۲ مراحل Import

| Step | Source | Target | Notes |
|------|--------|--------|-------|
| 1 | `wp_svp_*` tables | `svp_*` | direct copy اگر prefix فقط wp_ است |
| 2 | `wp_options.simplevpbot_settings` | `svp_settings` | JSON decode + encrypt secrets |
| 3 | `wp_options.simplevpbot_reseller_perms_*` | `svp_settings` key `reseller_perms.{id}` | |
| 4 | `wp_usermeta.svp_dashboard_accent` | `users.meta` | برای اپراتورها |
| 5 | `wp_users` (admins) | `users` | role mapping |
| 6 | Uploads/backup zips | `storage/app/backups` | optional path |
| 7 | Verify counts | — | per-table diff report |

### ۱۷.۳ Idempotency

- `--force` برای overwrite
- default: skip if target row exists (by id)
- transaction per table

### ۱۷.۴ Post-Import Checklist

```bash
php artisan wp:import --verify-only
php artisan svp:rebuild-reseller-closure
php artisan svp:register-webhooks
php artisan schedule:list
```

### ۱۷.۵ Rollback

- snapshot MySQL قبل از cutover
- DNS revert به WP
- relay config → forward URL قدیمی

---

## ۱۸. Observability، Rate Limits، Error Format

### ۱۸.۱ Error Response Format

تمام APIها (mutate، REST، webhook ack):

```json
{
  "ok": true,
  "message": "saved",
  "data": {}
}
```

```json
{
  "ok": false,
  "message": "forbidden"
}
```

**کدهای message پرکاربرد:**

| message | HTTP | معنی |
|---------|------|------|
| `saved` | 200 | موفق |
| `forbidden` | 403 | نقش/permission ناکافی |
| `not_found` | 404 | رکورد نیست |
| `invalid_tab` | 400 | settings tab نامعتبر |
| `unknown_op` | 400 | op ناشناخته |
| `rate_limited` | 429 | بیش از حد درخواست |
| `panel_error` | 502 | 3x-ui unreachable |
| `relay_error` | 502 | relay VPS error |

### ۱۸.۲ Rate Limits

| Endpoint | Limit | Store |
|----------|-------|-------|
| Webhook main | 120/min per IP | Redis |
| Webhook reseller | 60/min per IP | Redis |
| Dashboard login | 10/min per IP | Redis |
| `admin/mutate` | 300/min per user | Redis |
| `admin/state` | 60/min per user | Redis |

```php
// Trust X-Forwarded-For only when:
config('svp.rate_limit_trust_forwarded_for') === true
```

### ۱۸.۳ Logging

```php
Log::channel('svp')->info('webhook.received', [
    'platform' => $platform,
    'update_id' => $updateId,
    // no tokens
]);
```

**Channels:** `svp`, `svp-webhook`, `svp-panel`, `svp-relay`

### ۱۸.۴ Health Endpoints

| Route | Purpose |
|-------|---------|
| `GET /health` | liveness (app up) |
| `GET /health/ready` | DB + Redis connected |
| `GET /health/deep` | panel sample ping (admin token) |

### ۱۸.۵ Metrics (Phase 12+)

- Prometheus exporter optional
- Counters: `webhook_received_total`, `mutate_op_total`, `cron_job_duration_seconds`
- Grafana dashboard template در `docker/grafana/`

### ۱۸.۶ Alerting Rules

| Alert | Condition |
|-------|-----------|
| PanelDown | `AdminAlertsJob` detects unreachable > 5min |
| WebhookQueueBacklog | `svp_inbound_queue` > 1000 rows |
| BackupFailed | last backup > 2x interval |
| RelayUnreachable | relay test fails 3x |

---

## پیوست الف — نگاشت Component → Tab

| tabKey | Component Path |
|--------|----------------|
| `dashboard` | `components/dashboard-overview.tsx` |
| `monitoring` | `components/dashboard-monitoring.tsx` |
| `site_settings` | `components/dashboard-site-settings-admin.tsx` |
| `users` | `components/dashboard-users-admin.tsx` |
| `users` (detail) | `components/dashboard-user-detail-admin.tsx` |
| `users_bulk` | `components/dashboard-users-bulk-admin.tsx` |
| `bots` | `components/dashboard-bots-admin.tsx` |
| `reseller_bots` | `components/dashboard-bots-admin.tsx` |
| `texts` | `components/dashboard-texts-admin.tsx` |
| `bot_ui` | `components/dashboard-bot-ui-studio.tsx` |
| `xui_panels` | `components/dashboard-panels-admin.tsx` |
| `configs` | `components/dashboard-configs-admin.tsx` |
| `reseller_xui_panels` | `components/dashboard-reseller-panels-admin.tsx` |
| `plans` | `components/dashboard-plans-admin.tsx` |
| `plan_cats` | `components/dashboard-plan-cats-admin.tsx` |
| `cards` | `components/dashboard-cards-admin.tsx` |
| `receipts` | `components/dashboard-receipts-admin.tsx` |
| `unit_economics` | `components/dashboard-unit-economics-admin.tsx` |
| `reseller_charge` | `components/dashboard-reseller-charge-admin.tsx` |
| `broadcast` | `components/dashboard-broadcast-admin.tsx` |
| `marketing_lifecycle` | `components/dashboard-marketing-lifecycle-admin.tsx` |
| `referral` | `components/dashboard-referral-admin.tsx` |
| `referral_reports` | `components/dashboard-referral-admin.tsx` |
| `resellers` | `components/dashboard-resellers-admin.tsx` |
| `reseller_reports` | `components/dashboard-reseller-reports-admin.tsx` |
| `reseller_settings` | `components/dashboard-reseller-settings.tsx` |
| `l2tp_servers` | `components/dashboard-l2tp-admin.tsx` |
| `backup` | `components/dashboard-backup-admin.tsx` |
| `audit` | `components/dashboard-audit-admin.tsx` |
| login | `components/dashboard-login.tsx` |

---

## پیوست ب — تغییرات لازم در Dashboard UI (حداقل)

1. `restUrl` → `/api/v1` (بدون `wp-json`)
2. حذف `X-WP-Nonce` → Sanctum CSRF
3. `ajaxUrl` / portal → `/api/v1/portal/admin`
4. بدون تغییر tab keys و `admin/state` query params

---

**پایان سند**
