# Deployment Guide

This document is the operational reference for deploying the **Bekie Service** Laravel 12 API to Render. It is the source of truth for service topology, environment variables, migration strategy, and the rollout procedure. The contents of `render.yaml` and `Dockerfile` are derived from this guide.

For day-to-day development and architecture, see `CLAUDE.md`.

---

## 1. Architecture

The application is deployed as three Render services. No additional services are required.

### 1.1 Service inventory

| Service | Type | Purpose | Why |
|---|---|---|---|
| `laravel-app` | Web (Docker) | Laravel HTTP API + Scramble docs UI | The application. Built from `Dockerfile`. |
| `bekie-postgres` | PostgreSQL (managed) | Primary database | Holds all application data. |
| `bekie-redis` | Redis (Key Value Store) | Cache, session, queue | Backs `CACHE_STORE=redis`, `SESSION_DRIVER=redis`, `QUEUE_CONNECTION=redis`. |

### 1.2 Services intentionally NOT provisioned

- **No worker service.** The codebase has zero `app/Jobs/` files and zero `ShouldQueue` / `->dispatch()` call sites. The Redis queue driver is wired so jobs added later work without a config churn, but nothing is being processed today.
- **No cron service.** `routes/console.php` contains only the default `inspire` artisan command. There are no `Schedule::` / `everyXxx()` / `->cron(...)` calls anywhere in the project. The scheduler will be added when scheduled work is introduced.
- **No broadcast service (Pusher / Reverb / Soketi).** The `.env` ships `BROADCAST_CONNECTION=log` (events are written to the log channel, not fanned out). No `event()`, `->broadcast`, `Pusher`, `Reverb`, or `Soketi` references exist in the codebase.

### 1.3 Network topology

```
Internet ──► Render load balancer ──► laravel-app (Apache/PHP 8.2)
                                          │
                                          ├──► bekie-postgres (managed)
                                          └──► bekie-redis (managed)
```

The web service connects to Postgres and Redis over Render's internal network. The database and Redis services do not need external IP access.

---

## 2. Environment variables

Every environment variable the application reads is listed below. Set the ones marked **Required** in the Render dashboard before the first deploy. The `.env.example` file in the repository is the canonical local-development source; this section is the canonical production reference.

### 2.1 Application core (Required)

| Key | Value | Notes |
|---|---|---|
| `APP_ENV` | `production` | |
| `APP_DEBUG` | `false` | Never `true` in production. |
| `APP_URL` | `https://<your-service>.onrender.com` | Set to the Render external URL after first deploy. |
| `APP_KEY` | 32-char base64 key | Generate with `php artisan key:generate --show`. Paste into Render dashboard. |

### 2.2 Database (Required)

| Key | Value | Notes |
|---|---|---|
| `DB_CONNECTION` | `pgsql` | |
| `DB_HOST` | (from Render dashboard) | Internal hostname of `bekie-postgres`. |
| `DB_PORT` | `5432` | |
| `DB_DATABASE` | (from Render dashboard) | |
| `DB_USERNAME` | (from Render dashboard) | |
| `DB_PASSWORD` | (from Render dashboard) | |
| `DB_SSLMODE` | `require` | Render Postgres requires SSL. |

### 2.3 Redis (Required)

| Key | Value | Notes |
|---|---|---|
| `REDIS_CLIENT` | `phpredis` | PHP extension is installed in the Docker image. |
| `REDIS_HOST` | (from Render dashboard) | Internal hostname of `bekie-redis`. |
| `REDIS_PORT` | `6379` | |
| `REDIS_PASSWORD` | (from Render dashboard) | Render requires a password on managed Redis. |

### 2.4 Cache / session / queue (Required)

| Key | Value | Notes |
|---|---|---|
| `CACHE_STORE` | `redis` | |
| `SESSION_DRIVER` | `redis` | |
| `QUEUE_CONNECTION` | `redis` | Dormant — no jobs run today, but wired for future use. |

### 2.5 Logging (Required)

| Key | Value | Notes |
|---|---|---|
| `LOG_CHANNEL` | `stderr` | Render captures stdout/stderr for the log drain. |
| `LOG_LEVEL` | `info` | Use `debug` only for short-lived troubleshooting. |

### 2.6 JWT auth (Required)

| Key | Value | Notes |
|---|---|---|
| `JWT_SECRET` | 64-char hex string | Generate with `openssl rand -hex 32`. Only read in `app/Services/JwtService.php`; the secret defaults to `APP_KEY` if unset, but a dedicated secret is recommended. |

### 2.7 Cloudinary (Required for `/api/v1/admin/media`)

| Key | Value | Notes |
|---|---|---|
| `CLOUDINARY_CLOUD_NAME` | (from Cloudinary dashboard) | |
| `CLOUDINARY_API_KEY` | (from Cloudinary dashboard) | |
| `CLOUDINARY_API_SECRET` | (from Cloudinary dashboard) | |
| `CLOUDINARY_UPLOAD_PRESET` | (optional) | |
| `CLOUDINARY_SECURE` | `true` | Always serve over HTTPS. |
| `CLOUDINARY_FOLDER` | `bekie` | Name-spacing for production uploads. |

### 2.8 Scramble API docs (Required)

| Key | Value | Notes |
|---|---|---|
| `SCRAMBLE_ENABLED` | `false` | Gated. Flip to `true` in the dashboard when you want `/docs/client` and `/docs/admin` reachable. |
| `API_VERSION` | `1.0.0` | Used in the OpenAPI `info.version` field. |

### 2.9 Hardening (Required)

| Key | Value | Notes |
|---|---|---|
| `FILESYSTEM_DISK` | `local` | All file uploads go to Cloudinary. The local disk is dormant. |
| `SESSION_SECURE_COOKIE` | `true` | |
| `SESSION_SAME_SITE` | `lax` | |

### 2.10 Mail (placeholder — see section 5)

| Key | Value | Notes |
|---|---|---|
| `MAIL_MAILER` | `log` | **PLACEHOLDER.** Swap to a transactional provider before the first mail-sending feature ships. |
| `MAIL_FROM_ADDRESS` | (e.g. `no-reply@bekie.com`) | |
| `MAIL_FROM_NAME` | (e.g. `Bekie`) | |

### 2.11 Variables NOT required in production

The following are read from `env()` but default safely when unset, or are unused code paths. They are listed here for completeness; do not bother setting them in Render.

- `SESSION_LIFETIME`, `SESSION_ENCRYPT`, `SESSION_PATH`, `SESSION_DOMAIN`, `SESSION_HTTP_ONLY`, `SESSION_PARTITIONED_COOKIE` — defaults are fine.
- `BROADCAST_CONNECTION` — defaults to `log` (events go to the log channel, not networked).
- `BCRYPT_ROUNDS` — defaults to `12`.
- `AWS_*` — S3 disk is configured but unused. No code writes to S3.
- `MEMCACHED_*`, `BEANSTALKD_*`, `SQS_*`, `DYNAMODB_*` — alternative drivers not in use.
- `SLACK_*`, `PAPERTRAIL_*`, `LOG_DAILY_DAYS`, `LOG_STDERR_FORMATTER`, `LOG_SYSLOG_FACILITY` — log channel alternatives not in use.
- `POSTMARK_API_KEY`, `RESEND_API_KEY` — marketing mail drivers not in use.
- `SANCTUM_STATEFUL_DOMAINS` — Sanctum is installed but not used (custom JWT auth instead).
- `API_LOCALE`, `APP_FALLBACK_LOCALE`, `APP_FAKER_LOCALE` — defaults are fine.
- `CACHE_PREFIX`, `REDIS_PREFIX`, `REDIS_PERSISTENT`, `REDIS_CLUSTER`, `REDIS_USERNAME`, `REDIS_DB`, `REDIS_CACHE_DB`, `REDIS_MAX_RETRIES`, `REDIS_BACKOFF_*` — defaults are fine.
- `DB_SOCKET`, `DB_CHARSET`, `DB_COLLATION`, `DB_FOREIGN_KEYS`, `DB_CACHE_*`, `DB_QUEUE_*` — defaults are fine.
- `AUTH_*` — defaults are fine.
- `QUEUE_FAILED_DRIVER`, `BEANSTALKD_*`, `SQS_*`, `REDIS_QUEUE_*` — defaults are fine.
- `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_SCHEME`, `MAIL_URL`, `MAIL_EHLO_DOMAIN`, `MAIL_SENDMAIL_PATH`, `MAIL_LOG_CHANNEL` — unused until `MAIL_MAILER` is moved off `log`.

---

## 3. Build and release

### 3.1 Build (Docker)

The `Dockerfile` is a two-stage build:

1. **Stage 1 (`node:20-alpine AS frontend`):** installs npm dependencies, runs `npm run build`. Produces `public/build/`.
2. **Stage 2 (`php:8.2-apache`):** installs system libraries (libpq, libzip, libicu, libonig, libxml2, libpng, libjpeg, libfreetype, libwebp), PHP extensions (`pdo`, `pdo_pgsql`, `mbstring`, `zip`, `intl`, `bcmath`, `exif`, `pcntl`, `gd`), and the Redis extension via PECL. Copies the application, copies the Vite build output, runs `composer install --no-dev --no-scripts`, runs `php artisan package:discover`, then clears config/route/view/cache (the cache step is **not** optional — Render injects env vars at runtime, so caching them at build time would freeze empty values).

No Dockerfile changes are required for the first deploy.

### 3.2 Release (Render `preDeployCommand`)

```bash
php artisan migrate --force
```

This runs before each deploy on the Starter plan (and above). On the Free plan, `preDeployCommand` is silently skipped and you must run `php artisan migrate --force` manually from the Render shell once after the first deploy.

### 3.3 Health check

Render polls `GET /up` every few seconds. This is the Laravel 12 default health endpoint registered in `bootstrap/app.php`. The endpoint returns HTML with HTTP 200; Render accepts any 2xx response.

### 3.4 Migration safety

Laravel migrations are forward-only and idempotent at the migration level (`migrate` is safe to re-run after a prior failure). The migration history in `database/migrations/` includes:

- `2026_05_20_000000_add_is_admin_to_users_table` — adds `is_admin` boolean; non-blocking.
- `2026_05_20_000001_add_scope_to_api_tokens_table` — adds `scope` varchar; non-blocking.

Both are additive and small. The full migration history should be reviewed once before first deploy to check for any large table rewrites (e.g., column type changes on `users` or `orders`).

**Rollback** is `php artisan migrate:rollback --force`. If a deployment fails, the previous image is still running on Render — no rollback is needed unless the failure was a migration that left the schema in a broken state. In that case, restore the database from Render's automated backup.

### 3.5 Ephemeral filesystem

Render containers have an ephemeral filesystem. Anything written to disk is lost on restart. The application is configured to route all persistent state to managed services:

- **Cache** → Redis
- **Session** → Redis
- **Queue** → Redis (dormant)
- **Media uploads** → Cloudinary (via `app/Http/Controllers/Api/Admin/V1/MediaController.php`, which uploads directly to Cloudinary over HTTPS — never touches local disk)
- **Scramble OpenAPI spec cache** → file cache at `storage/framework/cache/data/scramble`. The cache misses on every cold start (first request after a deploy, or after a restart), causing the spec to regenerate. This is acceptable: the cache is a perf optimization, not a correctness one. The first request to `/docs/client.json` or `/docs/admin.json` after a deploy will be slower than subsequent ones.

There are no other local-disk writes in the application code.

---

## 4. Route cleanup

`routes/web.php` originally contained 11 route definitions pointing at controllers in the `App\Http\Controllers\Admin\` namespace (singular `Admin`). These controllers were consolidated into `App\Http\Controllers\Api\Admin\V1\` (the canonical V1 namespace) during the Phase B refactor, but the legacy `routes/web.php` references were never updated. The result: anyone hitting `/admin/login`, `/admin/dashboard`, `/admin/customers`, etc. would receive a `ReflectionException` (HTTP 500).

The cleanup: **delete the 11 broken route definitions from `routes/web.php`.** The admin surface now lives entirely under the JSON API at `/api/v1/admin/*` (see `routes/api_admin.php`). The web admin panel is no longer a deployment target of this service.

**What was deleted (with line numbers from the prior version):**
- Lines 29–32: `guest:web` group with `/admin/login` GET and POST (referenced `App\Http\Controllers\Admin\AuthController`).
- Lines 34–61: `/admin` prefix group with dashboard, customers, orders, products, categories, promotions, content, logs, reports, and logout routes (referenced `App\Http\Controllers\Admin\DashboardController`, `CustomerController`, `OrderController`, `ProductController`, `CategoryController`, `PromotionController`, `ContentController`, `LogController`, `ReportExportController`, `AuthController`).

**What was kept:**
- `Route::get('/')` returns the welcome view.
- The four `Route::redirect` calls from `/api/docs*` to `/docs/*` (Scramble migration redirects).

**What was unchanged:**
- `app/Http/Middleware/EnsureAdminWebAccess.php` is left in place. It is a working middleware; it is only reachable through the removed routes, but it is not itself broken.

**Impact:**
- No test references the removed routes.
- No view file references the removed routes.
- The `welcome.blade.php` template uses `Route::has('login')` and `Route::has('register')` which already return `false` (the default auth scaffolding is not in use); it is unaffected by the removal.
- The Docker build was previously affected by `php artisan route:clear` and `php artisan route:cache` because route reflection happens in `RouteListCommand`. Verified: both `route:clear` and `route:cache` succeed even with the broken routes (they don't reflect); only `route:list` crashes. The Dockerfile did not need changes.

---

## 5. Mail

`MAIL_MAILER=log` is set in `render.yaml` as a placeholder. The `log` driver writes mail to `storage/logs/laravel.log`, which is ephemeral on Render — mail is lost on restart.

**Why this is safe today:** the codebase has zero active mail-sending code paths. Grepping for `Mail::`, `Mailable`, `->notify()`, `->send()`, and `Notification` across `app/` returns only the `Notifiable` trait declared on the `User` model — nothing calls `->notify()` on it. There is no `app/Mail/` directory, no `app/Notifications/` directory, and no `app/Jobs/` directory. Authentication uses a custom JWT (`AuthenticateApiToken` / `AuthenticateAdminApiToken`) and does not touch Laravel's password reset broker.

**When to swap:** before shipping the first feature that sends mail (password resets, admin invites, order confirmations, notifications). At that point, choose a provider and update `render.yaml` plus the dashboard:

- **Resend:** `MAIL_MAILER=resend`, `RESEND_API_KEY=...`. Add `RESEND_API_KEY` to dashboard.
- **Postmark:** `MAIL_MAILER=postmark`, `POSTMARK_API_KEY=...`. Add `POSTMARK_API_KEY` to dashboard.
- **SES (via `aws/ses`):** `MAIL_MAILER=ses`, `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`. Configure the `services.ses` block in `config/services.php` if the default doesn't fit.

The provider keys must be added to `render.yaml` as `sync: false` entries and populated in the Render dashboard.

---

## 6. Rollout procedure (staging-first)

Do not promote to production before the staging instance has validated the full request lifecycle.

### 6.1 One-time setup

1. **Provision staging services in Render:**
   - `bekie-app-staging` (Web, Docker, Starter plan, region: singapore)
   - `bekie-postgres-staging` (PostgreSQL, Starter or Free)
   - `bekie-redis-staging` (Key Value Store)

2. **Set the env vars** in the staging dashboard per section 2. Use staging-specific values for `APP_URL`, `APP_KEY`, `JWT_SECRET`, Cloudinary credentials (use a separate Cloudinary folder), and `MAIL_FROM_ADDRESS`.

3. **Connect the staging web service to the staging database and Redis** ("Link Environment" in Render).

4. **Push to the staging branch** (Render auto-deploys on every commit when `autoDeployTrigger: commit`).

### 6.2 Staging validation

1. Visit `https://<staging>.onrender.com/up` — should return 200 with HTML.
2. Hit `/api/v1/...` and `/api/v1/admin/...` — verify the canonical JSON envelope (`{status, message, data}`).
3. Run a full auth round-trip: `POST /api/v1/admin/auth/login` → token → `GET /api/v1/admin/...` with the bearer token.
4. Test the media upload at `/api/v1/admin/media` (POST an image, verify it lands in Cloudinary).
5. Hit `/docs/client` and `/docs/admin` — should return 403 (gated) since `SCRAMBLE_ENABLED=false`. Flip the env var to `true` and confirm the docs render.
6. Watch the Render logs for any stack traces during a 10-minute soak.

### 6.3 Production promotion

1. Provision the production services (separate Postgres + Redis, dedicated Cloudinary account).
2. Set production env vars in the dashboard. **Never reuse staging credentials.**
3. Generate a fresh `JWT_SECRET` and `APP_KEY` for production.
4. Deploy. The `preDeployCommand` will run `php artisan migrate --force` against the production database.
5. Smoke-test the same endpoints as staging.
6. Set up a log drain (Render built-in, or Datadog / Better Stack integration).

### 6.4 Rollback

If a deployment fails or produces incorrect behavior:

- **App rollback:** Render keeps the previous image. Trigger a manual deploy from the previous commit via the dashboard.
- **Database rollback:** `php artisan migrate:rollback --force` from the Render shell. Restore from the Render Postgres backup if necessary.
- **Service-level:** Delete a managed Postgres / Redis service only as a last resort; the data is unrecoverable without a backup.

---

## 7. Open issues tracked elsewhere

- **Pre-existing test failures (out of scope).** As of the deploy plan, `php artisan test` reports 23 failed / 5 passed. The failures originate in `app/Services/JwtService.php:11` where `env('JWT_SECRET', env('APP_KEY'))` is evaluated before `APP_KEY` is set in the test environment. These are application-code issues, not deployment issues, and are tracked separately.
- **Scramble file cache on ephemeral FS.** Acceptable; see section 3.5.
- **No log drain configured yet.** Section 6.3 step 6 must be done before opening the service to real traffic.
