# Laravel Reverb Deployment Checklist

Use this checklist when deploying the Bekie Service real-time notification feature for the micro-client web app, admin web app, and mobile app.

## 1. Deployment topology

Run the backend as three independently restartable processes/services:

- [ ] **HTTP API**: Laravel application serving `/api/*` and `/api/v1/broadcasting/auth`.
- [ ] **Queue worker**: processes queued database/broadcast notifications.
- [ ] **Reverb server**: runs `php artisan reverb:start` and accepts WebSocket connections.
- [ ] **PostgreSQL**: primary application database.
- [ ] **Redis**: queue, cache/session storage, and Reverb scaling/pub-sub when enabled.
- [ ] Put the API and Reverb behind HTTPS/WSS at the public edge.
- [ ] Use a shared Redis instance for every API, worker, and Reverb replica.
- [ ] Do not run Reverb as a short-lived release command or inside a one-shot migration process.

### Current repository deployment gap

The current `render.yaml`, `Dockerfile`, and `supervisord.conf` start the HTTP application but do not provision a queue worker or Reverb process. Add separate Render services, or update the container/process supervisor configuration, before enabling real-time production traffic.

Recommended Render services:

| Service | Type | Start command |
|---|---|---|
| `bekie-api` | Web | Existing HTTP container command |
| `bekie-worker` | Background worker | `php artisan queue:work redis --sleep=1 --tries=3 --timeout=90` |
| `bekie-reverb` | Web/private WebSocket service | `php artisan reverb:start --host=0.0.0.0 --port=$PORT` |

Use a dedicated public hostname for Reverb, for example `reverb.example.com`. Configure the edge/load balancer to support HTTP Upgrade and long-lived WebSocket connections.

## 2. Required environment variables

Set these in the deployment secret manager or platform dashboard. Never commit production values.

### Application and authentication

- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `APP_URL=https://api.example.com`
- [ ] `APP_KEY=<unique Laravel encryption key>`
- [ ] `JWT_SECRET=<unique production JWT signing secret>`
- [ ] Confirm `config('app.key')` is non-empty in the deployed container.

### Broadcasting and Reverb

- [ ] `BROADCAST_CONNECTION=reverb`
- [ ] `REVERB_APP_ID=<production app id>`
- [ ] `REVERB_APP_KEY=<production public key>`
- [ ] `REVERB_APP_SECRET=<production secret>`
- [ ] `REVERB_HOST=reverb.example.com`
- [ ] `REVERB_PORT=443` for the public client endpoint, or the platform's internal Reverb port where required by the broadcaster configuration.
- [ ] `REVERB_SCHEME=https`
- [ ] `REVERB_SERVER_HOST=0.0.0.0` on the Reverb process.
- [ ] `REVERB_SERVER_PORT=$PORT` or the internal listener port used by the Reverb service.
- [ ] `REVERB_ALLOWED_ORIGINS=https://micro.example.com,https://admin.example.com`
- [ ] Include every trusted web origin; do not use `*` in production unless there is a documented reason.
- [ ] Configure the mobile app with the Reverb app key, host, port, scheme, and `/api/v1/broadcasting/auth` authorization endpoint.

### Queue and storage

- [ ] `QUEUE_CONNECTION=redis` for production.
- [ ] `REDIS_CLIENT=phpredis` or the client selected by the runtime image.
- [ ] `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD`, and TLS settings are correct.
- [ ] `CACHE_STORE=redis`.
- [ ] `SESSION_DRIVER=redis` if sessions are enabled.
- [ ] `DB_CONNECTION=pgsql` and all database credentials are configured.
- [ ] Ensure the `jobs`, `failed_jobs`, `notifications`, and application tables exist after migration.

## 3. Secrets and access control

- [ ] Generate separate production values for `APP_KEY`, `JWT_SECRET`, and `REVERB_APP_SECRET`.
- [ ] Do not reuse local `.env` values in production.
- [ ] Restrict access to Redis and PostgreSQL to application services only.
- [ ] Enable TLS for external API and WebSocket traffic.
- [ ] Verify `/api/v1/broadcasting/auth` is not publicly usable without a valid client/admin bearer token.
- [ ] Confirm users can authorize only `private-user.{their-id}`.
- [ ] Confirm order channels authorize only the owning customer or an authorized admin.
- [ ] Confirm admin channels reject non-admin users.
- [ ] Ensure `REVERB_ALLOWED_ORIGINS` contains only approved browser origins.

## 4. Database migration and release order

Run release steps in this order:

- [ ] Build the image with production dependencies.
- [ ] Deploy or start the API with health checks enabled.
- [ ] Run migrations before enabling notification-producing traffic:

```bash
php artisan migrate --force
```

- [ ] Confirm the notification tables are present.
- [ ] Confirm the audience column migration for `content_items` has run.
- [ ] Clear and rebuild runtime configuration after environment variables are available:

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

- [ ] Start the queue worker only after the database and Redis are reachable.
- [ ] Start Reverb only after the Reverb credentials and public hostname are configured.
- [ ] Do not run `migrate` concurrently from multiple replicas.

## 5. Process configuration

### Queue worker

- [ ] Run a persistent worker, not `queue:listen` for production.
- [ ] Use `--tries=3` and a timeout shorter than the Redis queue `retry_after` value.
- [ ] Configure automatic restart on failure and deployment.
- [ ] Monitor failed jobs:

```bash
php artisan queue:failed
php artisan queue:retry all
```

- [ ] Ensure workers receive the same `APP_KEY`, `JWT_SECRET`, database, Redis, and Reverb environment as the API.
- [ ] Restart workers after every deployment that changes notification code:

```bash
php artisan queue:restart
```

### Reverb server

- [ ] Run one persistent Reverb process per replica:

```bash
php artisan reverb:start --host=0.0.0.0 --port=$PORT
```

- [ ] Configure platform health checks against the Reverb service's supported HTTP health endpoint or TCP listener.
- [ ] Allow long-lived connections and WebSocket upgrades at the proxy.
- [ ] Set connection, message-size, ping, and rate limits appropriate for expected traffic.
- [ ] Enable Reverb horizontal scaling when running multiple Reverb replicas:

```env
REVERB_SCALING_ENABLED=true
```

- [ ] Verify Redis connectivity before increasing Reverb replicas.
- [ ] Use graceful shutdown/draining so existing WebSocket clients can reconnect during deploys.

## 6. Reverse proxy and networking

- [ ] Proxy `wss://reverb.example.com/app/{REVERB_APP_KEY}` to the Reverb service.
- [ ] Preserve the `Upgrade` and `Connection: Upgrade` headers.
- [ ] Set a WebSocket read timeout suitable for long-lived connections.
- [ ] Proxy `/api/v1/broadcasting/auth` to the Laravel API over HTTPS.
- [ ] Confirm the API and Reverb hostnames use valid certificates.
- [ ] Confirm CORS and origin policy match the deployed micro-client and admin web URLs.
- [ ] Do not expose Redis or PostgreSQL publicly.

Example Nginx WebSocket location:

```nginx
location /app/ {
    proxy_pass http://bekie_reverb;
    proxy_http_version 1.1;
    proxy_set_header Host $host;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "Upgrade";
    proxy_read_timeout 3600s;
}
```

## 7. Client contract verification

For every client, verify:

- [ ] Login returns a valid bearer token.
- [ ] The client sends `Authorization: Bearer <token>` to `/api/v1/broadcasting/auth`.
- [ ] The client subscribes to `private-user.{userId}`.
- [ ] The client handles the `realtime.notification` broadcast event.
- [ ] The client stores the notification `id` and ignores duplicate IDs.
- [ ] The client can recover missed events with:

```http
GET /api/v1/notifications
Authorization: Bearer <token>
```

- [ ] The client can mark notifications read with `/api/v1/notifications/{notification}/read`.
- [ ] The admin client verifies admin-only notifications separately from customer notifications.
- [ ] Mobile clients reconnect with exponential backoff and refresh expired bearer tokens.

Expected notification payload:

```json
{
  "id": "notification-uuid",
  "type": "order_tracking",
  "category": "order_tracking",
  "title": "Order Updated",
  "message": "Order ABC123 is now shipped.",
  "data": {
    "order_id": 123,
    "order_number": "ABC123",
    "status": "shipped"
  },
  "created_at": "2026-09-19T12:00:00Z"
}
```

## 8. Pre-production tests

- [ ] `vendor/bin/pint --dirty --format agent`
- [ ] Run the focused notification tests:

```bash
php artisan test --compact tests/Feature/RealtimeNotificationTest.php
```

- [ ] Test admin login and token issuance.
- [ ] Test client login and token issuance.
- [ ] Test private channel authorization for the owning user.
- [ ] Test private channel rejection for another user.
- [ ] Test admin channel rejection for a customer.
- [ ] Update an order from `processing` to `shipped`; verify one customer notification.
- [ ] Update payment status; verify customer and active-admin notifications.
- [ ] Publish a targeted announcement; verify excluded users receive nothing.
- [ ] Kill and restart a worker; verify queued notifications are retried without duplicate records.
- [ ] Disconnect and reconnect a WebSocket client; verify history API recovery.
- [ ] Test duplicate payment callbacks; verify no duplicate notification IDs.
- [ ] Test a failed queue job and confirm it appears in `failed_jobs`.
- [ ] Test Reverb through the public WSS hostname, not only localhost.

## 9. Production smoke test

After deployment:

- [ ] `GET https://api.example.com/api/health` returns HTTP 200.
- [ ] Admin login succeeds and returns `access_token`.
- [ ] Client login succeeds and returns `access_token`.
- [ ] `POST https://api.example.com/api/v1/broadcasting/auth` succeeds for an authorized private channel.
- [ ] A browser client establishes a WSS connection.
- [ ] A customer order status update appears in the customer client.
- [ ] A payment update appears for the customer and authorized admins.
- [ ] A published announcement appears only for its target audience.
- [ ] `php artisan queue:failed` is empty or contains only reviewed failures.
- [ ] Reverb logs show successful connections and no authentication/origin errors.
- [ ] Redis memory, connection count, queue depth, and worker restarts are monitored.

## 10. Rollback and incident response

- [ ] Keep the previous application image available.
- [ ] If the API deploy fails, restore the previous image and keep the database migration compatibility in mind.
- [ ] If Reverb fails, keep API polling/history available; clients must continue using `/api/v1/notifications`.
- [ ] If the queue backs up, inspect Redis connectivity and worker logs before retrying all jobs.
- [ ] If duplicate notifications appear, inspect deterministic notification IDs and listener uniqueness locks.
- [ ] If authorization fails, verify bearer token scope, `/api/v1/broadcasting/auth`, channel names, and route middleware.
- [ ] Rotate `REVERB_APP_SECRET`, `JWT_SECRET`, or `APP_KEY` only with a coordinated client/token invalidation plan.

## 11. Required operational documentation updates

- [ ] Update `DEPLOYMENT.md` to remove the statement that there is no worker or broadcast service.
- [ ] Add the worker and Reverb services to the deployment platform configuration.
- [ ] Document the public Reverb hostname and WebSocket path for frontend/mobile teams.
- [ ] Document who owns Redis, queue, Reverb, and failed-job monitoring.
- [ ] Store this checklist with the release runbook and require sign-off before production rollout.
