# MoroRide Server Release Manifest

Release: `2026.07.21-rc1`

Included:

- `backend/` — Laravel application, Composer vendor dependencies, migrations, production seeder, assets and tests
- `mobile/` — Expo React Native source and Android native project (dependencies/build output excluded)
- `deployment/` — Nginx, Supervisor and cron templates for `mororide.com`
- `docs/` — realtime and operational documentation
- `INSTALL-MORORIDE.md` — server installation and verification instructions
- `PROJECT-STATUS.md` — handoff status for the later APK phase
- `docker-compose.yml` — local/staging stack

Intentionally excluded:

- All `.env` and `.env.local` files
- Stripe/API/database secrets
- Runtime logs, sessions, caches and uploaded customer data
- Mobile `node_modules`, Expo caches and compiled build output
- Git metadata and unrelated design/source archives

The database is delivered as Laravel migrations plus `ProductionSeeder`; the installer creates a clean schema without demo users or development data.

