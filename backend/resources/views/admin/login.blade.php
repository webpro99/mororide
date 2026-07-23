<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MoroRide Admin — Sign in</title>
    <style>
        :root {
            --navy: #082443; --navy-2:#0e3358; --ink: #14253f; --muted: #607086;
            --line: #e6e0d6; --paper: #fffaf4; --gold: #d99d59; --green: #27845e; --red: #b84747;
        }
        * { box-sizing: border-box; }
        html, body { height: 100%; }
        body {
            margin: 0; color: var(--ink);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            display: grid; grid-template-columns: 1.05fr 1fr; min-height: 100vh; background: var(--paper);
        }
        .brandpane {
            position: relative; overflow: hidden; color: #fff; padding: 52px 56px;
            display: flex; flex-direction: column; justify-content: space-between;
            background:
                radial-gradient(900px 500px at 15% 5%, rgba(217,157,89,.35), transparent 60%),
                radial-gradient(700px 600px at 90% 100%, rgba(37,107,179,.35), transparent 55%),
                linear-gradient(150deg, var(--navy), var(--navy-2));
        }
        .brandpane::after {
            content:""; position:absolute; inset:0; opacity:.09;
            background-image: repeating-linear-gradient(45deg, #fff 0 2px, transparent 2px 22px);
            pointer-events:none;
        }
        .logo { display:flex; align-items:center; gap:12px; font-weight:900; font-size:22px; letter-spacing:.3px; position:relative; z-index:1; }
        .logo .mark {
            width:40px; height:40px; border-radius:12px; display:grid; place-items:center; font-size:20px;
            background: linear-gradient(160deg, var(--gold), #b97e3f); box-shadow: 0 8px 22px rgba(217,157,89,.4);
        }
        .logo small { display:block; font-size:11px; font-weight:700; letter-spacing:2px; color: #f2d9b8; text-transform:uppercase; }
        .pitch { position:relative; z-index:1; max-width: 420px; }
        .pitch h1 { font-size: 34px; line-height:1.15; margin:0 0 14px; font-weight:900; }
        .pitch p { color:#c9d6e6; font-size:15px; line-height:1.6; margin:0 0 24px; }
        .pitch ul { list-style:none; margin:0; padding:0; display:grid; gap:10px; color:#dfe8f3; font-size:14px; }
        .pitch li { display:flex; align-items:center; gap:10px; }
        .pitch li b { color:#fff; }
        .dot { width:8px; height:8px; border-radius:50%; background: var(--gold); flex:none; }
        .brandfoot { position:relative; z-index:1; color:#8ea3bd; font-size:13px; }

        .formpane { display:flex; align-items:center; justify-content:center; padding: 32px; }
        .card { width: min(400px, 100%); }
        .card h2 { margin:0 0 6px; font-size: 26px; font-weight:900; color: var(--navy); }
        .card .sub { margin:0 0 26px; color: var(--muted); font-size:14px; }
        label { display:block; font-size:13px; font-weight:700; color:var(--ink); margin: 0 0 6px; }
        .field { margin-bottom:18px; }
        input {
            width:100%; padding: 13px 14px; border:1px solid var(--line); border-radius:12px; font-size:15px;
            background:#fff; color:var(--ink); transition: border-color .15s, box-shadow .15s;
        }
        input:focus { outline:none; border-color: var(--gold); box-shadow: 0 0 0 4px rgba(217,157,89,.16); }
        button {
            width:100%; padding: 14px; border:0; border-radius:12px; cursor:pointer; font-size:15px; font-weight:800; color:#fff;
            background: linear-gradient(160deg, var(--navy), var(--navy-2)); box-shadow: 0 10px 24px rgba(8,36,67,.28); transition: transform .05s, opacity .15s;
        }
        button:hover { opacity:.95; }
        button:active { transform: translateY(1px); }
        button[disabled] { opacity:.6; cursor:progress; }
        .err {
            display:none; background: #fbecec; color: var(--red); border:1px solid #f0cfcf;
            padding: 11px 13px; border-radius:10px; font-size:13.5px; margin-bottom:16px; font-weight:600;
        }
        .hint { margin-top:18px; color: var(--muted); font-size:12.5px; text-align:center; }
        .hint code { background:#f1ece3; padding:2px 6px; border-radius:6px; }
        @media (max-width: 860px) {
            body { grid-template-columns: 1fr; }
            .brandpane { display:none; }
        }
    </style>
</head>
<body>
    <aside class="brandpane">
        <div class="logo"><span class="mark">🚕</span><span>MoroRide<small>Admin console</small></span></div>
        <div class="pitch">
            <h1>Run the whole marketplace from one place.</h1>
            <p>Verify drivers, watch live rides, manage wallets and payouts, review revenue and every audit trail.</p>
            <ul>
                <li><span class="dot"></span> Driver <b>verification</b> &amp; document review</li>
                <li><span class="dot"></span> Orders, <b>wallets</b> &amp; payouts</li>
                <li><span class="dot"></span> Revenue &amp; full <b>audit log</b></li>
            </ul>
        </div>
        <div class="brandfoot">© {{ date('Y') }} MoroRide — InDrive-style ride marketplace for Morocco.</div>
    </aside>

    <main class="formpane">
        <form class="card" id="loginForm" autocomplete="on">
            <h2>Sign in</h2>
            <p class="sub">Administrator access only.</p>
            <div class="err" id="err"></div>
            <div class="field">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" value="admin@mororide.test" required autofocus>
            </div>
            <div class="field">
                <label for="password">Password</label>
                <input id="password" name="password" type="password" value="password" required>
            </div>
            <button type="submit" id="submit">Sign in</button>
            <p class="hint">Demo account: <code>admin@mororide.test</code> / <code>password</code></p>
        </form>
    </main>

    <script>
        const TOKEN_KEY = 'mororide_admin_token';

        // If a valid admin token already exists, skip straight to the dashboard.
        (async () => {
            const token = localStorage.getItem(TOKEN_KEY);
            if (!token) return;
            try {
                const res = await fetch('/api/me', { headers: { Authorization: `Bearer ${token}`, Accept: 'application/json' } });
                const body = await res.json();
                if (res.ok && body?.data?.role === 'admin') location.replace('/admin');
                else localStorage.removeItem(TOKEN_KEY);
            } catch (_) { /* stay on login */ }
        })();

        const form = document.getElementById('loginForm');
        const errBox = document.getElementById('err');
        const btn = document.getElementById('submit');

        function showError(msg) {
            errBox.textContent = msg;
            errBox.style.display = 'block';
        }

        form.addEventListener('submit', async (event) => {
            event.preventDefault();
            errBox.style.display = 'none';
            btn.disabled = true;
            btn.textContent = 'Signing in…';

            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;

            try {
                const res = await fetch('/api/login', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                    body: JSON.stringify({ email, password }),
                });
                const body = await res.json();

                if (!res.ok) {
                    throw new Error(body?.message || 'Login failed. Check your credentials.');
                }
                if (body?.data?.user?.role !== 'admin' && body?.data?.role !== 'admin') {
                    throw new Error('This account is not an administrator.');
                }

                localStorage.setItem(TOKEN_KEY, body.data.token);
                location.replace('/admin');
            } catch (error) {
                showError(error.message);
                btn.disabled = false;
                btn.textContent = 'Sign in';
            }
        });
    </script>
</body>
</html>
