<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>MoroRide — Setup</title>
  <style>
    :root { --navy:#082844; --gold:#d89a57; --rust:#b66a45; --line:#ead9c5; --ink:#09223d; --muted:#66788a; --sand:#fff8ef; }
    * { box-sizing: border-box; }
    body { margin:0; font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; background: var(--sand); color: var(--ink); }
    .wrap { max-width: 620px; margin: 0 auto; padding: 32px 18px 60px; }
    .brand { text-align:center; margin-bottom: 22px; }
    .brand h1 { margin: 8px 0 2px; font-size: 26px; color: var(--navy); }
    .brand p { margin: 0; color: var(--muted); font-size: 14px; }
    .card { background:#fff; border:1px solid var(--line); border-radius: 18px; padding: 22px; box-shadow: 0 12px 30px rgba(11,42,72,.08); }
    h2 { font-size: 16px; margin: 0 0 4px; color: var(--navy); }
    .hint { color: var(--muted); font-size: 13px; margin: 0 0 16px; }
    label { display:block; font-size: 13px; font-weight: 700; color: var(--navy); margin: 14px 0 6px; }
    input, select { width:100%; padding: 11px 12px; border:1px solid var(--line); border-radius: 10px; font-size: 15px; color: var(--ink); background:#fff; }
    .row { display:flex; gap: 12px; }
    .row > div { flex:1; }
    .section { margin-top: 6px; padding-top: 14px; border-top: 1px dashed var(--line); }
    .section:first-of-type { border-top: none; padding-top: 0; }
    button { margin-top: 22px; width:100%; padding: 14px; background: var(--navy); color:#fff; border:none; border-radius: 12px; font-size: 16px; font-weight: 800; cursor: pointer; }
    button:hover { background:#0a3057; }
    .err { background:#fff0ee; border:1px solid #f3c9c1; color:#a23b2c; padding: 12px 14px; border-radius: 10px; font-size: 14px; margin-bottom: 16px; white-space: pre-wrap; }
    .ok { text-align:center; }
    .ok .badge { width:64px; height:64px; border-radius:999px; background:#e7f3ed; color:#2f8f57; display:flex; align-items:center; justify-content:center; font-size:34px; margin: 0 auto 14px; }
    .ok a { display:inline-block; margin-top: 8px; color: var(--rust); font-weight:800; text-decoration:none; }
    .links { text-align:left; background: var(--sand); border:1px solid var(--line); border-radius: 12px; padding: 14px 16px; margin-top: 16px; }
    .links code { background:#fff; padding:2px 6px; border-radius:6px; border:1px solid var(--line); }
    .warn { color:var(--muted); font-size:12px; margin-top:16px; }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="brand">
      <h1>MoroRide Setup</h1>
      <p>Connect your MySQL database and create the tables</p>
    </div>

    @if (session('done'))
      @php $done = session('done'); @endphp
      <div class="card ok">
        <div class="badge">&#10003;</div>
        <h2>Installation complete</h2>
        <p class="hint">Your database tables were created and the admin account is ready.</p>
        <div class="links">
          <div>Admin console: <a href="{{ $done['app_url'] }}/admin/login">{{ $done['app_url'] }}/admin/login</a></div>
          <div style="margin-top:8px">Admin email: <code>{{ $done['admin_email'] }}</code></div>
          <div style="margin-top:8px">Mobile API base URL: <code>{{ $done['app_url'] }}/api</code></div>
          <div style="margin-top:8px">Deployment profile: <code>{{ strtoupper($done['deployment_profile']) }}</code></div>
        </div>
        <p class="warn">The installer is now locked. To run it again, delete <code>storage/app/installed.lock</code> on the server. For safety, you can also remove the <code>/install</code> routes.</p>
      </div>
    @else
      <form class="card" method="POST" action="/install">
        @csrf
        @if (session('error'))
          <div class="err">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
          <div class="err">{{ implode("\n", $errors->all()) }}</div>
        @endif

        <div class="section">
          <h2>Site</h2>
          <p class="hint">The public HTTPS address of this backend.</p>
          <label>App URL</label>
          <input name="app_url" type="url" value="{{ old('app_url', 'https://mororide.com') }}" required>
          <label>Deployment profile</label>
          <select name="deployment_profile" required>
            <option value="vps" @selected(old('deployment_profile', 'vps') === 'vps')>VPS / cloud — Redis, queues and Reverb realtime</option>
            <option value="shared" @selected(old('deployment_profile') === 'shared')>Shared hosting — file cache and synchronous queue</option>
          </select>
        </div>

        <div class="section">
          <h2>Database</h2>
          <p class="hint">Create an empty database and user first. All tables and initial reference data are created automatically.</p>
          <label>Database engine</label>
          <select name="db_connection" id="dbConnection" onchange="setDefaultPort()" required>
            <option value="pgsql" @selected(old('db_connection', 'pgsql') === 'pgsql')>PostgreSQL (recommended)</option>
            <option value="mysql" @selected(old('db_connection') === 'mysql')>MySQL / MariaDB</option>
          </select>
          <div class="row">
            <div>
              <label>DB host</label>
              <input name="db_host" value="{{ old('db_host', 'localhost') }}" required>
            </div>
            <div>
              <label>Port</label>
              <input name="db_port" id="dbPort" value="{{ old('db_port', '5432') }}">
            </div>
          </div>
          <label>Database name</label>
          <input name="db_database" value="{{ old('db_database') }}" placeholder="cpuser_mororide" required>
          <label>Database user</label>
          <input name="db_username" value="{{ old('db_username') }}" placeholder="cpuser_mororide" required>
          <label>Database password</label>
          <input name="db_password" type="password" value="{{ old('db_password') }}">
        </div>

        <div class="section">
          <h2>Admin account</h2>
          <p class="hint">The owner login for the admin console.</p>
          <label>Full name</label>
          <input name="admin_name" value="{{ old('admin_name') }}" placeholder="MoroRide Admin" required>
          <label>Email</label>
          <input name="admin_email" type="email" value="{{ old('admin_email') }}" placeholder="you@mororide.com" required>
          <label>Password (minimum 10 characters)</label>
          <input name="admin_password" type="password" required>
          <label>Confirm password</label>
          <input name="admin_password_confirmation" type="password" required>
        </div>

        <button type="submit">Create tables &amp; install</button>
        <p class="warn">This writes production settings to <code>.env</code>, runs migrations, seeds cities/fares/settings, links public storage, creates your admin, and permanently locks the installer.</p>
      </form>
    @endif
  </div>
  <script>
    function setDefaultPort() {
      const driver = document.getElementById('dbConnection').value;
      document.getElementById('dbPort').value = driver === 'pgsql' ? '5432' : '3306';
    }
  </script>
</body>
</html>
