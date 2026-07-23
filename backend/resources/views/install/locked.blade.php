<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>MoroRide — Already Installed</title>
  <style>
    body { margin:0; font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; background:#fff8ef; color:#09223d; }
    .wrap { max-width: 520px; margin: 12vh auto 0; padding: 0 18px; text-align:center; }
    .card { background:#fff; border:1px solid #ead9c5; border-radius:18px; padding: 32px 24px; box-shadow:0 12px 30px rgba(11,42,72,.08); }
    .badge { width:64px; height:64px; border-radius:999px; background:#eef2f6; color:#66788a; display:flex; align-items:center; justify-content:center; font-size:30px; margin:0 auto 14px; }
    h1 { font-size:20px; margin:0 0 6px; color:#082844; }
    p { color:#66788a; font-size:14px; margin:6px 0; }
    a { color:#b66a45; font-weight:800; text-decoration:none; }
    code { background:#fff8ef; padding:2px 6px; border-radius:6px; border:1px solid #ead9c5; }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <div class="badge">&#128274;</div>
      <h1>MoroRide is already installed</h1>
      <p>The installer has been locked to protect your data.</p>
      <p><a href="/admin/login">Go to the admin console &rarr;</a></p>
      <p style="margin-top:18px">To re-run setup, delete <code>storage/app/installed.lock</code> on the server.</p>
    </div>
  </div>
</body>
</html>
