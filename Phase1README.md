# WhatsApp API Platform — Phase 1 Setup

## What's in Phase 1

- Laravel 13 + Vue 3 + Inertia.js project skeleton
- Full multi-tenant DB schema (clients, users, instances, credits, tokens, queue)
- 3-role auth: Super Admin / Master Admin (Client) / End User
- WhatsApp instance CRUD — every instance gets a unique `instance_token`
- Credit wallet system with full ledger (atomic DB transactions)
- API token management (custom `api_tokens` table, SHA-256 hashed)
- `X-Instance-Token` routing header pattern in all API routes
- Baileys Node.js microservice bootstrap + health-check
- All three dashboard shells (Super Admin / Client / User) — fully navigable
- Instances page + API tokens page fully wired with real data

---

## Prerequisites

| Tool        | Version   |
|-------------|-----------|
| PHP         | 8.3+      |
| Composer    | 2.x       |
| Node.js     | 20 LTS    |
| npm         | 10+       |

---

## Laravel Setup

```bash
# 1. Clone / extract the project
cd /var/www/waplatform

# 2. Install PHP dependencies
composer install

# 3. Copy and configure environment
cp .env.example .env
php artisan key:generate

# 4. Edit .env — set PUSHER credentials and BAILEYS_INTERNAL_SECRET
nano .env

# 5. Create SQLite database
touch database/database.sqlite

# 6. Run all migrations in order
php artisan migrate

# 7. Seed demo data (super admin + demo client + test user)
php artisan db:seed

# 8. Install frontend dependencies
npm install

# 9. Build assets (or run dev server)
npm run dev        # development
npm run build      # production
```

---

## Baileys Service Setup

```bash
cd /var/www/waplatform-baileys

# 1. Install dependencies
npm install

# 2. Configure environment
cp .env.example .env
nano .env   # set INTERNAL_SECRET to match BAILEYS_INTERNAL_SECRET in Laravel

# 3. Start the service
npm start            # foreground
npm run pm2:start    # production (requires pm2: npm install -g pm2)
```

---

## Queue Worker

```bash
# Development (foreground)
php artisan queue:work database --tries=3

# Production (via Supervisor)
# See supervisor.conf — copy to /etc/supervisor/conf.d/waplatform.conf
# then: supervisorctl reread && supervisorctl update && supervisorctl start all
```

---

## Verify Phase 1 is Working

### 1. Login test
```
URL: http://localhost:8000/login

Super Admin  : superadmin@waplatform.com  / SuperAdmin@123
Master Admin : admin@democlient.com        / ClientAdmin@123
End User     : user@democlient.com         / User@123
```

### 2. Baileys health check
```bash
curl http://localhost:8000/api/baileys-health \
  -H "Authorization: Bearer <your_api_token>"
# Expected: {"success":true,"data":{"online":true,"sessions":0,...}}
```

### 3. Create an API token (via dashboard or API)
```bash
curl -X POST http://localhost:8000/api/tokens \
  -H "Authorization: Bearer <existing_token>" \
  -H "Content-Type: application/json" \
  -d '{"name":"Test Token"}'
```

### 4. Create a WhatsApp instance
```bash
curl -X POST http://localhost:8000/api/instances \
  -H "Authorization: Bearer <user_token>" \
  -H "Content-Type: application/json" \
  -d '{"name":"Sales WA","credits":2}'

# Response includes instance_token — use this as X-Instance-Token in all messaging calls
```

### 5. Tenant isolation check
```bash
# Log in as user@democlient.com, get a token, try to access another client's instance
# Should return 403 Forbidden
```

---

## Phase 1 API Reference

| Method | Endpoint                | Auth            | Description                          |
|--------|-------------------------|-----------------|--------------------------------------|
| POST   | /login                  | None            | Web login (form)                     |
| POST   | /logout                 | Session         | Destroy session                      |
| GET    | /api/me                 | Bearer token    | Current user profile                 |
| GET    | /api/tokens             | Bearer token    | List API tokens                      |
| POST   | /api/tokens             | Bearer token    | Create API token → returns plain once|
| DELETE | /api/tokens/{id}        | Bearer token    | Revoke token                         |
| GET    | /api/instances          | Bearer token    | List your instances                  |
| POST   | /api/instances          | Bearer token    | Create instance → returns `instance_token` |
| GET    | /api/instances/{id}     | Bearer token    | Get single instance detail           |
| PATCH  | /api/instances/{id}     | Bearer token    | Update name/webhook/add credits      |
| DELETE | /api/instances/{id}     | Bearer token    | Delete (returns unused credits)      |
| GET    | /api/baileys-health     | Bearer token    | Baileys service health               |

---

## Instance Token Routing (Key Concept)

Every WhatsApp instance has a unique `instance_token`. Use it in the `X-Instance-Token` header:

```bash
# Sending a message (Phase 3 endpoint — shown for reference)
curl -X POST http://localhost:8000/api/send/text \
  -H "Authorization: Bearer wap_<your_user_token>" \
  -H "X-Instance-Token: <instance_token>" \
  -H "Content-Type: application/json" \
  -d '{"to":"919876543210","message":"Hello from the platform!"}'
```

- `Authorization: Bearer` → identifies **who** is making the call (User)
- `X-Instance-Token` → identifies **which WhatsApp number** to send from

Master Admins use the **same pattern** with their own `instance_token` for client-owned instances.

---

## Directory Structure

```
laravel/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Admin/          # Super Admin controllers
│   │   │   ├── Api/            # REST API controllers
│   │   │   ├── Auth/           # Login/logout
│   │   │   ├── Client/         # Client Admin controllers
│   │   │   └── DashboardController.php
│   │   └── Middleware/
│   ├── Models/                 # User, Client, WhatsappInstance, ApiToken, ...
│   ├── Services/
│   │   ├── Baileys/BaileysClient.php
│   │   ├── Credit/CreditService.php
│   │   ├── Instance/InstanceService.php
│   │   └── Token/TokenService.php
│   └── Providers/AppServiceProvider.php
├── database/
│   ├── migrations/             # 6 migration files (ordered)
│   └── seeders/DatabaseSeeder.php
├── resources/js/
│   ├── Components/
│   │   ├── Instance/           # InstanceCard, CreateInstanceModal
│   │   ├── Layout/AppLayout.vue
│   │   └── UI/                 # StatCard, FlashMessage
│   └── Pages/
│       ├── Admin/              # Super Admin pages
│       ├── Auth/Login.vue
│       ├── Client/             # Master Admin pages
│       ├── Shared/PlaceholderPage.vue
│       └── User/               # End User pages
└── routes/
    ├── api.php
    └── web.php

baileys-service/
├── src/index.js                # Express server + session stubs
└── package.json
```

---

## What's NOT in Phase 1 (Coming in Phase 2+)

| Feature                        | Phase |
|--------------------------------|-------|
| QR code scan / WA login        | 2     |
| Send/receive messages          | 3     |
| Webhooks & delivery ACKs       | 3     |
| Campaigns & contacts           | 4     |
| Reports & analytics            | 4     |
| Payment gateway / billing      | 5     |
| Credit expiry cron jobs        | 5     |

---

## Change Passwords Before Production

```bash
# In .env, set APP_DEBUG=false, APP_ENV=production
# Remove demo credentials section from Login.vue
# Change all seeded passwords via dashboard
```