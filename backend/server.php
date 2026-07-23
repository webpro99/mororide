<?php

/*
|--------------------------------------------------------------------------
| Built-in server router
|--------------------------------------------------------------------------
|
| Used by the Docker `app` container via `php -S 0.0.0.0:8000 -t public server.php`.
|
| Unlike `php artisan serve`, the raw PHP built-in server does NOT strip the
| container's environment variables, so the docker-compose DB_HOST=postgres /
| REDIS_HOST=redis overrides take effect for HTTP requests too. `.env` is left
| untouched, so running locally without Docker (`php artisan serve`) still works.
|
*/

$publicPath = __DIR__.'/public';

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? ''
);

// Serve real files (assets, etc.) directly from public/.
if ($uri !== '/' && file_exists($publicPath.$uri)) {
    return false;
}

require_once $publicPath.'/index.php';
