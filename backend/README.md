# MoroRide Backend

Laravel API backend for the MoroRide marketplace MVP.

## Stack

- Laravel 12 on PHP 8.2+ (the Docker app image uses PHP 8.3)
- Laravel Sanctum token auth
- PostgreSQL for local/runtime data
- Redis prepared for queues/cache
- SQLite in-memory for automated tests

The Docker environment is the recommended runtime because it matches the PHP 8.3 production target. The current Windows host CLI may be older and should not be used to run this Laravel checkout.

## Local Setup

From `mororide/`:

```bash
docker compose up -d postgres redis
```

From `mororide/backend/`:

```bash
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Demo credentials seeded with password `password`:

- `admin@mororide.test`
- `rider@mororide.test`
- `driver@mororide.test`
- `concierge@mororide.test`

## First Marketplace Flow

Implemented flow:

1. Admin can login.
2. Rider can create an order.
3. Approved driver can go online.
4. Driver can see open orders.
5. Driver can accept an order.
6. Order becomes assigned.
7. Driver marks arrived.
8. Driver starts the ride.
9. Driver completes the ride.
10. System creates a transaction.
11. Cash ride deducts the platform fee from driver points and writes a wallet ledger entry.
12. Admin can see dashboard/order/transaction data from the database.

## Catalog And Notifications

The backend now exposes database-backed app support data:

- `GET /api/catalog` returns active cities, vehicle types/fare multipliers, and approved driver profiles.
- `GET /api/notifications` returns visible global, role-targeted, and direct notifications.
- `POST /api/notifications/{notification}/read` enforces ownership/role visibility. Global and role notifications use `notification_reads` for per-user read state.

Seeded notification examples include driver assignment, driver arrival, payment reminder, safety reminder, driver approval, points balance reminder, and admin ride-completed updates.

## Browser Demo Pages

The first marketplace flow can now be tested from browser pages, not only from API tools:

```txt
http://127.0.0.1:8000/app
http://127.0.0.1:8000/app/rider
http://127.0.0.1:8000/app/concierge
http://127.0.0.1:8000/app/driver
http://127.0.0.1:8000/admin
```

Quick browser test:

1. Open `/app/rider`, click `Quick Login`, then create a ride order.
2. Open `/app/driver`, click `Quick Login`, go online, refresh open orders, accept the order, then mark arrived/start/complete.
3. Open `/admin/login`, sign in, then use the database-backed admin console at `/admin`.

## Security And Marketplace Hardening

- Riders and concierges can only read their own orders.
- Driver queue/show access requires an active, approved, online driver and hides expired, declined, cross-city, or unrelated assigned orders.
- Driver accept/counter/decline actions lock and re-check the order so stale or competing actions cannot assign or revive it.
- `/api/driver/online` can record `city_id` and initial coordinates in `driver_locations` for city-scoped dispatch.
- Rider/Concierge owners can list offers, atomically choose an eligible driver, cancel before the ride starts, and rate a completed ride once.
- Assigned ride participants can persist and page through private chat messages; non-participants receive `404` before validation.
- `/api/driver/location` appends fresh, monotonic GPS history and protects active rides from cross-city updates.
- Sanctum-protected private broadcast channels and post-commit events cover dispatch, offers, assignment, ride status, GPS, chat, and the admin feed.
- Cash completion with insufficient points/free rides rolls back the ride transaction, then persistently blocks and offlines the driver.
- Regression tests include `ResourceAuthorizationTest`, `DriverDispatchTest`, `CashRideCompletionWalletTest`, `ParticipantOrderFlowTest`, `ParticipantChatTest`, `DriverLocationTest`, `RealtimeBroadcastingTest`, and `ParticipantRealtimeIntegrationTest`.

## Homepage

The backend also serves the current marketing homepage at:

```txt
http://127.0.0.1:8000/
```

Implemented homepage work:

- Real Blade/HTML/CSS page in `resources/views/welcome.blade.php`.
- Responsive layout for desktop and mobile.
- Mobile hamburger menu.
- Menu links only point to existing homepage sections:
  - `#how`
  - `#tourists`
  - `#hotels`
  - `#drivers`
  - `#safety`
  - `#features`
  - `#cities`
- Homepage sections:
  - hero
  - how it works
  - tourist, hotel, and driver role cards
  - safety and trust
  - marketplace features
  - cities coverage
  - CTA banner
  - footer
- Font Awesome icons added for social media and app store badges.
- MoroRide assets copied under `public/assets/mororide/`.
- CTA banner uses Moroccan arch side decoration from the provided assets.

## Implemented API

Auth:

- `POST /api/register`
- `POST /api/login`
- `GET /api/me`
- `POST /api/logout`
- `PATCH /api/me/profile`

General:

- `GET /api/catalog`
- `GET /api/notifications`
- `POST /api/notifications/{notification}/read`

Fare:

- `POST /api/fares/estimate`
- `GET /api/fare-config`
- `GET /api/admin/fare-config`
- `POST /api/admin/fare-config`

Rider:

- `GET /api/rider/orders`
- `POST /api/rider/orders`
- `GET /api/rider/orders/{order}`
- `GET /api/rider/orders/{order}/offers`
- `POST /api/rider/orders/{order}/choose-driver`
- `POST /api/rider/orders/{order}/cancel`
- `POST /api/rider/orders/{order}/rating`

Concierge:

- `GET /api/concierge/orders`
- `POST /api/concierge/orders`
- `GET /api/concierge/orders/{order}`
- `GET /api/concierge/orders/{order}/offers`
- `POST /api/concierge/orders/{order}/choose-driver`
- `POST /api/concierge/orders/{order}/cancel`
- `POST /api/concierge/orders/{order}/rating`

Driver:

- `POST /api/driver/online`
- `POST /api/driver/offline`
- `POST /api/driver/location`
- `GET /api/driver/orders`
- `GET /api/driver/orders/{order}`
- `POST /api/driver/orders/{order}/accept`
- `POST /api/driver/orders/{order}/counter`
- `POST /api/driver/orders/{order}/decline`
- `POST /api/driver/orders/{order}/arrived`
- `POST /api/driver/orders/{order}/start`
- `POST /api/driver/orders/{order}/complete`
- `GET /api/driver/wallet`

Participant chat and broadcasting:

- `GET /api/orders/{order}/messages`
- `POST /api/orders/{order}/messages`
- `POST /broadcasting/auth`

Admin:

- `GET /api/admin/dashboard`
- `GET /api/admin/users`
- `GET /api/admin/orders`
- `GET /api/admin/orders/{order}`
- `GET /api/admin/transactions`
- `GET /api/admin/revenue`
- `GET /api/admin/wallets`

## Tests

```bash
docker compose exec -T app php artisan test
```

Current verified result: **65 tests passed with 459 assertions**.

## Known Limitations

- Realtime events/private channels are wired, but the local `log` broadcaster is diagnostic only; Reverb/Soketi/Pusher and client subscriptions are not configured yet.
- Card payments use a fake MVP marker only; no provider/webhooks yet.
- Driver verification upload/review and the admin verification workflow are implemented; mobile verification screens are still pending.
- A starter Expo React Native app exists in `../mobile`.
- The database-backed admin console is implemented; charts, bulk actions, full UI pagination, and live refresh remain optional polish.
- Participant chat and the admin archive are implemented; mobile chat UI, uploads, and delivery/read receipts are still pending.
- Notifications are persisted and readable through the API, but push delivery is still pending.
- Rider/Concierge participant APIs are implemented; the Expo UI is not connected to them yet.
- Continuous GPS history is implemented; native background tracking, live-map client subscriptions, and route polylines are still pending.
- App store and social links on the homepage are visual placeholders until real URLs are provided.
