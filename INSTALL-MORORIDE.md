# MoroRide Server Installation — mororide.com

This release contains the Laravel API/admin/website, database migrations and production seed data, realtime server configuration, deployment templates, and the Expo mobile source used for the later Android APK.

## Server requirements

- Ubuntu VPS recommended, PHP 8.3 with `curl`, `fileinfo`, `mbstring`, `openssl`, `pdo_pgsql` or `pdo_mysql`, `pcntl`, and Redis support
- PostgreSQL 15+ (recommended) or MySQL 8+
- Redis 7+, Nginx, Supervisor, and a valid TLS certificate
- Domain DNS for `mororide.com` and `www.mororide.com` pointing to the VPS
- Web root must be `backend/public`, never the project or `backend` directory

## Installation

1. Upload/extract the release to `/var/www/mororide`.
2. Create an empty database and a database user with full privileges on it.
3. Ensure these paths are writable by the PHP user:

   ```bash
   chown -R www-data:www-data /var/www/mororide/backend/storage /var/www/mororide/backend/bootstrap/cache
   chmod -R ug+rwX /var/www/mororide/backend/storage /var/www/mororide/backend/bootstrap/cache
   ```

4. Copy `deployment/nginx-mororide.conf` to Nginx, issue the TLS certificate, and reload Nginx.
5. Open `https://mororide.com/install` and enter:
   - Domain: `https://mororide.com`
   - VPS profile
   - PostgreSQL or MySQL credentials
   - The first administrator account
6. The installer writes `.env`, generates application/Reverb secrets, runs every migration, seeds required cities/fares/settings, links public storage, creates the admin, and writes `storage/app/installed.lock`.
7. Install the Supervisor and cron templates from `deployment/`, then start them.
8. Open `https://mororide.com/admin/login` and configure Stripe Test mode under **Payment Settings**. Register the production webhook URL `https://mororide.com/api/webhooks/stripe`.

## Verification

```bash
cd /var/www/mororide/backend
php artisan about
php artisan migrate:status
php artisan test
php artisan storage:link
curl -I https://mororide.com/
curl -I https://mororide.com/admin/login
```

The installer is deliberately locked after success. Do not delete `storage/app/installed.lock` on a live installation. Never include `.env`, Stripe secret keys, database passwords, or production user data in backups shared with developers.

## Android build values (next phase)

The APK must be built with:

```dotenv
EXPO_PUBLIC_API_BASE_URL=https://mororide.com/api
EXPO_PUBLIC_REVERB_HOST=mororide.com
EXPO_PUBLIC_REVERB_PORT=443
EXPO_PUBLIC_REVERB_SCHEME=https
EXPO_PUBLIC_REVERB_APP_KEY=<value generated in backend/.env>
```

Do not build the production APK until HTTPS, `/api`, Reverb, Stripe webhooks, uploads, queues, and push credentials have been tested on the installed server.

