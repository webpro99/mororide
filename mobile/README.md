# MoroRide Mobile

React Native / Expo cross-platform app for the MoroRide marketplace MVP.

This app is the mobile companion to the Laravel backend in `../backend`. It currently targets:

- iOS through Expo
- Android through Expo
- Web through Expo web

## What Is Built

- Expo React Native project scaffold.
- Shared API client connected to the Laravel Sanctum API.
- Laravel Echo + Pusher-protocol client connected to the local Reverb server with Sanctum private-channel authorization.
- Expo-compatible NetInfo and Babel static-class-block support required by the React Native Pusher/Echo bundle.
- Database-backed catalog loading for cities, vehicle types, fare multipliers, and approved drivers.
- Database-backed notification drawer loaded from `GET /api/notifications`.
- Clickable notification icon in the booking header.
- Animated side navigation for the current ride screens.
- MoroRide brand mark loaded from the provided logo assets.
- Expo app icon, splash image, Android adaptive icon, and web favicon configured from the provided logo assets.
- Native Google Maps support is prepared through `react-native-maps` for Android/iOS builds when `EXPO_PUBLIC_GOOGLE_MAPS_API_KEY` is set.
- Web keeps an OpenStreetMap tile fallback for local browser testing.
- Demo login for the seeded accounts:
  - `rider@mororide.test`
  - `concierge@mororide.test`
  - `driver@mororide.test`
  - `admin@mororide.test`
- Current ride screens:
  - ride booking
  - driver offers
  - driver profile
  - live tracking
  - private ride chat
  - completed ride
- Current ride actions:
  - create a rider order through the Laravel API
  - choose cities and vehicle types loaded from the database
  - list actual order offers and refresh them
  - choose an offer or track an already accepted driver
  - cancel the owned order
  - send/list private participant text messages
  - submit a completed-order rating
  - receive realtime offer, order-status, chat, and driver-location events
  - open database-backed notifications

## Required Backend

Start the Laravel backend first.

From `mororide/`:

```bash
docker compose up -d postgres redis app reverb
```

From `mororide/backend/`:

```bash
composer install
php artisan migrate --seed
php artisan serve
```

The backend should be available at:

```txt
http://127.0.0.1:8000
```

## Install Mobile Dependencies

From `mororide/mobile/`:

```bash
npm install
```

## Run The App

From `mororide/mobile/`:

```bash
npm start
```

Then choose the target from Expo:

- press `w` for web
- press `a` for Android emulator
- scan the QR code with Expo Go for a physical phone

Direct commands:

```bash
npm run web
npm run android
npm run ios
```

## API URL

The app reads the API URL from `EXPO_PUBLIC_API_BASE_URL`.

Default values:

- Android emulator: `http://10.0.2.2:8000/api`
- iOS simulator and web: `http://127.0.0.1:8000/api`

For a physical phone, use your computer LAN IP:

```bash
$env:EXPO_PUBLIC_API_BASE_URL="http://YOUR_PC_IP:8000/api"
$env:EXPO_PUBLIC_REVERB_HOST="YOUR_PC_IP"
$env:EXPO_PUBLIC_REVERB_PORT="8080"
$env:EXPO_PUBLIC_REVERB_SCHEME="http"
$env:EXPO_PUBLIC_REVERB_APP_KEY="mororide-local-key"
npm start
```

Example:

```bash
$env:EXPO_PUBLIC_API_BASE_URL="http://192.168.1.20:8000/api"
npm start
```

The phone and computer must be on the same network.

## Google Maps

For native Android/iOS builds, set a Google Maps API key before starting or building:

```bash
$env:EXPO_PUBLIC_GOOGLE_MAPS_API_KEY="YOUR_GOOGLE_MAPS_KEY"
npm start
```

Without this key, the app uses the OpenStreetMap fallback in web/local testing. Real production routes still need a Directions API integration for turn-by-turn polylines and live driver location updates.

## Test The Marketplace Flow

1. Open the app.
2. Tap the notification icon to view seeded notifications from the backend database.
3. Use the side navigation to move between current ride screens.
4. Choose a city and vehicle type loaded from the backend catalog.
5. Tap `Find Driver` to create a rider order through the Laravel API.
6. In the browser Driver flow, accept/counter the ride; confirm the actual offer appears in Expo without a restart.
7. Choose the offer, open private chat, and verify driver GPS events move the tracking coordinate.
8. Complete the ride from the Driver flow, then submit the rating in Rider.

## Current Limitations

- No production navigation stack yet; MVP uses local tab state.
- Google Maps SDK support is prepared for native builds and the Rider map subscribes to live GPS. Directions routes and Driver native background tracking are still pending.
- Local Reverb/Echo delivery is configured. Production still needs TLS, an exact origin allow-list, supervision/scaling, and physical-device validation.
- Notifications are in-app and database-backed, but not realtime/push yet.
- No document upload/verification screens yet.
- No native Stripe PaymentSheet/provider UI yet; only backend PaymentIntent/webhook support exists.
- Text chat is implemented; attachments, delivery/read receipts, and push alerts are not.
- Offers, choose-driver, cancellation, rating, chat, and GPS subscriptions use the backend. Some screen navigation remains local MVP state.
- The Expo SDK 52 dependency tree currently reports audit advisories; upgrade Expo as a dedicated tested change rather than using `npm audit fix --force`.
- Store-ready metadata is still pending.

## Next Recommended Mobile Tasks

1. Add React Navigation with role-based stacks.
2. Add persistent auth guard and profile screen.
3. Replace placeholder pickup/drop-off values with real ride request forms.
4. Add map pickup/drop-off selection.
5. Add the native Stripe/local-provider payment screen after the Morocco provider decision.
6. Add push notifications and chat delivery/read receipts/attachments.
7. Add Driver/Concierge role stacks and driver background GPS.
8. Add driver verification upload screens.
9. Upgrade Expo SDK/dependencies and add device E2E tests.
10. Add store-ready metadata and platform-specific screenshots.
