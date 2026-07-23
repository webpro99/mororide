# MoroRide — Testing Workflow

Two layers of testing: **automated** (backend) and **manual end-to-end** (admin console, rider app, driver app, realtime).

---

## 0. Start the stack

From `mororide/`:

```bash
docker compose up -d postgres redis
docker compose run --rm -e COMPOSER_PROCESS_TIMEOUT=0 app composer install   # first time only
docker compose run --rm app php artisan migrate --seed                       # first time only
docker compose up -d app reverb
```

- API + web:        http://127.0.0.1:8000
- Admin console:    http://127.0.0.1:8000/admin/login
- Reverb (realtime WS): ws://127.0.0.1:8080

Demo accounts (password `password` for all):
`admin@mororide.test` · `rider@mororide.test` · `driver@mororide.test` · `concierge@mororide.test`
Plus a **pending** driver for verification: `pending.driver@mororide.test`.

---

## 1. Automated backend tests (fastest confidence)

```bash
docker compose run --rm app php artisan test
```

- Uses an **isolated in-memory sqlite** DB — it never touches the dev Postgres data.
- Expect: **71 passed**. Run a single suite while iterating:

```bash
docker compose run --rm app php artisan test --filter=MarketplaceFlowTest
docker compose run --rm app php artisan test --filter=StripePaymentFlowTest
docker compose run --rm app php artisan test --filter=RealtimeBroadcastingTest
docker compose run --rm app php artisan test --filter=AdminBackendTest
```

---

## 2. Admin console (web)

1. Open http://127.0.0.1:8000/admin/login → **Sign in** (creds prefilled).
2. Dashboard → check user/driver/order/revenue tiles.
3. **Drivers & Verification** → filter `pending` → open `pending.driver@mororide.test` documents → **Approve** / **Reject** / **Request document**.
4. **Orders** → cancel/flag an order.
5. **Wallets** → adjust points/balance (writes a ledger entry).
6. **Payouts** → create from a driver balance → confirm.
7. **Audit logs** → every action above appears here.

---

## 3. Run the mobile app (Expo, web mode)

From `mobile/`:

```bash
npx expo start --web        # serves on http://localhost:8081 (or 8082 if taken)
```

On the landing **role gate**, pick **Rider** or **Driver**. Switch anytime via the side menu → *Switch app*.

> **Important for cross-role testing:** the rider and driver apps share one browser token
> (localStorage). To run **both at once** (rider creates, driver accepts), open them in
> **two separate browsers** (or one normal + one Incognito window) so each has its own session.

---

## 3b. Run on a real Android phone (Expo Go)

No APK needed — the app runs inside **Expo Go**.

1. On the phone: install **Expo Go** from the Play Store. Put the phone on the **same Wi‑Fi** as this PC.
2. Make sure the backend is up: `docker compose up -d postgres redis app reverb`.
3. `mobile/.env.local` already points the app at this PC's LAN IP:
   ```
   EXPO_PUBLIC_API_BASE_URL=http://192.168.2.75:8000/api
   EXPO_PUBLIC_REVERB_HOST=192.168.2.75
   ```
   (If your LAN IP changes, update it: `ipconfig` → IPv4 Address.)
4. **Open the firewall** so the phone can reach the ports (run PowerShell **as Administrator** once):
   ```powershell
   New-NetFirewallRule -DisplayName "MoroRide Dev 8000" -Direction Inbound -Action Allow -Protocol TCP -LocalPort 8000 -Profile Private
   New-NetFirewallRule -DisplayName "MoroRide Dev 8080" -Direction Inbound -Action Allow -Protocol TCP -LocalPort 8080 -Profile Private
   New-NetFirewallRule -DisplayName "MoroRide Dev 8081" -Direction Inbound -Action Allow -Protocol TCP -LocalPort 8081 -Profile Private
   ```
   (Docker usually opens 8000/8080 already; when Expo starts, also click **Allow access** on any Windows Firewall popup for Node.js.)
5. Start Metro: `cd mobile && npx expo start`
6. In **Expo Go**, scan the QR shown in the terminal — or tap *Enter URL manually* and type `exp://192.168.2.75:8081` (use the port Expo prints).
7. On the **role gate**, tap **Driver** → try the tabs: **Drive** (go online → accept a request), **Verify** (upload documents), **Wallet**, **Profile**.

> If the phone can't reach the server: confirm same Wi‑Fi, the firewall rules above, and that `http://192.168.2.75:8000/` opens in the phone's browser. If Wi‑Fi has client isolation, use `npx expo start --tunnel` (and expose the backend with a tunnel too).

---

## 4. End-to-end ride (the core flow)

Use **two browser windows** pointed at the Expo web app.

**Window A — Rider:**
1. Role gate → **Rider**.
2. Booking → pick city **Marrakech**, set price → **Find a driver** (creates the order).

**Window B — Driver** (separate browser / incognito):
3. Role gate → **Driver**.
4. Choose city **Marrakech** → **Go online**.
5. The rider's request appears in **Incoming requests** → **Accept** (or **Counter** / **Decline**).
6. Ride panel → **I've arrived → Start ride → Complete ride**.
7. On complete: earnings shown, wallet points/balance update.

**Back in Window A — Rider:** the order moves to tracking → completed; leave a **rating**.

**Admin console:** the order shows as completed, a **transaction** appears in Transactions/Revenue.

✅ This exercises: order creation → dispatch → accept → assignment → ride lifecycle → transaction → wallet ledger → rating.

---

## 5. Chat

- During an active ride, tap **💬 Chat** (driver) and the chat screen (rider). Messages persist by order and appear on both sides (poll/realtime). Admin can read the archive under **Chat archive**.

---

## 6. Realtime (Reverb)

- With `docker compose up -d reverb` running, the **rider app** receives live updates (driver assignment, location, chat) over `ws://127.0.0.1:8080`.
- The **driver queue** refreshes every ~4s by polling, so it works even without Reverb.
- Check the WS server is alive: `docker compose logs -f reverb` (look for `Starting server on 0.0.0.0:8080`).
- Mobile realtime uses app key `mororide-local-key` (matches the docker-compose `reverb` service). Override per env with `EXPO_PUBLIC_REVERB_APP_KEY` / `EXPO_PUBLIC_REVERB_HOST` / `EXPO_PUBLIC_REVERB_PORT` if needed.

---

## 7. Payments (Stripe — test mode)

Payments are **gated by `STRIPE_ENABLED`** (default `false` → endpoints return *service unavailable*, verified by tests).

To exercise them:
1. In `backend/.env` set `STRIPE_ENABLED=true` and add Stripe **test** keys (`sk_test_…`, `pk_test_…`, webhook secret).
2. `docker compose restart app`.
3. Card ride: rider selects **card** → app calls `POST /api/payments/orders/{order}/intent` → confirm with the returned `client_secret`.
4. Driver points top-up: `POST /api/driver/payments/points/intent`.
5. Webhooks: point Stripe CLI to `POST http://127.0.0.1:8000/api/webhooks/stripe`.
   The webhook handler is **idempotent** (see `StripePaymentFlowTest`).

> Never expose `STRIPE_SECRET_KEY` to the mobile/web client — only the publishable key is returned to clients.

---

## Troubleshooting

| Symptom | Fix |
|---|---|
| Slow API (seconds) | Ensure `vendor/` is on the named volume and OPcache is on — see README "Performance". |
| `php artisan test` wiped dev data | Should not happen: tests force sqlite. Re-seed: `docker compose run --rm app php artisan migrate:fresh --seed`. |
| Driver "must be approved and online" | Use `driver@mororide.test` (approved) and **Go online with a city** first. |
| Driver sees no requests | Rider order must be in the **same city** (default Marrakech / `city_id 2`). |
| Mobile can't reach API | Android emulator uses `10.0.2.2`; set `EXPO_PUBLIC_API_BASE_URL` for a device. |
| Realtime silent | Confirm `docker compose up -d reverb` and matching `REVERB_APP_KEY`. |
