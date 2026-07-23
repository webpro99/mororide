<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MoroRide Test App</title>
    <style>
        :root {
            --navy: #082443;
            --ink: #14253f;
            --muted: #607086;
            --line: #e4d8ca;
            --paper: #fffaf4;
            --gold: #d99d59;
            --green: #27845e;
            --red: #b84747;
            --blue: #256bb3;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            color: var(--ink);
            background: linear-gradient(135deg, #f7fbff, #fffaf4);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        a { color: inherit; text-decoration: none; }

        .topbar {
            position: sticky;
            top: 0;
            z-index: 10;
            background: rgba(255,250,244,.94);
            border-bottom: 1px solid var(--line);
            backdrop-filter: blur(14px);
        }

        .wrap {
            width: min(1180px, calc(100% - 32px));
            margin: 0 auto;
        }

        .topbar-inner {
            min-height: 68px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
        }

        .brand {
            color: var(--navy);
            font-weight: 900;
            font-size: 20px;
        }

        .nav {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .nav a,
        .btn {
            min-height: 38px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 0 14px;
            border: 1px solid rgba(8,36,67,.14);
            border-radius: 7px;
            background: rgba(255,255,255,.78);
            color: var(--navy);
            font-size: 13px;
            font-weight: 850;
            cursor: pointer;
        }

        .nav a.active,
        .btn.primary {
            color: #fff;
            background: var(--navy);
            border-color: var(--navy);
        }

        .btn.gold {
            color: #fff;
            background: var(--gold);
            border-color: var(--gold);
        }

        .btn.danger {
            color: #fff;
            background: var(--red);
            border-color: var(--red);
        }

        main {
            padding: 28px 0 48px;
        }

        .hero {
            display: grid;
            gap: 10px;
            margin-bottom: 22px;
        }

        h1, h2, h3 { margin: 0; color: var(--navy); }

        h1 {
            font-family: Georgia, "Times New Roman", serif;
            font-size: clamp(32px, 5vw, 54px);
            font-weight: 500;
            line-height: 1;
        }

        .muted {
            color: var(--muted);
            line-height: 1.55;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(12, 1fr);
            gap: 16px;
        }

        .card {
            border: 1px solid var(--line);
            border-radius: 10px;
            background: rgba(255,255,255,.86);
            box-shadow: 0 18px 42px rgba(8,36,67,.07);
        }

        .card.pad { padding: 18px; }
        .span-4 { grid-column: span 4; }
        .span-5 { grid-column: span 5; }
        .span-6 { grid-column: span 6; }
        .span-7 { grid-column: span 7; }
        .span-8 { grid-column: span 8; }
        .span-12 { grid-column: span 12; }

        .form {
            display: grid;
            gap: 12px;
        }

        .fields {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
        }

        label {
            display: grid;
            gap: 5px;
            color: #2d4059;
            font-size: 12px;
            font-weight: 850;
        }

        input, select {
            width: 100%;
            min-height: 40px;
            border: 1px solid #d9cdbf;
            border-radius: 7px;
            padding: 0 10px;
            color: var(--ink);
            background: #fff;
            font: inherit;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            align-items: center;
        }

        .status {
            min-height: 42px;
            display: grid;
            align-items: center;
            margin: 12px 0 0;
            padding: 10px 12px;
            border-radius: 8px;
            color: var(--muted);
            background: #f5f8fb;
            border: 1px solid #e0e8f0;
            font-size: 13px;
            white-space: pre-wrap;
        }

        .status.ok {
            color: #185b42;
            background: #edf8f2;
            border-color: #ccebdc;
        }

        .status.err {
            color: #8b2727;
            background: #fff0f0;
            border-color: #f1caca;
        }

        .list {
            display: grid;
            gap: 10px;
            margin-top: 12px;
        }

        .item {
            display: grid;
            gap: 8px;
            padding: 12px;
            border: 1px solid #e5dbd0;
            border-radius: 8px;
            background: #fff;
        }

        .item-head {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            align-items: flex-start;
        }

        .pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 24px;
            padding: 0 8px;
            border-radius: 999px;
            color: var(--navy);
            background: #edf3fb;
            font-size: 11px;
            font-weight: 900;
        }

        .pill.done { color: #185b42; background: #e7f6ee; }
        .pill.money { color: #83511f; background: #fff0d9; }

        .metric-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 10px;
            margin-top: 12px;
        }

        .metric {
            padding: 12px;
            border: 1px solid #e7dcca;
            border-radius: 8px;
            background: #fff;
        }

        .metric strong {
            display: block;
            color: var(--navy);
            font-size: 24px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
            overflow: hidden;
            border-radius: 8px;
            background: #fff;
            font-size: 13px;
        }

        th, td {
            padding: 10px;
            border-bottom: 1px solid #eee5dc;
            text-align: left;
        }

        th {
            color: var(--navy);
            background: #f7f4ef;
            font-size: 11px;
            text-transform: uppercase;
        }

        @media (max-width: 900px) {
            .span-4, .span-5, .span-6, .span-7, .span-8 { grid-column: span 12; }
            .fields, .metric-grid { grid-template-columns: 1fr; }
            .topbar-inner { align-items: flex-start; flex-direction: column; padding: 14px 0; }
        }
    </style>
</head>
<body>
<nav class="topbar">
    <div class="wrap topbar-inner">
        <a class="brand" href="/">MoroRide</a>
        <div class="nav">
            <a href="/app" class="{{ $screen === 'home' ? 'active' : '' }}">App Home</a>
            <a href="/app/rider" class="{{ $screen === 'rider' ? 'active' : '' }}">Rider</a>
            <a href="/app/concierge" class="{{ $screen === 'concierge' ? 'active' : '' }}">Concierge</a>
            <a href="/app/driver" class="{{ $screen === 'driver' ? 'active' : '' }}">Driver</a>
            <a href="/admin">Admin console →</a>
        </div>
    </div>
</nav>

<main class="wrap" data-screen="{{ $screen }}">
    @if($screen === 'home')
        <section class="hero">
            <h1>MoroRide browser test app</h1>
            <p class="muted">Use these pages to test the first marketplace flow from the browser: create an order as rider or concierge, accept and complete it as driver, then inspect dashboard and transactions as admin.</p>
        </section>
        <section class="grid">
            <article class="card pad span-4">
                <h2>Rider</h2>
                <p class="muted">Create a tourist ride request and see your order history.</p>
                <div class="actions"><a class="btn primary" href="/app/rider">Open Rider Page</a></div>
            </article>
            <article class="card pad span-4">
                <h2>Driver</h2>
                <p class="muted">Go online, accept orders, mark arrived, start, and complete rides.</p>
                <div class="actions"><a class="btn primary" href="/app/driver">Open Driver Page</a></div>
            </article>
            <article class="card pad span-4">
                <h2>Admin</h2>
                <p class="muted">View dashboard metrics, live orders, transactions, and revenue.</p>
                <div class="actions"><a class="btn primary" href="/app/admin">Open Admin Page</a></div>
            </article>
            <article class="card pad span-12">
                <h2>Recommended flow</h2>
                <p class="muted">1. Rider creates order. 2. Driver goes online and accepts order. 3. Driver marks arrived, starts, and completes. 4. Admin refreshes dashboard and transactions.</p>
            </article>
        </section>
    @endif

    @if($screen === 'rider' || $screen === 'concierge')
        <section class="hero">
            <h1>{{ $screen === 'rider' ? 'Rider ride request' : 'Concierge guest ride' }}</h1>
            <p class="muted">Quick login with demo credentials, create an order, then switch to Driver to accept it.</p>
        </section>
        <section class="grid">
            <article class="card pad span-5">
                <h2>Session</h2>
                <p class="muted">Demo account: <strong>{{ $screen }}@mororide.test</strong> / password</p>
                <div class="actions">
                    <button class="btn primary" data-login="{{ $screen }}">Quick Login</button>
                    <button class="btn" data-me="{{ $screen }}">Check Session</button>
                    <button class="btn danger" data-clear="{{ $screen }}">Clear Token</button>
                </div>
                <div class="status" id="sessionStatus">Not checked yet.</div>
            </article>
            <article class="card pad span-7">
                <h2>Create order</h2>
                <form class="form" id="orderForm" data-role="{{ $screen }}">
                    <div class="fields">
                        <label>City ID <input name="city_id" type="number" value="2"></label>
                        <label>Payment <select name="payment_method"><option value="cash">cash</option><option value="card">card</option></select></label>
                        <label>Pickup <input name="pickup_address" value="{{ $screen === 'rider' ? 'Jemaa el-Fnaa' : 'Hotel Mamounia' }}"></label>
                        <label>Dropoff <input name="dropoff_address" value="{{ $screen === 'rider' ? 'Majorelle Garden' : 'Marrakech Airport' }}"></label>
                        <label>Distance KM <input name="distance_km" type="number" step="0.1" value="5"></label>
                        <label>ETA minutes <input name="eta_min" type="number" value="18"></label>
                        <label>Passengers <input name="pax" type="number" value="2"></label>
                        <label>Offered fare MAD <input name="offered_fare" type="number" value="140"></label>
                        @if($screen === 'concierge')
                            <label>Guest name <input name="guest_name" value="John & Sarah"></label>
                            <label>Hotel name <input name="hotel_name" value="Demo Riad"></label>
                        @endif
                    </div>
                    <div class="actions">
                        <button class="btn gold" type="submit">Create Order</button>
                        <button class="btn" type="button" data-refresh-orders="{{ $screen }}">Refresh My Orders</button>
                    </div>
                </form>
                <div class="status" id="orderStatus">Ready.</div>
            </article>
            <article class="card pad span-12">
                <h2>My orders</h2>
                <div class="list" id="ordersList"></div>
            </article>
        </section>
    @endif

    @if($screen === 'driver')
        <section class="hero">
            <h1>Driver console</h1>
            <p class="muted">Go online, accept an open order, then move it through arrived, started, and completed.</p>
        </section>
        <section class="grid">
            <article class="card pad span-4">
                <h2>Session</h2>
                <p class="muted">Demo account: <strong>driver@mororide.test</strong> / password</p>
                <div class="actions">
                    <button class="btn primary" data-login="driver">Quick Login</button>
                    <button class="btn" data-me="driver">Check Session</button>
                    <button class="btn danger" data-clear="driver">Clear Token</button>
                </div>
                <div class="status" id="sessionStatus">Not checked yet.</div>
            </article>
            <article class="card pad span-4">
                <h2>Driver status</h2>
                <div class="actions">
                    <button class="btn primary" id="driverOnline">Go Online</button>
                    <button class="btn" id="driverOffline">Go Offline</button>
                    <button class="btn" id="loadWallet">Wallet</button>
                </div>
                <div class="status" id="driverStatus">Ready.</div>
            </article>
            <article class="card pad span-4">
                <h2>Current order</h2>
                <label>Order ID <input id="driverOrderId" placeholder="Order ID"></label>
                <div class="actions" style="margin-top:10px">
                    <button class="btn" data-order-action="arrived">Arrived</button>
                    <button class="btn" data-order-action="start">Start</button>
                    <button class="btn gold" data-order-action="complete">Complete</button>
                </div>
            </article>
            <article class="card pad span-12">
                <div class="actions">
                    <h2 style="margin-right:auto">Open orders</h2>
                    <button class="btn primary" id="loadDriverOrders">Refresh Open Orders</button>
                </div>
                <div class="list" id="driverOrdersList"></div>
            </article>
        </section>
    @endif

    {{-- Admin is now handled by the dedicated console at /admin (login) and /admin. --}}
</main>

<script>
const screen = document.querySelector('main').dataset.screen;
const credentials = {
    rider: ['rider@mororide.test', 'password'],
    concierge: ['concierge@mororide.test', 'password'],
    driver: ['driver@mororide.test', 'password'],
    admin: ['admin@mororide.test', 'password'],
};

function tokenKey(role) {
    return `mororide_token_${role}`;
}

function setStatus(id, message, type = '') {
    const el = document.getElementById(id);
    if (!el) return;
    el.textContent = message;
    el.className = `status ${type}`;
}

function asList(payload) {
    if (!payload) return [];
    if (Array.isArray(payload)) return payload;
    if (Array.isArray(payload.data)) return payload.data;
    if (payload.data && Array.isArray(payload.data.data)) return payload.data.data;
    return [];
}

async function request(role, method, path, body = null) {
    const headers = { Accept: 'application/json' };
    const token = localStorage.getItem(tokenKey(role));
    if (token) headers.Authorization = `Bearer ${token}`;
    if (body) headers['Content-Type'] = 'application/json';

    const response = await fetch(path, {
        method,
        headers,
        body: body ? JSON.stringify(body) : null,
    });

    const payload = await response.json().catch(() => ({}));
    if (!response.ok || payload.success === false) {
        throw new Error(payload.message || `Request failed with ${response.status}`);
    }
    return payload;
}

async function login(role) {
    try {
        const [email, password] = credentials[role];
        const payload = await request(role, 'POST', '/api/login', { email, password });
        localStorage.setItem(tokenKey(role), payload.data.token);
        setStatus('sessionStatus', `Logged in as ${payload.data.user.name} (${payload.data.user.role})`, 'ok');
    } catch (error) {
        setStatus('sessionStatus', error.message, 'err');
    }
}

async function me(role) {
    try {
        const payload = await request(role, 'GET', '/api/me');
        setStatus('sessionStatus', `Active token: ${payload.data.name} (${payload.data.role})`, 'ok');
    } catch (error) {
        setStatus('sessionStatus', `${error.message}. Click Quick Login first.`, 'err');
    }
}

function clearToken(role) {
    localStorage.removeItem(tokenKey(role));
    setStatus('sessionStatus', `Cleared ${role} token.`, 'ok');
}

function orderHtml(order, includeAccept = false) {
    const id = order.id || '';
    const status = order.status || 'unknown';
    const fare = order.final_fare || order.offered_fare || 0;
    return `
        <div class="item">
            <div class="item-head">
                <strong>#${id} ${order.pickup_address || ''} -> ${order.dropoff_address || ''}</strong>
                <span class="pill ${status === 'completed' ? 'done' : ''}">${status}</span>
            </div>
            <div class="muted">${order.distance_km || 0} km · ${order.eta_min || 0} min · ${order.pax || 1} pax · ${order.payment_method || 'cash'}</div>
            <div><span class="pill money">${fare} MAD</span> ${order.assigned_driver_id ? `<span class="pill">driver #${order.assigned_driver_id}</span>` : ''}</div>
            ${includeAccept ? `<div class="actions"><button class="btn primary" data-accept="${id}">Accept Order</button></div>` : ''}
        </div>
    `;
}

async function createOrder(role, form) {
    const data = Object.fromEntries(new FormData(form).entries());
    ['city_id', 'eta_min', 'pax'].forEach((key) => data[key] = parseInt(data[key], 10));
    ['distance_km', 'offered_fare'].forEach((key) => data[key] = parseFloat(data[key]));
    Object.keys(data).forEach((key) => {
        if (data[key] === '' || Number.isNaN(data[key])) delete data[key];
    });

    try {
        const path = role === 'concierge' ? '/api/concierge/orders' : '/api/rider/orders';
        const payload = await request(role, 'POST', path, data);
        setStatus('orderStatus', `Order #${payload.data.id} created. Now open Driver page and accept it.`, 'ok');
        await loadRoleOrders(role);
    } catch (error) {
        setStatus('orderStatus', error.message, 'err');
    }
}

async function loadRoleOrders(role) {
    try {
        const payload = await request(role, 'GET', `/api/${role}/orders`);
        const orders = asList(payload.data);
        document.getElementById('ordersList').innerHTML = orders.length
            ? orders.map((order) => orderHtml(order)).join('')
            : '<p class="muted">No orders yet.</p>';
    } catch (error) {
        document.getElementById('ordersList').innerHTML = `<p class="muted">${error.message}</p>`;
    }
}

async function driverOnline(online) {
    try {
        const payload = await request('driver', 'POST', online ? '/api/driver/online' : '/api/driver/offline');
        setStatus('driverStatus', online ? 'Driver is online.' : 'Driver is offline.', 'ok');
    } catch (error) {
        setStatus('driverStatus', error.message, 'err');
    }
}

async function loadDriverOrders() {
    try {
        const payload = await request('driver', 'GET', '/api/driver/orders');
        const orders = asList(payload.data);
        document.getElementById('driverOrdersList').innerHTML = orders.length
            ? orders.map((order) => orderHtml(order, true)).join('')
            : '<p class="muted">No open orders. Create one from Rider or Concierge first.</p>';
    } catch (error) {
        document.getElementById('driverOrdersList').innerHTML = `<p class="muted">${error.message}</p>`;
    }
}

async function acceptOrder(id) {
    try {
        const payload = await request('driver', 'POST', `/api/driver/orders/${id}/accept`);
        document.getElementById('driverOrderId').value = payload.data.id;
        setStatus('driverStatus', `Accepted order #${payload.data.id}. Use Arrived -> Start -> Complete.`, 'ok');
        await loadDriverOrders();
    } catch (error) {
        setStatus('driverStatus', error.message, 'err');
    }
}

async function driverOrderAction(action) {
    const id = document.getElementById('driverOrderId').value;
    if (!id) return setStatus('driverStatus', 'Enter or accept an order ID first.', 'err');

    try {
        const payload = await request('driver', 'POST', `/api/driver/orders/${id}/${action}`);
        setStatus('driverStatus', `Order #${payload.data.id} moved to ${payload.data.status}.`, 'ok');
    } catch (error) {
        setStatus('driverStatus', error.message, 'err');
    }
}

async function loadWallet() {
    try {
        const payload = await request('driver', 'GET', '/api/driver/wallet');
        setStatus('driverStatus', `Wallet: ${payload.data.wallet_balance} ${payload.data.currency}\nPoints: ${payload.data.points_balance}\nFree rides: ${payload.data.free_rides_remaining}`, 'ok');
    } catch (error) {
        setStatus('driverStatus', error.message, 'err');
    }
}

document.querySelectorAll('[data-login]').forEach((button) => button.addEventListener('click', () => login(button.dataset.login)));
document.querySelectorAll('[data-me]').forEach((button) => button.addEventListener('click', () => me(button.dataset.me)));
document.querySelectorAll('[data-clear]').forEach((button) => button.addEventListener('click', () => clearToken(button.dataset.clear)));

document.getElementById('orderForm')?.addEventListener('submit', (event) => {
    event.preventDefault();
    createOrder(event.currentTarget.dataset.role, event.currentTarget);
});
document.querySelector('[data-refresh-orders]')?.addEventListener('click', (event) => loadRoleOrders(event.currentTarget.dataset.refreshOrders));

document.getElementById('driverOnline')?.addEventListener('click', () => driverOnline(true));
document.getElementById('driverOffline')?.addEventListener('click', () => driverOnline(false));
document.getElementById('loadWallet')?.addEventListener('click', loadWallet);
document.getElementById('loadDriverOrders')?.addEventListener('click', loadDriverOrders);
document.getElementById('driverOrdersList')?.addEventListener('click', (event) => {
    const button = event.target.closest('[data-accept]');
    if (button) acceptOrder(button.dataset.accept);
});
document.querySelectorAll('[data-order-action]').forEach((button) => button.addEventListener('click', () => driverOrderAction(button.dataset.orderAction)));

if (screen === 'rider' || screen === 'concierge') loadRoleOrders(screen);
if (screen === 'driver') loadDriverOrders();
</script>
</body>
</html>
