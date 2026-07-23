# MoroRide — Production Realtime (Laravel Reverb) Deployment

This guide covers running the WebSocket realtime layer (Laravel Reverb) in
production behind TLS, with process supervision. Realtime powers live driver
offers, ride status, GPS tracking, and chat. It requires a **long-running
process**, so it cannot run on FTP-only shared hosting — use a VPS (or a small
VPS just for Reverb while the HTTP API stays elsewhere).

> Push notifications (Expo) do **not** need Reverb — they are delivered over
> HTTPS to Expo's push service and work anywhere the API can make outbound
> requests. Reverb is only for in-app live updates.

---

## 1. Architecture

```
                        ┌────────────────────────────┐
   mobile app  ─wss──▶  │ Nginx (TLS) reverse proxy  │ ──▶ Reverb :8080 (127.0.0.1)
   mobile app  ─https─▶ │ ws.mororide.com / :443     │ ──▶ PHP-FPM (API) :9000
                        └────────────────────────────┘
   Laravel API ─http──▶ Reverb :8080 (publishes events after DB commit)
```

- The API publishes events (`ShouldBroadcastNow`) directly to Reverb over the
  loopback interface.
- Clients connect to Reverb through Nginx over `wss://` (TLS terminated at Nginx).

---

## 2. Environment (`.env`)

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.mororide.com

BROADCAST_DRIVER=reverb

# Secrets — generate strong random values, never reuse the local defaults.
REVERB_APP_ID=mororide-prod
REVERB_APP_KEY=<random-key>
REVERB_APP_SECRET=<random-secret>

# Where the API publishes events (loopback on the same host).
REVERB_HOST=127.0.0.1
REVERB_PORT=8080
REVERB_SCHEME=http

# Where the Reverb process binds.
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8080

# EXACT allow-list of origins — never "*" in production.
REVERB_ALLOWED_ORIGINS=https://app.mororide.com,https://admin.mororide.com
```

The mobile app connects with the **public** TLS host, not the loopback:

```dotenv
EXPO_PUBLIC_REVERB_HOST=ws.mororide.com
EXPO_PUBLIC_REVERB_PORT=443
EXPO_PUBLIC_REVERB_SCHEME=https
EXPO_PUBLIC_REVERB_APP_KEY=<random-key>   # the public app key (not the secret)
```

---

## 3. Nginx — TLS reverse proxy for the WebSocket

```nginx
server {
    listen 443 ssl http2;
    server_name ws.mororide.com;

    ssl_certificate     /etc/letsencrypt/live/ws.mororide.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/ws.mororide.com/privkey.pem;

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "Upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 3600s;   # keep long-lived sockets open
        proxy_send_timeout 3600s;
    }
}
```

Get the certificate with `certbot --nginx -d ws.mororide.com`.

---

## 4. Supervisor — keep Reverb (and the queue) running

`/etc/supervisor/conf.d/mororide-reverb.conf`:

```ini
[program:mororide-reverb]
process_name=%(program_name)s
command=php /var/www/mororide/backend/artisan reverb:start --host=127.0.0.1 --port=8080
directory=/var/www/mororide/backend
autostart=true
autorestart=true
user=www-data
stopwaitsecs=10
stdout_logfile=/var/log/mororide/reverb.log
stderr_logfile=/var/log/mororide/reverb.err.log
```

The scheduler (order expiry) needs a cron entry — one line:

```cron
* * * * * cd /var/www/mororide/backend && php artisan schedule:run >> /dev/null 2>&1
```

> There are **no queue jobs** in this app and broadcasts are `ShouldBroadcastNow`,
> so a `queue:work` supervisor is optional today. Add one only when you introduce
> queued jobs; if you do, also switch `QUEUE_CONNECTION` off `sync`.

Apply:

```bash
sudo mkdir -p /var/log/mororide
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl status mororide-reverb
```

---

## 5. PHP requirement for graceful restarts

Reverb needs the `pcntl` extension for clean signal handling on restart/deploy
(already enabled in `backend/Dockerfile`). On a bare VPS install it with your
PHP version, e.g. `sudo apt install php8.3-pcntl`.

---

## 6. Verify

```bash
# Reverb is listening on loopback
ss -ltnp | grep 8080

# TLS handshake + upgrade through Nginx (should not error)
curl -i -N -H "Connection: Upgrade" -H "Upgrade: websocket" https://ws.mororide.com/app/<REVERB_APP_KEY>

# Supervisor keeps it alive
sudo supervisorctl status mororide-reverb   # RUNNING
```

From the app: create a rider order, take a driver online in the same city, and
confirm the offer/status/GPS/chat update live without a manual refresh.

---

## 7. Scaling notes

- Reverb is single-process; for higher load run multiple Reverb instances behind
  a load balancer with **sticky sessions** and a shared Redis pub/sub
  (`REVERB_SCALING_ENABLED=true`, Redis-backed), then set `CACHE_DRIVER`/
  `QUEUE_CONNECTION` to Redis too.
- Monitor connection count and memory; restart on deploy with
  `supervisorctl restart mororide-reverb` (pcntl makes this graceful).
- Keep `REVERB_ALLOWED_ORIGINS` tight and rotate `REVERB_APP_SECRET` if leaked.
