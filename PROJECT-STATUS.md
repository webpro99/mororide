# MoroRide Release Status

Last updated: 2026-07-21

## Current release target

- Domain: `https://mororide.com`
- API: `https://mororide.com/api`
- Admin: `https://mororide.com/admin/login`
- Stripe webhook: `https://mororide.com/api/webhooks/stripe`
- Mobile: Expo React Native source included; production Android APK is the next phase after server validation

## Completed

- Laravel API, Sanctum roles, PostgreSQL/MySQL migrations and production seeder
- Rider, Driver and Concierge order lifecycle
- Admin console, verification, wallets, points, payouts, fares and audit logs
- Driver point purchase pricing controlled by Admin
- USD Stripe PaymentIntents, signed webhooks and ledger-only-on-success crediting
- Reverb private realtime channels, chat, driver locations and notifications
- One-time production installer with domain/database/admin configuration
- Production Nginx, Supervisor and cron templates
- Automated verification: 80 tests / 585 assertions; point/payment batch additionally passed 15 tests / 122 assertions

## Required before APK production test

- Install this server release on the VPS and enable HTTPS
- Verify PostgreSQL/MySQL, Redis, worker, scheduler and Reverb under Supervisor
- Configure a persistent Stripe production/test webhook endpoint (not the temporary CLI listener)
- Configure object/public storage and backup policy
- Configure Android FCM/Expo push credentials
- Build Android with the production API/Reverb values from `INSTALL-MORORIDE.md`
- Run physical-device rider/driver/concierge and PaymentSheet journeys

## Known caveats

- Stripe live availability requires a legally supported business entity/bank account.
- Shared-hosting profile disables realtime transport and background queues; VPS is required for the intended production experience.
- Background GPS and production push delivery still require a native Android build and device validation.
- Never copy the local development `.env` into production.
