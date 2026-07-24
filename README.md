# Seat Reservation API

![PHP](https://img.shields.io/badge/PHP-8.4+-777BB4?style=flat&logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?style=flat&logo=laravel&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8-4479A1?style=flat&logo=mysql&logoColor=white)
![Pest](https://img.shields.io/badge/Pest-4-FFDD57?style=flat&logo=php&logoColor=black)

Microservice for event seats and orders. It does **not** handle user login - that's the API Gateway's job. Traffic here is internal only, authenticated with `X-Internal-Api-Key`.

We also don't own the cart. Frontend / Gateway collects selected seats, then hits us with `POST orders`, and only then do we lock seats in the database.

---

## What this service does

- Events, sectors, seats (CRUD)
- Bulk seat generation (standing / seated grid / mixed)
- Orders: reserve -> pay -> tickets, or expire / cancel
- Limit of 6 tickets per user per event
- CRON that expires `pending` orders after `valid_until` (15 minutes by default)

### Sector types

| Type | How you order |
|------|----------------|
| `seated` | specific `seat_ids` |
| `standing` | `quantity` - we pick free seats with no row/number |
| `mixed` | in one sector: either `seat_ids` or `quantity` (you can use both as separate items) |

### Order lifecycle

```
pending  ->  paid       (payment webhook)
pending  ->  expired    (CRON after valid_until)
pending  ->  cancelled  (cart / checkout cancelled)
paid     ->  cancelled  (refund)
```

On reserve, seats go `free -> locked`.  
On payment: `locked -> sold` + a `ticket_number` (UUID) per reservation.  
On expire / cancel: seats go back to `free`. Reservations stay in the DB as history.

---

## Local setup

1. Dependencies:

```bash
composer install
cp .env.example .env
php artisan key:generate
```

2. In `.env`, set the database and internal key:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=seat_reservation_api
DB_USERNAME=root
DB_PASSWORD=

INTERNAL_API_KEY=generate-a-long-secret
ALLOWED_IPS=
```

Leave `ALLOWED_IPS` empty locally (disabled). In production you can put the Gateway IPs, comma-separated.

Generate a secret like this:

```bash
php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

3. Migrate and run:

```bash
php artisan migrate
php artisan serve
```

API base: `http://127.0.0.1:8000/api/v1/...`

### Scheduler (order expiry)

In a second terminal:

```bash
php artisan schedule:work
```

Or manually:

```bash
php artisan orders:expire
```

Without this, `pending` orders won't release seats on their own after 15 minutes.

---

## Auth (internal)

Every `/api/...` request needs:

```http
X-Internal-Api-Key: <INTERNAL_API_KEY from .env>
Accept: application/json
```

Missing / wrong key -> `401`.

---

## Order flow (happy path)

1. Gateway calls `POST /api/v1/events/{event}/orders` -> `pending`, seats `locked`
2. Gateway starts payment with the provider and attaches the ID:
   `PATCH /api/v1/events/{event}/orders/{order}/payment-provider`
3. After successful payment, webhook:
   `POST /api/v1/orders/{paymentProviderId}/confirm-payment`  
   -> `paid`, seats `sold`, ticket UUIDs

Cancel (pending, or paid refund):

```http
POST /api/v1/events/{event}/orders/{order}/cancel
```

---

## Endpoints (`/api/v1`)

### Events / sectors / seats

| Method | Path |
|--------|------|
| CRUD | `/events` |
| CRUD | `/events/{event}/sectors` |
| CRUD | `/events/{event}/sectors/{sector}/seats` |
| POST | `/events/{event}/sectors/{sector}/seats/generate` |

Seat generation is a separate endpoint; manual seat CRUD stays as-is.

Standing / mixed (pool with no row):

```json
{ "capacity": 100, "base_price": 50 }
```

Seated / mixed (grid):

```json
{
  "base_price": 100,
  "row": { "prefix": "ALPHABET", "count": 10 },
  "number": { "suffix": "NUMBER", "count": 20 }
}
```

`prefix` / `suffix`: `ALPHABET` | `NUMBER` | `ROMAN` (or `null`).  
Label = prefix + optional `name` + suffix.

### Orders

| Method | Path | What it does |
|--------|------|----------------|
| GET | `/events/{event}/orders?user_id=` | list orders for a user (`user_id` required) |
| POST | `/events/{event}/orders` | create reservation |
| GET | `/events/{event}/orders/{order}` | details + reservations |
| PATCH | `/events/{event}/orders/{order}/payment-provider` | attach payment provider ID |
| POST | `/events/{event}/orders/{order}/cancel` | cancel / refund |
| POST | `/orders/{paymentProviderId}/confirm-payment` | confirm payment |
| GET | `/events/{event}/users/{userId}/limit` | how many seats the user can still take |

Example order (seated + standing in one request):

```json
{
  "user_id": "user-from-gateway",
  "items": [
    { "sector_id": 1, "seat_ids": [10, 11] },
    { "sector_id": 2, "quantity": 2 }
  ]
}
```

For mixed: either `seat_ids` or `quantity` in a single item - not both. Two items on the same mixed sector (a seat + standing) is fine.

---

## Tests

Create the test DB first (default name/port come from `phpunit.xml`):

```bash
php scripts/create-test-database.php
```

Then:

```bash
php artisan test
```

Concurrency tests for `SKIP LOCKED` (needs a second MySQL connection):

```bash
php artisan test --testsuite=Concurrency
```

Formatting:

```bash
./vendor/bin/pint
```

---

## Docker

No `Dockerfile` / `docker-compose` yet - run PHP + MySQL locally as above. When compose lands, it'll be documented here.

---

## Useful files

| File | Why |
|------|-----|
| `examples/api-gateway/SeatReservationClient.php` | sketch of a Gateway client |
| `routes/api.php` | all endpoints |
| `routes/console.php` | scheduler (`orders:expire` every minute) |
