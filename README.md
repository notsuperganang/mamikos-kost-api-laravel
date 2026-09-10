# Mamikos Kost API — Laravel

REST API for a kost (boarding house) search platform: owners list their kosts, users search them and spend credits to ask about room availability. This is the **Laravel** implementation of the Mamikos backend technical test; the Spring Boot twin lives at [mamikos-kost-api-spring](https://github.com/notsuperganang/mamikos-kost-api-spring) and exposes the exact same HTTP contract. A side-by-side comparison of both is in [COMPARISON.md](COMPARISON.md).

[![CI](https://github.com/notsuperganang/mamikos-kost-api-laravel/actions/workflows/ci.yml/badge.svg)](https://github.com/notsuperganang/mamikos-kost-api-laravel/actions/workflows/ci.yml)

## Stack

| Concern | Choice |
|---|---|
| Runtime | PHP 8.3+, Laravel 13 |
| Database | PostgreSQL 18, schema managed by Laravel migrations |
| Persistence | Eloquent, query scope for search |
| Auth | Laravel Sanctum personal access tokens (opaque bearer tokens, revocable, 7-day expiry) |
| Scheduling | Laravel scheduler (`routes/console.php`) + `credits:recharge` Artisan command |
| Tests | Pest 5 against a real PostgreSQL test database |
| Quality | Laravel Pint enforced in CI, GitHub Actions |

## Business rules implemented

- Register as **owner**, **regular** user or **premium** user. Regular users start with 20 credits, premium with 40, owners with none.
- Owners create, update, delete and list their own kosts (any number of them).
- Anyone can search kosts by `name`, `location` and price range, sort by price, and view a kost's details.
- Regular and premium users can ask about a kost's room availability; every inquiry costs **5 credits** and is refused when the balance is too low.
- On the **first day of every month** (00:00 in the configured timezone) balances are reset to the tier allowance (20 / 40). Every balance change is written to an append-only ledger, which also makes the recharge idempotent.

## API

Base path: `/api/v1`. Requests and responses are JSON with `snake_case` keys. Authenticated endpoints expect `Authorization: Bearer <token>`.

| Method | Path | Auth | Description |
|---|---|---|---|
| `POST` | `/auth/register` | – | Body `{name, email, password, role}`; `role` ∈ `owner`, `regular`, `premium`. Returns `201` with `{token, token_type, expires_in, user}` |
| `POST` | `/auth/login` | – | Body `{email, password}`. Returns `{token, token_type, expires_in, user}` |
| `GET` | `/auth/me` | any | Current user including `credit` |
| `POST` | `/auth/logout` | any | Revokes the token used for the request → `204` |
| `GET` | `/owner/kosts` | owner | Paginated list of the caller's kosts (`page`, `per_page` ≤ 50) |
| `POST` | `/owner/kosts` | owner | Body `{name, location, price, available_rooms, description?}` → `201` |
| `PUT` | `/owner/kosts/{id}` | owner | Full update; `403` if the kost belongs to someone else |
| `DELETE` | `/owner/kosts/{id}` | owner | `204` on success |
| `GET` | `/kosts` | – | Search: `name`, `location` (case-insensitive contains), `min_price`, `max_price`, `sort` (`price`, `created_at`), `order` (`asc`, `desc`), `page`, `per_page` |
| `GET` | `/kosts/{id}` | – | Kost detail |
| `POST` | `/kosts/{id}/availability-inquiries` | regular, premium | Charges 5 credits and returns `{kost_id, available_rooms, is_available, credits_charged, remaining_credit}` → `201` |

List endpoints return `{"data": [...], "meta": {"current_page", "per_page", "total", "last_page"}}`.

Errors follow [RFC 9457 Problem Details](https://www.rfc-editor.org/rfc/rfc9457) (`application/problem+json`):

```json
{
  "type": "/problems/validation",
  "title": "Validation Failed",
  "status": 422,
  "detail": "The given data was invalid.",
  "instance": "/api/v1/owner/kosts",
  "errors": { "price": ["The price field must be at least 0."] }
}
```

| Status | When |
|---|---|
| `401` | Missing, expired or invalid token; wrong login credentials |
| `403` | Wrong role for the endpoint, or editing someone else's kost |
| `404` | Unknown kost |
| `422` | Validation failure (`/problems/validation`) or insufficient credit (`/problems/insufficient-credit`) |
| `429` | Too many login / register attempts (10 per minute per IP) |

Health check: `GET /up`.

## Getting started

### Prerequisites

- PHP 8.3 or newer with the `pdo_pgsql`, `pgsql`, `mbstring`, `intl` extensions
- Composer 2
- PostgreSQL 18, either local or via Docker (`docker compose` is the easiest path)

### 1. Clone and install

```bash
git clone https://github.com/notsuperganang/mamikos-kost-api-laravel.git
cd mamikos-kost-api-laravel
composer install
cp .env.example .env
php artisan key:generate
```

### 2. Start PostgreSQL

```bash
docker compose up -d postgres
```

This starts `postgres:18-alpine` on port 5432 with database `mamikos_laravel` (plus `mamikos_laravel_test` for the test suite), user `mamikos`, password `mamikos`, matching `.env.example`. Already have PostgreSQL? Create the two databases and adjust the `DB_*` values in `.env`.

### 3. Configure (optional)

| Variable | Default | Purpose |
|---|---|---|
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | `127.0.0.1`, `5432`, `mamikos_laravel`, `mamikos`, `mamikos` | Database connection |
| `SANCTUM_TOKEN_EXPIRATION` | `10080` (7 days, minutes) | Token lifetime |
| `CREDITS_INQUIRY_COST` | `5` | Credits charged per availability inquiry |
| `CREDITS_TIMEZONE` | `Asia/Jakarta` | Zone in which the schedule and "start of month" are evaluated |
| `CACHE_STORE` | `database` | Must be a shared store (`database`, `redis`) for `onOneServer()` on multi-server setups |

### 4. Migrate, seed and run

```bash
php artisan migrate
php artisan db:seed          # optional demo data: owner@example.com, regular@example.com, premium@example.com / "password"
php artisan serve            # http://localhost:8000
```

Verify with `curl http://localhost:8000/up`.

### 5. Try it

A scripted walkthrough covering registration, kost management, search and an availability inquiry (needs `curl` and `jq`):

```bash
./scripts/smoke.sh                      # against http://localhost:8000
BASE_URL=http://localhost:8080 ./scripts/smoke.sh
```

Or by hand:

```bash
# register an owner
curl -s -X POST localhost:8000/api/v1/auth/register -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d '{"name":"Owner","email":"owner@example.com","password":"secret-123","role":"owner"}'

# add a kost (paste the token from the previous response)
curl -s -X POST localhost:8000/api/v1/owner/kosts -H "Authorization: Bearer $TOKEN" \
  -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d '{"name":"Kost Melati","location":"Yogyakarta","price":1500000,"available_rooms":2}'

# search
curl -s 'localhost:8000/api/v1/kosts?location=yogya&max_price=2000000&sort=price&order=asc'

# ask about availability as a regular user (costs 5 credits)
curl -s -X POST localhost:8000/api/v1/kosts/1/availability-inquiries -H "Authorization: Bearer $USER_TOKEN" -H 'Accept: application/json'
```

### Run everything in Docker

```bash
export APP_KEY=$(php artisan key:generate --show)   # or any base64:... 32-byte key
docker compose --profile full up --build
```

Builds the image, runs migrations and serves the API on port 8000 next to PostgreSQL.

## Monthly credit recharge

`routes/console.php` schedules the `credits:recharge` command with `monthlyOn(1, '00:00')` in `CREDITS_TIMEZONE`, guarded by `onOneServer()` and `withoutOverlapping()`. The command:

1. selects regular / premium users who have **not** yet received a `monthly_recharge` ledger row this month,
2. writes one ledger row per user (`amount = allowance − current balance`),
3. resets their balance to 20 / 40,

all in a single SQL statement per role, inside one transaction. Running it twice in the same month is a no-op.

Run it by hand, or preview it:

```bash
php artisan credits:recharge
php artisan credits:recharge --dry-run
php artisan schedule:list          # shows "0 0 1 * *  php artisan credits:recharge"
```

For the schedule to fire in production, add the single standard Laravel cron entry on the server:

```
* * * * * cd /path/to/mamikos-kost-api-laravel && php artisan schedule:run >> /dev/null 2>&1
```

Locally, `php artisan schedule:work` runs the scheduler in the foreground.

## Tests

```bash
php artisan test            # Pest, against the mamikos_laravel_test database
vendor/bin/pint --test      # code style (use `vendor/bin/pint` to fix)
```

Feature tests hit every endpoint over HTTP with a real PostgreSQL database (`RefreshDatabase`), including the 401/403 matrix, credit deduction, insufficient-credit handling, the recharge command's idempotency and the registered schedule. Unit tests cover the domain rules in isolation.

## Project layout

```
app
├── Console/Commands/RechargeCreditsCommand.php   credits:recharge
├── Data/                 KostSearch, InquiryResult value objects
├── Enums/                UserRole (allowances), CreditTransactionType
├── Exceptions/           ProblemDetail renderer, InsufficientCreditException
├── Http/Controllers/Api/V1/   Auth, Kost (public), Owner\Kost, AvailabilityInquiry
├── Http/Requests/        FormRequest validation per endpoint
├── Http/Resources/       JSON shapes shared with the Spring implementation
├── Http/Middleware/EnsureUserHasRole.php
├── Models/               User, Kost (search scope), CreditTransaction, RoomAvailabilityInquiry
├── Policies/KostPolicy.php
└── Services/             AuthService, CreditService, AvailabilityInquiryService
database/migrations, factories, seeders
routes/api.php            versioned API routes
routes/console.php        schedule definition
tests/Feature, tests/Unit
```

## Design notes

- **Framework-idiomatic layering.** FormRequests validate, controllers stay thin, Eloquent handles CRUD directly, and services exist only where there is real logic (credits, inquiries, token issuing). No repository layer over Eloquent.
- **Roles as an enum, not tables.** The three account types are fixed by the requirements, so `UserRole` carries the allowance and the "can inquire" rule. Route middleware enforces the role, `KostPolicy` enforces ownership of the individual kost.
- **Credits are safe under concurrency.** Deduction is a single conditional `UPDATE … WHERE credit >= ?`; zero affected rows means "insufficient credit". A `CHECK (credit >= 0)` constraint is the last line of defence.
- **Sanctum tokens** are hashed in the database, expire after 7 days and can be revoked instantly, which is the right trade-off for a first-party API. The Spring twin uses stateless JWTs; the comparison report discusses the difference.
- **Consistent errors.** All API failures are rendered as problem-detail documents in `bootstrap/app.php`, so clients get the same shape from both implementations.
- **Deliberately left out** for this scope: refresh tokens, soft deletes, caching, queues, and permission tables. See `COMPARISON.md` for the reasoning.
