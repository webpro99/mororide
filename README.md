# MoroRide — Order for Coding Assistant / Developer Agent

> Current implementation status was added at the bottom of this README under **MoroRide - Current Project Status**. It documents what is done, how to run it, and what is still missing.

**Goal:** Read the full project handoff first, understand the existing prototype logic, then build the production version of MoroRide step by step.

**Source document to read first:** `MoroRide-Developer-Handoff.md`

> Important: Do not start coding randomly. The handoff is the source of truth for UX, roles, business logic, flows, and prototype behavior. Your job is to convert the front-end prototype into a real production system with backend, database, realtime, payments, admin dashboard, and cross-platform mobile app.

---

## Latest VPS / Mobile Delivery - July 2026

This repository is currently deployed and tested on the MoroRide VPS:

```txt
Domain: https://mororide.com
Server IP: 187.124.222.163
Backend: /var/www/mororide/backend
Mobile app: /root/mororide/mobile
Latest test APK: https://mororide.com/mororide-voip-test.apk
Latest APK SHA-256: 44a85b7c14a393392d5a43c3f629ffc1e50d6da8ddf84be6d4ce962aaeace654
```

### Production Server Work Completed

- `mororide.com` is pointed at the VPS and served through Nginx with HTTPS/SSL.
- Laravel backend is installed under `/var/www/mororide/backend`.
- Reverb realtime traffic is proxied through Nginx.
- LiveKit is installed as a systemd service for free self-hosted in-ride VoIP:
  - client URL: `wss://mororide.com`
  - Nginx paths: `/rtc` and `/twirp/`
  - service: `livekit.service`
- Metro file watcher limits were increased for Expo development:

```txt
fs.inotify.max_user_watches=524288
fs.inotify.max_user_instances=1024
```

### Mobile App Work Completed

The Expo mobile app has been upgraded and tested on SDK 54.

Major mobile changes now included:

- Unified safe-area layout so app headers and footers do not overlap Android system bars.
- Full redesign pass across rider, driver, concierge, signup, login, offers, driver profile, verification, wallet, chat, ride complete, and tracking screens.
- Hamburger navigation/menu added for role dashboards.
- Logout added across roles.
- Driver verification UX improved; approved drivers no longer keep seeing the stale verification banner.
- Driver document upload and persistence flow remains connected to backend storage/database.
- Driver free rides/points/wallet UI added, including the two free rides shown after approval.
- Rider pickup/drop-off UX improved with clear selection behavior and full reset after ride cancellation.
- Rider offer cards and driver profile cards redesigned for smaller Android screens.
- Notifications panel redesigned so it opens as a full-width mobile sheet instead of a cropped panel.
- Message/chat screen moved away from Android navigation controls.

### Maps

Google native maps were removed from the APK flow because the APK did not have a configured Google Maps API key and Android could crash when opening pickup selection.

The app now uses a free OpenStreetMap/Leaflet map inside `react-native-webview` for:

- pickup selection;
- drop-off selection;
- route preview;
- driver/rider map display fallback.

This avoids Google billing/API-key requirements for the test build and prevents the Android map crash.

### Location

- Rider pickup/drop-off picker asks for foreground location permission.
- The picker defaults to the user's detected location when permission is granted.
- Driver foreground GPS publishing is connected while the driver is online.
- Background GPS is still a production follow-up because it requires a dedicated native/background task setup and device testing.

### Payments / Stripe Test Mode

- Stripe test-mode card payment flow exists through native Stripe PaymentSheet in the APK.
- Driver points top-up is connected to Stripe test PaymentSheet.
- Wallet refresh polling was added after a successful top-up so the balance updates after webhook confirmation.
- Admin payment settings remain the source of truth for Stripe keys and webhook secrets.
- Live Stripe use still depends on a lawful supported-country Stripe entity or a Morocco-compatible provider such as CMI/Payzone.

### Free VoIP Calling

In-ride audio calling is implemented with self-hosted LiveKit, so there is no per-minute Twilio-style cost for the test flow.

Current behavior:

- Rider can call driver during an active ride.
- Driver can call rider during an active ride.
- The caller joins the LiveKit room and notifies the other participant.
- The receiver now gets an incoming-call prompt with Answer and Decline.
- The incoming-call prompt vibrates the phone while the app is foregrounded.
- Push notifications include sound for background/locked-app delivery when Android notification permissions are enabled.
- Answer joins the call without requiring the receiver to press the call icon manually.
- Decline closes the prompt and marks the notification read.

Important caveat:

- Full native background ringing like WhatsApp/Phone requires platform call integrations such as Android ConnectionService / iOS CallKit plus APNs/FCM production credentials. The current implementation provides in-app ringing/prompt plus Expo push notification sound.

### Expo Development

Expo Go is launched in a detached screen session for iterative testing:

```bash
screen -r mororide-expo
```

The current development command is:

```bash
cd /root/mororide/mobile
npx expo start --go --tunnel --clear
```

For JS-only changes, test in Expo first. Build a new APK only when native dependencies/configuration changed or when a final Android test build is needed.

Useful mobile checks:

```bash
cd /root/mororide/mobile
npm run typecheck
```

Build Android release APK locally:

```bash
cd /root/mororide/mobile/android
JAVA_HOME=/usr/lib/jvm/java-21-openjdk-amd64 ANDROID_HOME=/opt/android-sdk ./gradlew assembleRelease
```

Publish the current test APK to the domain:

```bash
install -m 0644 /root/mororide/mobile/android/app/build/outputs/apk/release/app-release.apk /var/www/mororide/backend/public/mororide-voip-test.apk.new
mv /var/www/mororide/backend/public/mororide-voip-test.apk.new /var/www/mororide/backend/public/mororide-voip-test.apk
nginx -s reload
```

---

## 1. Product Understanding

MoroRide is an **InDrive-style ride marketplace for Morocco tourism**.

The platform has **4 roles**:

| Role | Description | Main Goal |
|---|---|---|
| Rider | Tourist/client | Book a ride, name a price, choose driver, track ride, pay, rate |
| Driver | Licensed driver/guide | Go online, receive ride requests, accept/counter, drive, get paid |
| Concierge | Hotel/riad staff | Dispatch rides for guests, choose driver, track guest rides |
| Admin | Platform operator | Manage users, verification, fares, commission, live rides, chat, revenue |

This is **not a simple booking app**. It is a marketplace with live dispatch, wallet/points, driver verification, payments, maps, realtime chat, and admin control.

---

## 2. Read and Extract from the Prototype

Before creating the production code, inspect the prototype file if available, especially the JavaScript arrays and functions.

Extract and document:

- Screens for each role
- User actions
- Business rules
- Data models
- Simulated data arrays
- Function names and their logic
- Current fake/simulated parts
- Parts that must become backend/database/realtime

Prototype arrays/functions to look for:

```txt
DREQUESTS
DRIVERS
CBOOKINGS
USERS
ACTIVE_RIDES
TRANSACTIONS
CHAT_ARCHIVE
fareConfig
platformFeePct
driverPoints
driverWallet
driverFreeRides
conciergePoints

confirmRide
sendOffer
dAccept
dComplete
cDispatch
cChoose
saveFareConfig
renderTransactions
sendChat
addLog
```

---

## 3. Required Architecture

Use this architecture unless there is a strong reason to change it:

| Layer | Technology |
|---|---|
| Backend API | Laravel 12 (brief asked for Laravel 11; upgraded to 12 because every Laravel 11.x release is flagged by security advisories) |
| PHP | PHP 8.3 |
| Database | PostgreSQL |
| Auth | Laravel Sanctum |
| Queue/cache | Redis |
| Realtime | Laravel Echo + Pusher or Soketi |
| Storage | S3-compatible storage |
| Maps | Google Maps SDK, Places, Geocoding, Distance Matrix |
| Payments | Stripe Connect or Morocco-compatible provider such as CMI/PayZone if Stripe MAD is not possible |
| Mobile app | Flutter or React Native |
| Admin dashboard | Laravel Blade/Inertia/Vue/React |
| Deployment | VPS recommended, not FTP-only shared hosting |

---

## 4. Recommended Repository Structure

Create or maintain this structure:

```txt
mororide/
├── backend/
│   ├── app/
│   ├── database/
│   ├── routes/
│   ├── tests/
│   └── README.md
│
├── admin/
│   └── README.md
│
├── mobile/
│   ├── lib/
│   ├── screens/
│   ├── services/
│   ├── store/
│   └── README.md
│
├── docs/
│   ├── architecture.md
│   ├── database.md
│   ├── api.md
│   ├── realtime.md
│   ├── payments.md
│   ├── deployment.md
│   └── changelog.md
│
└── docker-compose.yml
```

If admin is built inside Laravel, document that clearly and keep the routes/controllers separated.

---

## 5. Development Rules

Follow these rules strictly:

1. Do not build mobile screens before backend foundations are ready.
2. Do not trust frontend fare calculation.
3. Fare, commission, wallet, points, and payment logic must be server-side.
4. Every wallet/points movement must create a ledger entry.
5. Every ride completion must create a transaction row.
6. Chat messages must be persisted by order.
7. Admin dashboard must read real database data, not fake arrays.
8. Driver cannot go online unless approved.
9. Realtime events must be scoped by city/order/driver.
10. Do not put secrets in code. Use `.env`.
11. Do not deploy production through FTP-only shared hosting if realtime, queues, or webhooks are required.
12. Add tests for the core marketplace flow.

---

## 6. Database Design to Implement

Create migrations for these tables.

### 6.1 Auth & Users

```txt
users
rider_profiles
driver_profiles
concierge_profiles
```

`users` must support roles:

```txt
rider
driver
concierge
admin
```

Driver profile must include:

```txt
vehicle_name
vehicle_plate
vehicle_type
tourism_license_no
approval_state
online_status
current_lat
current_lng
stripe_connect_id/payment_account_id
payout_enabled
```

Concierge profile must include:

```txt
hotel_name
hotel_ice
hotel_address
hotel_website
```

---

### 6.2 Location

```txt
cities
driver_locations
```

Seed initial cities:

```txt
Casablanca
Marrakech
Rabat
Fez
Tangier
Agadir
Essaouira
Chefchaouen
```

---

### 6.3 Orders & Dispatch

```txt
orders
order_offers
order_status_events
```

`orders` must unify Rider and Concierge ride requests.

Important fields:

```txt
source: rider | concierge
requester_id
concierge_id
city_id
hotel_name
guest_name
languages
pax
luggage
pickup_name
pickup_address
pickup_lat
pickup_lng
dropoff_name
dropoff_address
dropoff_lat
dropoff_lng
distance_km
eta_min
offered_fare
final_fare
payment_method: cash | card
status
assigned_driver_id
note
expires_at
assigned_at
arrived_at
started_at
completed_at
cancelled_at
```

Order statuses:

```txt
searching
offered
assigned
arrived
in_progress
completed
cancelled
expired
```

`order_offers` must store:

```txt
order_id
driver_id
type: accept | counter | decline
amount
message
status
```

---

### 6.4 Fare Config

```txt
fare_configs
```

Fields:

```txt
base
per_km
per_min
per_pax
floor
sedan_multiplier
minivan_multiplier
suv_multiplier
minibus_multiplier
luxury_multiplier
platform_fee_pct
currency
is_active
active_from
active_to
created_by
```

Server-side fare formula:

```txt
subtotal = base + (distance_km * per_km) + (eta_min * per_min) + ((pax - 1) * per_pax)
adjusted = subtotal * vehicle_multiplier
suggested_fare = max(floor, adjusted)
platform_fee = final_fare * platform_fee_pct
driver_net = final_fare - platform_fee
```

---

### 6.5 Wallet, Points & Revenue

```txt
wallets
wallet_ledger_entries
transactions
```

Wallet fields:

```txt
user_id
points_balance
wallet_balance
free_rides_remaining
currency
```

Ledger entry fields:

```txt
wallet_id
user_id
order_id
transaction_id
direction: credit | debit
entry_type
amount
points_delta
balance_after
points_after
reason
metadata
```

Ledger entry types:

```txt
cash_commission
card_net_earning
points_topup
free_ride_used
manual_adjustment
refund
payout
```

Transaction fields:

```txt
order_id
type: cash | card | points | payout | refund
source
rider_id
concierge_id
driver_id
city_id
fare
fee
net
currency
status
payment_provider_references
metadata
```

Rules:

- Cash ride: deduct platform fee from driver points.
- If no points: use free ride fallback.
- If no free rides: block driver.
- Card ride: charge rider, platform keeps fee, driver receives net.
- Top-up: driver buys points, points are added after payment success.
- Every movement must be written to `wallet_ledger_entries`.

---

### 6.6 Payments

```txt
payment_accounts
payment_intents
payment_webhook_events
payouts
```

If using Stripe Connect:

- Each driver needs a Connect/Express account.
- Card ride creates PaymentIntent.
- Platform fee is application fee.
- Net goes to driver.
- Webhooks must be idempotent.
- Handle refunds/disputes.

If using CMI/PayZone/local gateway:

- Document limitations.
- Build manual payout flow if automatic payout is not supported.

---

### 6.7 Driver Verification

```txt
driver_documents
driver_document_requests
```

Required 7 documents:

```txt
profile
vehicle_out
vehicle_in
id_front
id_back
license
tourism_agreement
```

Approval states:

```txt
incomplete
pending
approved
rejected
```

Rules:

- Driver uploads all documents.
- Admin reviews.
- Admin approves or rejects.
- Rejected/incomplete driver cannot go online.
- Admin can request a specific document.

---

### 6.8 Chat & Notifications

```txt
chat_messages
notifications
```

Chat must be linked to order:

```txt
order_id
sender_id
sender_role
text
image_url
created_at
```

Admin must be able to view chat archive by order.

Notification examples:

```txt
order_assigned
driver_arrived
ride_started
ride_completed
document_requested
driver_approved
driver_rejected
points_low
payment_failed
```

---

### 6.9 Admin & Audit

```txt
audit_logs
platform_settings
ratings
```

Audit logs must track:

```txt
actor_id
action
target_type
target_id
old_values
new_values
ip_address
user_agent
created_at
```

Use audit logs for:

```txt
driver approved
driver rejected
user suspended
fare config changed
commission changed
ride cancelled
payment webhook processed
wallet adjusted
```

---

## 7. Backend Services to Create

Create these services inside Laravel:

```txt
app/Services/FareService.php
app/Services/OrderService.php
app/Services/DriverDispatchService.php
app/Services/WalletService.php
app/Services/PaymentService.php
app/Services/DriverVerificationService.php
app/Services/ChatService.php
app/Services/NotificationService.php
app/Services/AuditLogService.php
```

### FareService

Must handle:

```txt
getActiveConfig()
estimateFare()
calculatePlatformFee()
calculateDriverNet()
```

### OrderService

Must handle:

```txt
createRiderOrder()
createConciergeOrder()
assignDriver()
markArrived()
startRide()
completeRide()
cancelRide()
expireOrder()
```

### DriverDispatchService

Must handle:

```txt
getNearbyOnlineDrivers()
broadcastOrderToDrivers()
acceptOrder()
counterOrder()
declineOrder()
removeOrderFromOtherDrivers()
```

### WalletService

Must handle:

```txt
createWalletForUser()
debitCashCommission()
creditCardEarning()
useFreeRide()
blockDriverIfNeeded()
topUpPoints()
writeLedgerEntry()
```

### PaymentService

Must handle:

```txt
createRidePayment()
createPointsTopup()
processWebhook()
handleRefund()
handlePayout()
```

### DriverVerificationService

Must handle:

```txt
uploadDocument()
checkRequiredDocuments()
submitForReview()
approveDriver()
rejectDriver()
requestDocument()
```

---

## 8. API Endpoints to Implement

### 8.1 Auth

```txt
POST   /api/register
POST   /api/login
GET    /api/me
POST   /api/logout
PATCH  /api/me/profile
```

---

### 8.2 Rider

```txt
POST   /api/rider/orders
GET    /api/rider/orders
GET    /api/rider/orders/{order}
GET    /api/rider/orders/{order}/offers
POST   /api/rider/orders/{order}/choose-driver
POST   /api/rider/orders/{order}/cancel
POST   /api/rider/orders/{order}/rating
```

---

### 8.3 Concierge

```txt
POST   /api/concierge/orders
GET    /api/concierge/orders
GET    /api/concierge/orders/{order}
GET    /api/concierge/orders/{order}/offers
POST   /api/concierge/orders/{order}/choose-driver
POST   /api/concierge/orders/{order}/cancel
POST   /api/concierge/orders/{order}/rating
```

---

### 8.4 Driver

```txt
POST   /api/driver/online
POST   /api/driver/offline
GET    /api/driver/orders
GET    /api/driver/orders/{order}
POST   /api/driver/orders/{order}/accept
POST   /api/driver/orders/{order}/counter
POST   /api/driver/orders/{order}/decline
POST   /api/driver/orders/{order}/arrived
POST   /api/driver/orders/{order}/start
POST   /api/driver/orders/{order}/complete
POST   /api/driver/location
GET    /api/driver/wallet
GET    /api/driver/earnings
POST   /api/driver/points/topup
GET    /api/driver/documents
POST   /api/driver/documents
```

---

### 8.5 Chat

```txt
GET    /api/orders/{order}/messages
POST   /api/orders/{order}/messages
```

---

### 8.6 Fare

```txt
POST   /api/fares/estimate
GET    /api/fare-config
```

---

### 8.7 Admin

```txt
GET    /api/admin/dashboard
GET    /api/admin/users
GET    /api/admin/users/{user}
PATCH  /api/admin/users/{user}/status

GET    /api/admin/orders
GET    /api/admin/orders/{order}
POST   /api/admin/orders/{order}/cancel
POST   /api/admin/orders/{order}/flag

GET    /api/admin/fare-config
POST   /api/admin/fare-config

GET    /api/admin/transactions
GET    /api/admin/revenue
GET    /api/admin/wallets
POST   /api/admin/wallets/{wallet}/adjust

GET    /api/admin/drivers/{driver}/documents
POST   /api/admin/drivers/{driver}/approve
POST   /api/admin/drivers/{driver}/reject
POST   /api/admin/drivers/{driver}/request-document

GET    /api/admin/chats
GET    /api/admin/chats/{order}

GET    /api/admin/audit-logs
```

---

### 8.8 Payments

```txt
POST   /api/payments/ride/{order}
POST   /api/payments/points/topup
POST   /api/webhooks/payment-provider
GET    /api/admin/payouts
POST   /api/admin/payouts/{payout}/confirm
```

---

## 9. Realtime Channels

Implement or prepare these channels:

```txt
drivers.{city_id}
order.{order_id}
driver.{driver_id}.location
chat.{order_id}
admin.feed
```

Events:

```txt
OrderCreated
DriverOfferSent
DriverAssigned
OrderRemovedFromQueue
RideArrived
RideStarted
RideCompleted
RideCancelled
DriverLocationUpdated
ChatMessageSent
TransactionCreated
DriverApproved
DriverRejected
DocumentRequested
```

---

## 10. Admin Dashboard Requirements

Build admin dashboard early because it helps test the backend.

Sections:

```txt
Dashboard overview
Users
Drivers
Driver verification
Orders / live rides
Fare config
Transactions / revenue
Wallets / points
Chat archive
Payouts
Audit logs
Settings
```

Admin dashboard must show real database data.

---

## 11. Mobile App Requirements

Build cross-platform mobile app after backend endpoints are stable.

### Rider Screens

```txt
Splash
Login/register
Home/book ride
City selector
Pickup/dropoff search
Fare estimate
Name your price
Driver offers
Driver profile
Live tracking
Chat
Payment
Rating
Ride history
Profile/settings
```

### Driver Screens

```txt
Splash
Login/register
Verification upload
Home online/offline
Order queue
Order details
Accept/counter/decline
Ride navigation/status
Chat
Complete ride
Wallet/points
Earnings history
Profile/settings
Blocked/rejected state
```

### Concierge Screens

```txt
Login
Hotel profile
Create guest ride
Guest details
Pickup/dropoff
Fare/budget
Driver offers
Choose driver
Track ride
Chat
Booking history
Profile/settings
```

Admin dashboard should be web, not mobile, for MVP.

---

## 12. Build Sequence

Follow this order:

### Phase 1 — Foundation

```txt
Setup Laravel
Setup PostgreSQL
Setup Sanctum
Create migrations
Seed cities and demo users
Create roles middleware
Create API response format
```

### Phase 2 — Fare and Orders

```txt
Build fare config
Build fare estimate
Build rider order creation
Build concierge order creation
Build driver order queue
```

### Phase 3 — Driver Marketplace

```txt
Driver online/offline
Driver accept/counter/decline
Assign driver
Order status lifecycle
Admin live orders
```

### Phase 4 — Wallet and Transactions

```txt
Create wallets
Cash commission points
Free ride fallback
Driver blocking logic
Card fake payment first
Transactions
Revenue dashboard
```

### Phase 5 — Realtime and Chat

```txt
Broadcast new order
Broadcast assignment
Ride status updates
Driver GPS updates
Chat per order
Admin chat archive
```

### Phase 6 — Verification

```txt
Document upload
Admin review
Approve/reject driver
Request specific document
Block unapproved drivers
```

### Phase 7 — Mobile App

```txt
Build Rider app
Build Driver app
Build Concierge app
Connect API
Connect maps
Connect realtime
```

### Phase 8 — Payments and Deployment

```txt
Integrate payment provider
Implement webhooks
Implement payouts
Deploy staging
Run QA
Deploy production
Create Android/iOS test builds
```

---

## 13. First Milestone to Deliver

The first real milestone is not the full app. It is this flow:

```txt
Admin can login
Rider can create order
Driver can go online
Driver can see order
Driver can accept order
Order becomes assigned
Driver can mark arrived
Driver can start ride
Driver can complete ride
System creates transaction
System updates wallet/points
Admin can see order and transaction
```

Do not proceed to polishing mobile UI until this flow works.

---

## 14. Testing Checklist

Create automated or manual tests for:

### Rider order flow

```txt
Rider login
Create order
Driver sees order
Driver accepts
Rider chooses driver
Driver arrives
Ride starts
Ride completes
Transaction created
Admin sees revenue
```

### Concierge order flow

```txt
Concierge login
Create guest ride
Driver sees hotel order
Driver accepts/counters
Concierge chooses driver
Ride completes
Transaction created
```

### Wallet logic

```txt
Cash ride deducts driver points
Card ride credits driver wallet
No points uses free ride
No points and no free rides blocks driver
Top-up adds points
Ledger entries are created
```

### Verification

```txt
Driver uploads 7 docs
Admin approves
Driver can go online
Admin rejects
Driver cannot go online
Admin requests document
Driver uploads requested document
```

### Chat

```txt
Rider/driver chat saved
Concierge/driver chat saved
Admin can view chat archive
```

### Admin

```txt
Admin sees users
Admin sees live rides
Admin updates fare config
Admin sees transactions
Admin sees revenue
Admin sees audit logs
```

---

## 15. Deployment Notes

FTP-only hosting is not enough for production if the system uses:

```txt
PostgreSQL
Redis
Queue workers
Realtime server
Payment webhooks
Cron jobs
Storage
SSL
```

Use VPS or cloud server.

Recommended domains:

```txt
api.domain.com       Backend API
admin.domain.com     Admin dashboard
www.domain.com       Landing page
staging.domain.com   Staging
```

Deployment requirements:

```txt
PHP 8.3
Composer
PostgreSQL
Redis
Supervisor for queues
Nginx
SSL certificate
Cron
S3-compatible storage
Environment variables
Backup strategy
```

---

## 16. Output Expected from Coding Assistant

When completing work, always provide:

```txt
Summary of what was done
Files created/modified
Database migrations added
API endpoints added
How to run locally
How to test the flow
Known limitations
Next tasks
```

Do not just say “done”. Explain exactly what changed.

---

## 17. Development Quality Rules

- Use clean Laravel services, not fat controllers.
- Use Form Requests for validation.
- Use Policies/Middleware for role access.
- Use Resources for API responses.
- Use database transactions for order completion/payment/wallet updates.
- Use idempotency for payment webhooks.
- Use enums/constants for statuses.
- Use tests for critical flows.
- Use audit logs for admin/system actions.
- Keep docs updated after each phase.

---

## 18. Immediate First Task

Start with:

```txt
1. Create Laravel backend
2. Setup PostgreSQL
3. Install Sanctum
4. Create migrations for core tables
5. Create seeders for cities and demo users
6. Create auth endpoints
7. Create role middleware
8. Create fare config service
9. Create first order creation endpoint
```

After that, implement the first milestone:

```txt
Rider creates order → Driver sees order → Driver accepts → Order assigned → Ride completed → Transaction created → Admin sees transaction
```

---

## 19. Final Instruction

Build MoroRide as a production marketplace system, not as a static demo.

The prototype gives the UX and business logic.
The backend must make all role interactions real.
The mobile app must consume the real API.
The admin dashboard must use real database data.
Payments, wallet, points, chat, verification, and revenue must be persistent and auditable.
# MoroRide - Current Project Status

Last updated: 2026-07-15

This repository currently contains a Laravel backend MVP and a responsive marketing homepage for MoroRide.

## What Has Been Done

### Production feature batch (2026-07-15)

A large batch closing most of the previously "missing" gaps. Backend changes are
syntax-verified and follow the existing service/event patterns; mobile changes
pass `tsc --noEmit` and the Expo web export.

- **Order auto-expiry** — `mororide:expire-orders` artisan command + every-minute
  scheduler (`app/Console/Kernel.php`). Expires stale `searching`/`offered`
  orders under a row lock, expires their pending offers, and broadcasts so
  drivers drop them from the queue.
- **Automatic refund/dispute reversal** — `WalletService::reverseRideSettlement()`
  reverses a card ride's driver net on a full refund, dispute opened, or dispute
  lost, writing a `refund` ledger entry (idempotent via the
  `payment_intent_id + entry_type` unique index) and recording any liability
  shortfall. Partial refunds stay flagged for manual review.
- **Provider-backed payouts** — `PayoutService::confirm()` executes a real
  idempotent **Stripe transfer** to the driver's connected account when Connect
  is ready (falls back to the manual/internal ledger otherwise); a new
  `transfer.reversed` webhook handler re-credits the wallet and fails the payout.
- **Push notifications** — new `device_tokens` table/model, register/unregister
  endpoints, `ExpoPushService` (best-effort delivery to Expo's push API with
  dead-token pruning), and a `PushNotificationRequested` event
  (`ShouldDispatchAfterCommit`) so `NotificationService::push()` never calls out
  inside a transaction. Push is disabled in tests via `EXPO_PUSH_ENABLED=false`.
- **Chat image attachments** — `POST /orders/{order}/messages/image` (participant
  check before validation, stored on the public disk) + an image picker in the
  Concierge chat.
- **Concierge Expo app** (`mobile/src/ConciergeApp.tsx`) — guest-ride booking,
  live offers, choose driver, track, chat (text + image), rating, history.
- **Account signup** — a Create-account screen in the role gate (real
  `/register`, role rider/driver/concierge); a signed-up session is respected
  over the demo login in all three apps.
- **Driver Wallet upgrades** — a "Buy points" screen (creates the points intent)
  and a "Set up payouts" (Stripe Connect onboarding) action.
- **Real GPS + push in mobile** — `expo-location` foreground publishing to
  `/driver/location` while online, and `expo-notifications` push-token
  registration on sign-in (added `expo-notifications`, `expo-device`,
  `expo-location`).
- **Rider "Pay by card"** action on the tracking screen (creates the ride intent
  and surfaces its status).
- **Production realtime deployment guide** — [`docs/PRODUCTION-REALTIME.md`](docs/PRODUCTION-REALTIME.md)
  (Reverb behind Nginx TLS, Supervisor, cron, scaling).

> The native Stripe **PaymentSheet** is now wired (`@stripe/stripe-react-native`)
> for the rider "Pay by card" and driver "Buy points" flows — it runs in a
> dev/preview **APK build**, not in Expo Go or on web (a `.web.ts` shim keeps
> Stripe out of the web bundle). Config plugins for `expo-notifications`,
> `expo-location`, `@stripe/stripe-react-native`, and cleartext HTTP are in
> `app.json`; `eas.json` has a `preview` (APK) profile. True **background** GPS
> still needs a TaskManager task in that build.

### Backend MVP

- Laravel backend exists in `backend/`, running **Laravel 12 on PHP 8.3**.
- Docker Compose provides PostgreSQL, Redis, a **PHP 8.3 `app` container**, and a dedicated **Laravel Reverb WebSocket container** on port `8080`.
- Laravel Sanctum token authentication is installed and wired.
- Role middleware exists for `rider`, `driver`, `concierge`, and `admin`.
- Demo users and cities are seeded.
- Core marketplace migrations were added for profiles, cities, orders, offers, fare configs, wallets, ledger entries, transactions, chat messages, notifications, audit logs, Stripe payment intents/accounts, and idempotent webhook receipts.
- Core services exist for fare calculation, order lifecycle, driver dispatch, wallet/points ledger logic, Stripe payments, audit logging, driver verification, payouts, platform settings, and notifications.
- The first marketplace flow is implemented and tested:
  - rider creates an order
  - approved driver goes online
  - driver sees open orders
  - driver accepts
  - order becomes assigned
  - driver marks arrived
  - driver starts ride
  - driver completes ride
  - transaction is created
  - driver wallet/points ledger is updated
  - admin can see order and transaction data
- Automated test exists for the first marketplace flow:

```bash
php artisan test --filter=MarketplaceFlowTest
```

### Security, Dispatch, and Wallet Hardening

The following production-critical fixes are now implemented and covered by feature tests:

- **Resource ownership:** riders and concierges can only open their own orders. Cross-account access returns `404` so an order's existence is not disclosed.
- **Notification privacy:** direct notifications can only be marked read by their owner. Global and role-targeted notifications use per-user records in `notification_reads`, so one user's read state does not affect another user.
- **Driver dispatch eligibility:** the driver queue is limited to active, approved, online drivers; expired and previously declined orders are excluded; and orders are scoped to the driver's latest known city when a location is available.
- **Atomic marketplace actions:** accept/counter/decline re-check the locked order before changing it. This prevents two drivers from assigning the same ride and prevents stale actions from reviving assigned or expired orders.
- **Wallet safety:** when a cash ride cannot cover commission with points or a free ride, completion is rolled back, no transaction or ledger entry is committed, and the driver is persistently blocked and taken offline after the rollback.
- Dedicated regression coverage exists in `ResourceAuthorizationTest`, `DriverDispatchTest`, and `CashRideCompletionWalletTest`.
- Full verification result on 2026-07-14: **77 tests passed with 562 assertions**; the Expo mobile TypeScript check also passes.

### Participant Flows, Chat, GPS, and Realtime Backend

- **Rider and Concierge offers:** each requester can privately list non-decline offers for their own order.
- **Choose driver:** a requester can atomically select a pending accept/counter offer while the order is open. The selected driver is re-checked as active, approved, and online; the selected fare becomes final and competing offers are rejected. An existing immediate driver acceptance remains authoritative and cannot be overwritten.
- **Requester cancellation:** Rider and Concierge owners can cancel in `searching`, `offered`, `assigned`, or `arrived`; cancellation is rejected once the ride is in progress. Status history and audit logs are written.
- **Ratings:** a completed assigned ride can be rated once by its Rider/Concierge owner with a score from 1 to 5 and an optional comment. The database enforces one rating per order.
- **Participant chat:** assigned ride participants can list and send persisted text or HTTP(S) image messages. Sender identity/role comes from Sanctum, and non-participants receive `404` before payload validation.
- **Continuous driver GPS:** `POST /api/driver/location` appends fresh, monotonic location history, updates the profile coordinates, requires an active/approved/online/unblocked driver, and prevents changing city during an active ride.
- **Realtime delivery:** Sanctum-protected private channels exist for city dispatch, orders, driver location, chat, and the admin feed. Events are emitted after successful database commits and are now delivered by the Docker Reverb service.
- The Expo Rider client uses Laravel Echo/Pusher protocol subscriptions for order offers/status, private ride chat, and assigned-driver GPS updates. `REVERB_ALLOWED_ORIGINS=*` is local-only; production must use an exact allow-list and TLS.
- Regression coverage exists in `ParticipantOrderFlowTest`, `ParticipantChatTest`, `DriverLocationTest`, `RealtimeBroadcastingTest`, and `ParticipantRealtimeIntegrationTest`.

### Reverb, Stripe, and Expo Rider Integration

- **Reverb transport:** `laravel/reverb` is installed, `config/broadcasting.php` has a real `reverb` connection, Docker runs `php artisan reverb:start`, and the PHP image includes `pcntl` for graceful signal handling. A real Pusher-protocol client handshake against `ws://127.0.0.1:8080` was verified.
- **Stripe payment foundation:** `stripe/stripe-php` is installed. Card rides use one idempotent PaymentIntent per order/fare and cannot be completed until a signed `payment_intent.succeeded` webhook is persisted. A client-side status refresh cannot unlock fulfillment.
- **Points purchases:** driver point top-ups require an `Idempotency-Key`; points are credited from the succeeded webhook only, with a database uniqueness guard against retries.
- **Refunds and disputes:** admins can request idempotent Stripe refunds. Refund/dispute webhooks update the internal payment and ride transaction state and flag wallet reconciliation for review.
- **Admin-managed payment configuration:** the admin dashboard has a dedicated Payment Settings area for Cash/Stripe selection, test/live mode, keys, webhook signing, currency/top-up rules, and Stripe Connect. Secret values are encrypted at rest, masked in responses, omitted from generic settings and audit logs, and can only be removed through an explicit clear action. Dashboard values are the runtime source after first setup; `.env` is a bootstrap fallback.
- **Stripe Connect:** driver Express-account creation, single-use onboarding links, account status synchronization, and `account.updated` handling use the dashboard-controlled Connect setting.
- **Expo Rider:** offers are loaded from the participant API (not the public driver catalog), choose/cancel/rating actions call the backend, private text chat is usable, and live order/GPS events update the UI.
- Focused payment coverage lives in `StripePaymentFlowTest` (6 cases, including signature rejection, webhook idempotency, card settlement, points, Connect, refund retry, and disabled-mode behavior).
- Payment-settings security and authorization coverage lives in `AdminPaymentSettingsTest` (6 cases covering encryption/masking, safe audit logs, explicit secret clearing, mode validation, runtime override, connection testing, and admin-only access).

### Verification Workflow For These Fixes

From the repository root, install the locked PHP dependencies, build the `pcntl`-enabled image, start Laravel/Reverb, and apply all migrations:

```bash
docker compose build app reverb
docker compose run --rm -e COMPOSER_PROCESS_TIMEOUT=0 app composer install
docker compose up -d postgres redis app reverb
docker compose exec -T app php artisan migrate
```

Run the focused regression suites, then the complete backend suite:

```bash
docker compose exec -T app php artisan test --filter=ResourceAuthorizationTest
docker compose exec -T app php artisan test --filter=DriverDispatchTest
docker compose exec -T app php artisan test --filter=CashRideCompletionWalletTest
docker compose exec -T app php artisan test --filter=ParticipantOrderFlowTest
docker compose exec -T app php artisan test --filter=ParticipantChatTest
docker compose exec -T app php artisan test --filter=DriverLocationTest
docker compose exec -T app php artisan test --filter=RealtimeBroadcastingTest
docker compose exec -T app php artisan test --filter=ParticipantRealtimeIntegrationTest
docker compose exec -T app php artisan test --filter=StripePaymentFlowTest
docker compose exec -T app php artisan test --filter=AdminPaymentSettingsTest
docker compose exec -T app php artisan test
```

Verify the mobile TypeScript project:

```bash
cd mobile
npm install
npm run typecheck
npx expo export --platform web --output-dir dist-check --clear
```

Confirm the live WebSocket service with `docker compose ps reverb` and `docker compose logs reverb`; the log must show `Starting server on 0.0.0.0:8080`.

For a manual smoke test, sign in with the seeded accounts, create a rider order, then take the driver online with `city_id`, `current_lat`, and `current_lng`. Confirm that the driver only sees eligible rides in that city and that another rider/concierge cannot open the order by ID.

- A dedicated **admin web console** exists (not a demo page):
  - `/admin/login` — branded sign-in page (token auth against `/api/login`, admin-only).
  - `/admin` — full single-page dashboard with a sidebar: dashboard, users, drivers & verification, orders, wallets, transactions & revenue, payment settings, payouts, chat archive, audit logs, and general settings. Payment configuration includes readiness and webhook health, masked credentials, validation, and a server-side Stripe connection test.
- Browser demo pages exist for testing the marketplace MVP flow without Postman:
  - `/app`
  - `/app/rider`
  - `/app/concierge`
  - `/app/driver`
  - (`/app/admin` now redirects to the real `/admin` console.)
- A database-backed notification foundation now exists:
  - `notifications` table
  - seeded global and role-targeted notification records
  - API endpoint to list notifications
  - authenticated endpoint to mark a notification as read
- Public catalog data is now exposed from the database for the mobile app:
  - active cities
  - vehicle types and fare multipliers
  - approved drivers and vehicle profiles

### Admin Backend (professional)

The admin surface was rebuilt from a single monolithic controller into a clean,
service-backed structure under `app/Http/Controllers/Api/Admin/`:

- **Thin controllers** per domain (dashboard, users, orders, driver verification, wallets, transactions, payouts, chat, audit logs, fare config, settings).
- **Services** for all business logic: `DriverVerificationService`, `PayoutService`, `PlatformSettingsService`, `NotificationService`, plus `WalletService::adjust()`/`debitForPayout()` and `OrderService::cancelByAdmin()`/`flagOrder()`.
- **Form Requests** validate every mutation (`app/Http/Requests/Admin/`).
- **API Resources** shape responses (`UserResource`, `DriverDocumentResource`, `AuditLogResource`, `PayoutResource`, `ChatMessageResource`).
- **Every admin mutation writes an audit log** and, where relevant, an in-app notification to the affected user.
- List endpoints are **paginated and filterable** (role/status/search for users, status/city/flagged for orders, etc.).

Admin capabilities implemented:

- **Dashboard overview**: user/driver/order/revenue rollups, pending documents & payouts, recent audit activity.
- **User management**: list/filter/search, view, suspend/block/reactivate (offlines the driver, notifies the user).
- **Order management**: list/filter, full detail with status timeline, cancel with reason, flag for review.
- **Driver verification**: list drivers by approval state, per-document checklist, approve, reject with reason, request a specific document.
- **Wallets & points**: list, detail with ledger, manual points/balance adjustment (writes a ledger entry).
- **Transactions & revenue**: filterable transactions, revenue breakdown (gross / fees / driver net / cash vs card).
- **Payouts**: create a payout from a driver's card balance, confirm it (debits the wallet, writes a `payout` ledger entry).
- **Chat archive**: list orders with chat activity, read the full persisted conversation per order.
- **Audit logs**: filterable log of every admin/system action.
- **Fare config**: view active config + history, publish a new active config.
- **Platform settings**: typed key/value settings (general/billing/verification), batch update.

New database tables: `driver_documents`, `driver_document_requests`, `payouts`, `platform_settings`, plus admin columns on `orders` (flag/cancel metadata) and `users` (suspension metadata).

Driver-side document upload (`GET`/`POST /api/driver/documents`) was added so the verification loop is real end-to-end. The seeder now includes a **pending driver with uploaded documents** so the verification queue has data to review.

Tested by `AdminBackendTest` (11 cases covering dashboard, users, orders, verification, wallet adjust, payouts, chat archive, settings, audit logs, and role enforcement).

### Implemented API Surface

General:

- `GET /api/catalog`
- `GET /api/notifications`
- `POST /api/notifications/{notification}/read`
- `POST /api/device-tokens` (register an Expo push token)
- `DELETE /api/device-tokens` (unregister a push token)

Auth:

- `POST /api/register`
- `POST /api/login`
- `GET /api/me`
- `POST /api/logout`
- `PATCH /api/me/profile`

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
- `POST /api/driver/payments/points/intent`
- `POST /api/driver/payments/connect/onboarding`
- `GET /api/driver/payments/connect/status`
- `GET /api/driver/documents`
- `POST /api/driver/documents`

Participant chat:

- `GET /api/orders/{order}/messages`
- `POST /api/orders/{order}/messages`
- `POST /api/orders/{order}/messages/image` (multipart image attachment)

Payments:

- `POST /api/payments/orders/{order}/intent`
- `POST /api/webhooks/stripe` (public endpoint; requires a valid `Stripe-Signature`)
- `POST /api/admin/payments/{paymentIntent}/refund` (requires an `Idempotency-Key` header)
- `GET /api/admin/payment-settings` (never returns raw secret values)
- `PUT /api/admin/payment-settings`
- `POST /api/admin/payment-settings/test`

Broadcast authorization:

- `POST /broadcasting/auth` (Sanctum-protected private-channel authorization)

Admin:

- `GET /api/admin/dashboard`
- `GET /api/admin/users`
- `GET /api/admin/users/{user}`
- `PATCH /api/admin/users/{user}/status`
- `GET /api/admin/orders`
- `GET /api/admin/orders/{order}`
- `POST /api/admin/orders/{order}/cancel`
- `POST /api/admin/orders/{order}/flag`
- `GET /api/admin/drivers`
- `GET /api/admin/drivers/{driver}/documents`
- `POST /api/admin/drivers/{driver}/approve`
- `POST /api/admin/drivers/{driver}/reject`
- `POST /api/admin/drivers/{driver}/request-document`
- `GET /api/admin/fare-config`
- `POST /api/admin/fare-config`
- `GET /api/admin/transactions`
- `GET /api/admin/revenue`
- `GET /api/admin/wallets`
- `GET /api/admin/wallets/{wallet}`
- `POST /api/admin/wallets/{wallet}/adjust`
- `GET /api/admin/payouts`
- `POST /api/admin/payouts`
- `POST /api/admin/payouts/{payout}/confirm`
- `GET /api/admin/chats`
- `GET /api/admin/chats/{order}`
- `GET /api/admin/audit-logs`
- `GET /api/admin/settings`
- `POST /api/admin/settings`

### Homepage / Landing Page

- A responsive homepage exists at `/`.
- The page is implemented as real HTML/CSS in `backend/resources/views/welcome.blade.php`.
- It is not a single pasted screenshot.
- Homepage sections:
  - hero
  - how it works
  - tourists card
  - hotels and riads card
  - drivers card
  - safety and trust
  - marketplace features
  - cities we cover
  - CTA banner
  - footer
- Mobile responsive navigation is implemented with a hamburger menu.
- The top menu links only point to existing homepage sections:
  - `#how`
  - `#tourists`
  - `#hotels`
  - `#drivers`
  - `#safety`
  - `#features`
  - `#cities`
- Footer links were cleaned so they point to existing homepage sections.
- Font Awesome is used for social media icons and app store badges.
- Visual assets were copied into `backend/public/assets/mororide/`, including hero, role mockups, city images, Morocco map, and Moroccan arch CTA side decoration.

### Mobile App

An Expo React Native cross-platform app in `mobile/` (runs on web + iOS + Android). On launch it shows a **role gate** (`Rider` / `Driver`); the side menu can switch apps at any time.

**Rider app** (`App.tsx`):
- Screens: booking (city / pickup / dropoff / name-your-price), driver offers, driver profile, live map tracking, private ride chat, completed + rating.
- Real APIs: order creation, offer listing/refresh/choose-driver/cancel, rating, participant chat (send/list/realtime), notifications drawer.
- Laravel Echo subscriptions (Reverb) for private order, chat, and driver-location channels; live driver coordinates on the Google map.

**Driver app** (`src/DriverApp.tsx`, modernized) — a bottom-tab layout, every button wired:
- **Drive tab**: go online/offline with a city selector; **incoming requests** queue (polls ~4s) with **Accept / Counter / Decline**; active-ride panel with a status stepper (**I've arrived → Start → Complete**), earnings summary, and in-ride **chat**.
- **Verify tab**: the 7-document checklist with **photo upload** per document (via `expo-image-picker`, multipart `POST /api/driver/documents`), status badges (missing / pending / approved / rejected) and an upload-progress bar.
- **Wallet tab**: balance, points, free rides, and recent ledger activity.
- **Profile tab**: account details, switch to the Rider app, sign out.

> Cross-role testing note: rider and driver share one browser session token, so run them in two separate browsers (or one Incognito). To test on a **real Android phone via Expo Go**, see `TESTING.md` §3b.

## How To Run Locally

> **Testing:** for a step-by-step test workflow (automated tests + manual end-to-end: admin console, rider app, driver app, cross-role ride, realtime, payments) see **[`TESTING.md`](TESTING.md)**.

### Option A — Docker (PHP 8.3, matches production target, recommended)

From `mororide/`:

```bash
docker compose build app reverb
docker compose run --rm -e COMPOSER_PROCESS_TIMEOUT=0 app composer install
docker compose run --rm app php artisan migrate --seed
docker compose up -d postgres redis app reverb
```

Notes:
- The `app` service (`backend/Dockerfile`) runs Laravel 12 on PHP 8.3 with `pdo_pgsql`, `pdo_sqlite`, `redis`, and `pcntl`. The `reverb` service uses the same image and exposes WebSockets on `8080`.
- `docker-compose.yml` overrides `DB_HOST=postgres` and `REDIS_HOST=redis` for the container, so no `.env` change is needed.
- **Performance (important on Windows/macOS):** the Docker bind-mount is very slow at reading many small files, which made every request re-read/re-compile the framework (4–16s per request). Two fixes make it fast (~0.2–0.9s):
  - **OPcache** is enabled in the image with `opcache.enable_cli=1` (required because the app runs under the CLI SAPI via `php -S`).
  - **`vendor/` lives in a Linux-native named volume** (`mororide_vendor`), not the bind-mount. Because of this, run Composer **inside the container** (`docker compose run --rm app composer ...`) so it writes to that volume — running Composer on the host will not update what the container uses. App code (`app/`, `routes/`, `resources/`) stays bind-mounted, so edits are still picked up live (OPcache `revalidate_freq=2`).
- Run tests with: `docker compose exec -T app php artisan test`. The suite uses an isolated in-memory sqlite database (forced in `phpunit.xml`), so it never touches the dev Postgres data.

### Admin Payment Settings (Stripe)

Stripe is disabled by default, so cash flows keep working without external credentials. Payment configuration is now managed from **Admin Dashboard -> Payment Settings**, not by editing `.env` for each change.

Security behavior:

- `sk_...` and `whsec_...` values are encrypted with the Laravel application key before database storage.
- Secret values are never returned to the browser, generic settings endpoint, or audit logs. The dashboard only receives a masked status.
- Leaving a secret field blank keeps the stored value. Removing it requires checking the explicit clear box.
- Test keys must match Test mode (`pk_test_` / `sk_test_`); Live mode requires `pk_live_` / `sk_live_`, HTTPS Connect callback URLs, and complete credentials before it can be enabled.
- After Payment Settings are initialized, dashboard/database values are authoritative. The `STRIPE_*` environment block remains a bootstrap/fallback for an installation that has not opened this settings area yet.

Important availability note: as of 2026-07-14, Morocco is not listed as a supported Stripe business country on [Stripe's global availability page](https://stripe.com/global). Test-mode development is fine, but Live mode must use a real verified business and bank account in a supported country, or MoroRide must integrate a Morocco-compatible provider such as CMI/Payzone. Never enter an inaccurate Connect country.

Admin setup workflow:

1. Open `http://127.0.0.1:8000/admin`, sign in with an admin account, and choose **Payment Settings**.
2. In Stripe Dashboard Test mode, copy the publishable key and secret key from **Developers -> API keys**.
3. For local webhooks, authenticate and start the official Stripe CLI listener:

```bash
stripe login
stripe listen --forward-to http://127.0.0.1:8000/api/webhooks/stripe
```

4. In Payment Settings select **Stripe** and **Test**, paste `pk_test_...`, `sk_test_...`, and the listener's printed `whsec_...`. The CLI signing secret is different from a Stripe Dashboard production endpoint secret.
5. Set MAD top-up limits/ratio. Leave Connect off unless it is being tested with a real platform country supported by Stripe. If enabled, enter the actual two-letter country and both callback URLs.
6. Save while Stripe is disabled if the configuration is not complete. Click **Test Stripe connection**; after it succeeds, enable Stripe and save again.
7. Confirm the status cards show `ready`, the credentials show `Configured`, and webhook deliveries start appearing after a test PaymentIntent event.

For staging/production, register the exact HTTPS webhook URL displayed by the dashboard and subscribe to:

```txt
payment_intent.succeeded
payment_intent.payment_failed
charge.refunded
charge.dispute.created
charge.dispute.closed
account.updated
```

Select connected-account delivery for `account.updated` when Connect is enabled. Keep the raw request body unchanged; the backend verifies `Stripe-Signature` before persisting or processing an event. Keep test and live keys/signing secrets separate.

The following `.env` values are optional bootstrap defaults only:

```dotenv
STRIPE_ENABLED=false
STRIPE_MODE=test
STRIPE_SECRET_KEY=
STRIPE_PUBLISHABLE_KEY=
STRIPE_WEBHOOK_SECRET=
STRIPE_CURRENCY=mad
```

If these fallback values change before dashboard settings are initialized, clear Laravel config with `docker compose exec -T app php artisan config:clear`. Normal dashboard saves are active immediately and do not need an app restart.

### Workflow To Test This Batch

1. Build/start the complete local stack and migrate:

```bash
docker compose build app reverb
docker compose run --rm -e COMPOSER_PROCESS_TIMEOUT=0 app composer install
docker compose up -d postgres redis app reverb
docker compose exec -T app php artisan migrate --seed
docker compose ps
docker compose logs --tail=30 reverb
```

2. Run automated verification:

```bash
docker compose exec -T app php artisan test --filter=StripePaymentFlowTest
docker compose exec -T app php artisan test --filter=AdminPaymentSettingsTest
docker compose exec -T app php artisan test
cd mobile
npm install
npm run typecheck
npx expo export --platform web --output-dir dist-check --clear
```

Expected result: `77 passed (562 assertions)`, mobile typecheck exits `0`, the Expo web bundle exports successfully, and Reverb logs `Starting server on 0.0.0.0:8080`.

3. Configure the Expo process for the target device. Android emulator defaults already use `10.0.2.2`; for a physical phone use the computer's LAN IP for both HTTP and WebSocket traffic:

```powershell
$env:EXPO_PUBLIC_API_BASE_URL="http://YOUR_PC_IP:8000/api"
$env:EXPO_PUBLIC_REVERB_HOST="YOUR_PC_IP"
$env:EXPO_PUBLIC_REVERB_PORT="8080"
$env:EXPO_PUBLIC_REVERB_SCHEME="http"
$env:EXPO_PUBLIC_REVERB_APP_KEY="mororide-local-key"
npm start
```

4. Rider realtime smoke test:
   - Create a ride in Expo Rider.
   - In `/app/driver`, sign in as the seeded driver, go online in the same city, and accept/counter the ride.
   - Confirm the real offer appears without restarting Expo, select it, open chat, and exchange a message.
   - Send a driver location update and confirm the map car coordinate changes.
   - Complete the ride from the driver flow, then submit the Rider rating.

5. Stripe test-mode smoke test:
   - Open `/admin#payments`, save the three test credentials, run **Test Stripe connection**, enable Stripe, and keep `stripe listen --forward-to http://127.0.0.1:8000/api/webhooks/stripe` running.
   - Create a card order and assign a driver before requesting `POST /api/payments/orders/{order}/intent` as its Rider/Concierge owner.
   - Confirm the returned test PaymentIntent using a Stripe test client/payment method. Verify the CLI forwards `payment_intent.succeeded` and the internal intent becomes `succeeded` once.
   - Verify driver completion is rejected before that webhook and accepted afterward.
   - As admin, send a unique `Idempotency-Key` to `POST /api/admin/payments/{paymentIntent}/refund`; repeating the same request/key must return the same refund and not create another provider call.

The native PaymentSheet screen is still intentionally listed as missing, so the Stripe smoke test currently validates the backend/provider lifecycle rather than an end-user card UI.

### Option B — Local PHP (requires PHP 8.2+ installed locally)

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

In a second backend terminal, start realtime delivery:

```bash
php artisan reverb:start --host=0.0.0.0 --port=8080
```

Open:

```txt
http://127.0.0.1:8000/
```

Admin web console:

```txt
http://127.0.0.1:8000/admin/login   (sign in as admin@mororide.test / password)
http://127.0.0.1:8000/admin         (dashboard, guarded by token)
```

Browser MVP pages (rider/driver/concierge marketplace flow):

```txt
http://127.0.0.1:8000/app
http://127.0.0.1:8000/app/rider
http://127.0.0.1:8000/app/concierge
http://127.0.0.1:8000/app/driver
```

Demo credentials use password `password`:

- `admin@mororide.test`
- `rider@mororide.test`
- `driver@mororide.test`
- `concierge@mororide.test`

## What Is Still Missing

| Area | Status | Done now | Still missing / production caveat |
|---|---|---|---|
| Laravel Reverb transport | Done locally | Docker service, real `reverb` broadcaster, private auth, Echo client, successful live WebSocket handshake | Production TLS/reverse proxy, exact origins, process supervision, metrics and scaling |
| Payment provider settings | Done | Admin dashboard manages Cash/Stripe, mode, encrypted/masked credentials, top-up limits, Connect, readiness/webhook health, and a safe connection test; `.env` is fallback only | Add a Morocco-compatible provider adapter if Stripe cannot be used legally |
| Stripe PaymentIntents | Backend done / dashboard-controlled | Idempotent ride and points intents, MAD minor units, no secret exposure | Native Stripe PaymentSheet/payment UI and a legally supported live platform account |
| Stripe webhooks | Done | Raw-body signature verification, event deduplication, retries, success/failure/refund/dispute/account events | Production endpoint registration, alerting and operational replay runbook |
| Card ride settlement | Done | Ride completion requires a succeeded signed webhook; driver net credited once. The Rider tracking screen has a **"Pay by card"** action that opens the **native Stripe PaymentSheet** (`@stripe/stripe-react-native`, runs in the APK build) | Live test-device run against real Stripe test keys |
| Points top-up | Done | Idempotency header, webhook-only credit, ledger uniqueness, driver Wallet "Buy points" screen, **and native Stripe PaymentSheet** card entry (`@stripe/stripe-react-native`, runs in the APK build) | Live test-device run against real Stripe test keys |
| Refunds/disputes | Done (backend) | Admin refund request, provider state sync, **and automatic driver-net reversal** on full refund / dispute-opened / dispute-lost (writes a `refund` ledger entry, records any liability shortfall) | Partial refunds are still flagged for manual review by design |
| Stripe Connect | Backend foundation | Express account creation, browser onboarding link, status sync | Disabled by default; live use requires a real supported country/entity/bank account |
| Provider-backed payouts | Done (backend) | Confirming a payout now executes a real **Stripe transfer** to the driver's connected account (idempotent) when Connect is ready; `transfer.reversed` webhook re-credits the wallet and marks the payout failed. Manual/internal ledger payout still works when Connect is off | Live end-to-end run needs a supported Connect country/entity; connected-account payout reconciliation is documented, not automated |
| Expo Rider offers | Done | Real offers, refresh, choose-driver and cancellation APIs | Better empty/retry UX and full device E2E automation |
| Expo Rider rating | Done | Completed-order rating API connected | Review moderation/reporting UI |
| Ride chat | Done (text + image) | Persisted private text chat, realtime receive, **and image attachments** (multipart upload endpoint + in-app picker in the Concierge app) | Delivery/read receipts; image send button in the large Rider chat screen |
| Live driver map | Done (foreground) | Private GPS subscription updates the Rider map; the driver app now **publishes real device GPS** (`expo-location`, ~8s/25m throttle) to `/driver/location` while online | Native **background** tracking (dev build + TaskManager), Directions polylines, retention/pruning |
| Driver mobile app | Done | `DriverApp`: online/offline with city, requests queue (accept/counter/decline), ride lifecycle, earnings, wallet **+ buy-points + payout onboarding**, in-ride chat, **foreground GPS publishing**, and **push registration** | Native **background** GPS (dev build), richer navigation/maps |
| Concierge mobile app | Done | Full **Concierge Expo app**: guest-ride booking form, live driver offers, choose driver, track, in-ride chat (text + image), rating, and booking history | Google Places pickup/drop-off; richer hotel profile screen |
| Driver verification & registration (mobile) | Done | **Signup screen** in the role gate (name/email/phone/password/role → real `/register`); 7-document upload already worked; a signed-up session is respected over demo login | Native camera capture polish; resubmission UX |
| Notifications | Done (push) | Database notifications + read state, **Expo push delivery** via `device_tokens` (register/unregister endpoints, best-effort after-commit send, dead-token pruning), and mobile push registration on sign-in | iOS/Android production credentials (APNs/FCM) for a dev/standalone build; notification center screen |
| Order expiry | Done | `mororide:expire-orders` command + every-minute scheduler expires stale `searching`/`offered` orders (locked/re-checked), expires their pending offers, and notifies drivers to drop them | — |
| Admin backend/web console | Done | All current admin domains are covered | Optional charts, bulk actions, richer pagination and realtime refresh |
| Automated verification | Strong backend coverage | 77 tests / 562 assertions; mobile TypeScript and Expo web export pass | Physical-device journeys, background GPS, production WebSocket and PaymentSheet E2E tests |
| Mobile dependency security | Needs upgrade | Audit captured | Expo SDK 52 dependency tree reports 18 non-critical/critical advisories (6 moderate, 12 high); upgrade Expo deliberately, not with a forced audit fix |
| Homepage/store assets | Partial | Responsive landing page and app branding exist | Real-browser visual QA, real store/social URLs, store-ready metadata/screenshots |
| Deployment | Docs added | Local Docker stack works; **production Reverb/TLS/Supervisor/cron guide** in [`docs/PRODUCTION-REALTIME.md`](docs/PRODUCTION-REALTIME.md); shared-hosting + MySQL path documented | Staging/prod SSL automation, backups, storage strategy, monitoring/alerting |

## Next Recommended Tasks

1. Decide the lawful Morocco payment path: supported Stripe entity versus CMI/Payzone; then add the native card UI, provider-backed payouts, and refund/dispute wallet reconciliation.
2. Build the Expo Driver and Concierge role stacks, including native background GPS publishing.
3. Add Google Places/Directions, real pickup/drop-off selection, route polylines, and ETA refresh.
4. Add push notifications, chat attachments, and delivery/read receipts.
5. Upgrade Expo SDK/dependencies in a dedicated tested change, then add physical-device end-to-end journeys.
6. Add staging/production infrastructure: TLS reverse proxy for app/Reverb, supervision, queues, cron, backups, storage and monitoring.

---

# Original Product Brief / Build Order
