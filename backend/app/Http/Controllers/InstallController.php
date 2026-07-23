<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * One-time web installer. Lets the operator enter MySQL + admin details in a
 * browser; it tests the connection, writes .env, runs the migrations, seeds the
 * reference data, creates the admin, then locks itself so it cannot run again.
 */
class InstallController extends Controller
{
    private function lockPath(): string
    {
        return storage_path('app/installed.lock');
    }

    private function isLocked(): bool
    {
        return file_exists($this->lockPath());
    }

    public function show()
    {
        if ($this->isLocked()) {
            return view('install.locked');
        }

        return view('install.form');
    }

    public function run(Request $request)
    {
        if ($this->isLocked()) {
            return redirect('/install');
        }

        $data = $request->validate([
            'app_url' => ['required', 'url'],
            'deployment_profile' => ['required', Rule::in(['vps', 'shared'])],
            'db_connection' => ['required', Rule::in(['mysql', 'pgsql'])],
            'db_host' => ['required', 'string', 'max:255'],
            'db_port' => ['nullable', 'string', 'max:10'],
            'db_database' => ['required', 'string', 'max:255'],
            'db_username' => ['required', 'string', 'max:255'],
            'db_password' => ['nullable', 'string', 'max:255'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255'],
            'admin_password' => ['required', 'string', 'min:10', 'max:255', 'confirmed'],
        ]);

        $connection = $data['db_connection'];
        $port = $data['db_port'] ?: ($connection === 'pgsql' ? '5432' : '3306');
        $password = $data['db_password'] ?? '';
        $appUrl = rtrim($data['app_url'], '/');
        $appHost = (string) parse_url($appUrl, PHP_URL_HOST);
        $isVps = $data['deployment_profile'] === 'vps';

        // 1) Verify the selected database connection with the supplied credentials.
        config([
            "database.connections.{$connection}.host" => $data['db_host'],
            "database.connections.{$connection}.port" => $port,
            "database.connections.{$connection}.database" => $data['db_database'],
            "database.connections.{$connection}.username" => $data['db_username'],
            "database.connections.{$connection}.password" => $password,
            'database.default' => $connection,
        ]);
        DB::purge($connection);

        try {
            DB::connection($connection)->getPdo();
        } catch (Throwable $e) {
            return back()->withInput()->with('error', 'Could not connect to the database: '.$e->getMessage());
        }

        // 2) Ensure an APP_KEY, then persist configuration to .env.
        $appKey = (string) config('app.key');
        if ($appKey === '') {
            $appKey = 'base64:'.base64_encode(random_bytes(32));
        }
        config(['app.key' => $appKey]);

        try {
            $this->writeEnv([
                'APP_ENV' => 'production',
                'APP_DEBUG' => 'false',
                'APP_URL' => $appUrl,
                'APP_KEY' => $appKey,
                'DB_CONNECTION' => $connection,
                'DB_HOST' => $data['db_host'],
                'DB_PORT' => $port,
                'DB_DATABASE' => $data['db_database'],
                'DB_USERNAME' => $data['db_username'],
                'DB_PASSWORD' => $password,
                'CACHE_DRIVER' => $isVps ? 'redis' : 'file',
                'SESSION_DRIVER' => 'file',
                'QUEUE_CONNECTION' => $isVps ? 'redis' : 'sync',
                'BROADCAST_DRIVER' => $isVps ? 'reverb' : 'log',
                'FILESYSTEM_DISK' => 'public',
                'SESSION_SECURE_COOKIE' => str_starts_with($appUrl, 'https://') ? 'true' : 'false',
                'SANCTUM_STATEFUL_DOMAINS' => $appHost.',www.'.$appHost,
                'REVERB_APP_ID' => 'mororide-production',
                'REVERB_APP_KEY' => bin2hex(random_bytes(16)),
                'REVERB_APP_SECRET' => bin2hex(random_bytes(32)),
                'REVERB_HOST' => $appHost,
                'REVERB_PORT' => '443',
                'REVERB_SCHEME' => 'https',
                'REVERB_SERVER_HOST' => '0.0.0.0',
                'REVERB_SERVER_PORT' => '8080',
                'REVERB_ALLOWED_ORIGINS' => $appUrl.',https://www.'.$appHost,
                'STRIPE_ENABLED' => 'false',
                'STRIPE_MODE' => 'test',
                'STRIPE_CURRENCY' => 'usd',
                'STRIPE_POINTS_MIN_TOPUP' => '20',
                'STRIPE_POINTS_MAX_TOPUP' => '5000',
                'STRIPE_POINTS_PER_CURRENCY_UNIT' => '0.66666667',
                'MAIL_FROM_ADDRESS' => 'support@'.$appHost,
            ]);
        } catch (Throwable $e) {
            return back()->withInput()->with('error', 'Could not write the .env file (check permissions): '.$e->getMessage());
        }

        // 3) Build the schema and seed reference data.
        try {
            Artisan::call('migrate', ['--force' => true]);
            Artisan::call('db:seed', ['--class' => \Database\Seeders\ProductionSeeder::class, '--force' => true]);
            Artisan::call('storage:link');
        } catch (Throwable $e) {
            return back()->withInput()->with('error', 'Migration failed: '.$e->getMessage());
        }

        // 4) Create (or update) the single admin account.
        $admin = User::updateOrCreate(
            ['email' => $data['admin_email']],
            [
                'name' => $data['admin_name'],
                'role' => 'admin',
                'status' => 'active',
                'password' => Hash::make($data['admin_password']),
            ]
        );

        // 5) Lock the installer and clear any stale cached config.
        if (file_put_contents($this->lockPath(), 'installed at '.now()->toDateTimeString().PHP_EOL) === false) {
            return back()->with('error', 'Installation succeeded, but the installer lock could not be written. Fix storage/app permissions immediately.');
        }
        Artisan::call('config:clear');

        return redirect('/install')->with('done', [
            'admin_email' => $admin->email,
            'app_url' => $appUrl,
            'deployment_profile' => $data['deployment_profile'],
        ]);
    }

    /**
     * Update (or append) keys in the project .env file.
     *
     * @param  array<string, string>  $values
     */
    private function writeEnv(array $values): void
    {
        $path = base_path('.env');
        if (! file_exists($path)) {
            $example = base_path('.env.example');
            copy(file_exists($example) ? $example : $path, $path);
        }

        $contents = file_get_contents($path) ?: '';

        foreach ($values as $key => $value) {
            $line = $key.'='.$this->envQuote($value);
            if (preg_match('/^'.preg_quote($key, '/').'=.*$/m', $contents)) {
                $contents = preg_replace('/^'.preg_quote($key, '/').'=.*$/m', $line, $contents);
            } else {
                $contents .= PHP_EOL.$line;
            }
        }

        file_put_contents($path, $contents);
    }

    private function envQuote(string $value): string
    {
        if ($value === '') {
            return '""';
        }

        if (preg_match('/[\s"\'\\\\#$]/', $value)) {
            return '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $value).'"';
        }

        return $value;
    }
}
