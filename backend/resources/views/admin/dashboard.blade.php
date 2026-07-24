<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MoroRide Admin</title>
    <style>
        :root {
            --navy:#082443; --navy-2:#0e3358; --ink:#14253f; --muted:#6a7891;
            --line:#e7e2d8; --paper:#f6f4ef; --card:#ffffff; --gold:#d99d59; --gold-d:#b97e3f;
            --green:#27845e; --green-bg:#e6f4ec; --red:#b84747; --red-bg:#fbecec;
            --blue:#256bb3; --blue-bg:#e7f0fa; --amber:#b8862f; --amber-bg:#fbf1dd;
            --sidebar: 250px;
        }
        * { box-sizing:border-box; }
        html, body { height:100%; }
        body {
            margin:0; color:var(--ink); background:var(--paper);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            font-size:14px;
        }
        a { color:inherit; text-decoration:none; }
        .muted { color:var(--muted); }

        /* Layout */
        .app { display:grid; grid-template-columns: var(--sidebar) 1fr; min-height:100vh; }
        .sidebar {
            background: linear-gradient(180deg, var(--navy), var(--navy-2));
            color:#cdd8e6; padding:20px 14px; display:flex; flex-direction:column; gap:6px;
            position:sticky; top:0; height:100vh;
        }
        .brand { display:flex; align-items:center; gap:11px; padding:6px 8px 18px; }
        .brand .mark { width:38px; height:38px; border-radius:11px; display:grid; place-items:center; font-size:19px;
            background:linear-gradient(160deg,var(--gold),var(--gold-d)); box-shadow:0 8px 20px rgba(217,157,89,.35); }
        .brand b { color:#fff; font-size:17px; font-weight:900; letter-spacing:.2px; }
        .brand small { display:block; font-size:10px; letter-spacing:2px; text-transform:uppercase; color:#f0d6b3; }
        .nav { display:flex; flex-direction:column; gap:3px; margin-top:4px; }
        .nav a {
            display:flex; align-items:center; gap:11px; padding:10px 12px; border-radius:10px; color:#c2cfe0;
            font-weight:600; font-size:13.5px; cursor:pointer; transition:background .12s,color .12s;
        }
        .nav a .ic { width:20px; text-align:center; font-size:15px; }
        .nav a:hover { background:rgba(255,255,255,.06); color:#fff; }
        .nav a.active { background:rgba(217,157,89,.18); color:#fff; box-shadow: inset 3px 0 0 var(--gold); }
        .nav .spacer { flex:1; }
        .signout { margin-top:auto; }

        /* Topbar + content */
        .main { display:flex; flex-direction:column; min-width:0; }
        .topbar {
            display:flex; align-items:center; justify-content:space-between; gap:16px;
            padding:16px 26px; background:rgba(255,255,255,.8); border-bottom:1px solid var(--line);
            position:sticky; top:0; z-index:5; backdrop-filter: blur(10px);
        }
        .topbar h1 { margin:0; font-size:20px; font-weight:900; color:var(--navy); }
        .who { display:flex; align-items:center; gap:12px; }
        .avatar { width:34px; height:34px; border-radius:50%; background:linear-gradient(160deg,var(--gold),var(--gold-d));
            color:#fff; display:grid; place-items:center; font-weight:800; font-size:14px; }
        .who small { color:var(--muted); }
        .content { padding:24px 26px 60px; }

        /* Cards & grid */
        .stat-grid { display:grid; grid-template-columns: repeat(auto-fill, minmax(180px,1fr)); gap:14px; margin-bottom:22px; }
        .stat { background:var(--card); border:1px solid var(--line); border-radius:14px; padding:16px 18px; box-shadow:0 1px 2px rgba(8,36,67,.04); }
        .stat .k { color:var(--muted); font-size:12.5px; font-weight:600; }
        .stat .v { font-size:26px; font-weight:900; color:var(--navy); margin-top:4px; }
        .stat .v.sm { font-size:20px; }
        .panel { background:var(--card); border:1px solid var(--line); border-radius:16px; box-shadow:0 1px 2px rgba(8,36,67,.04); margin-bottom:20px; overflow:hidden; }
        .panel-head { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:15px 18px; border-bottom:1px solid var(--line); flex-wrap:wrap; }
        .panel-head h2 { margin:0; font-size:15px; font-weight:800; color:var(--navy); }
        .panel-body { padding:6px 0; }
        .pad { padding:16px 18px; }

        /* Toolbar */
        .toolbar { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
        select, input[type=text], input[type=number], input[type=search], input[type=password], input[type=url], textarea {
            padding:8px 10px; border:1px solid var(--line); border-radius:9px; font-size:13px; background:#fff; color:var(--ink); font-family:inherit;
        }
        select:focus, input:focus, textarea:focus { outline:none; border-color:var(--gold); box-shadow:0 0 0 3px rgba(217,157,89,.15); }

        /* Table */
        .table-wrap { overflow-x:auto; }
        table { width:100%; border-collapse:collapse; font-size:13px; }
        th, td { text-align:left; padding:11px 14px; border-bottom:1px solid #f0ece3; white-space:nowrap; }
        th { color:var(--muted); font-weight:700; font-size:11.5px; text-transform:uppercase; letter-spacing:.4px; background:#faf8f4; position:sticky; top:0; }
        tbody tr:hover { background:#fcfaf6; }
        td.wrap { white-space:normal; max-width:280px; }

        /* Badges */
        .badge { display:inline-block; padding:3px 9px; border-radius:999px; font-size:11.5px; font-weight:700; }
        .b-green { background:var(--green-bg); color:var(--green); }
        .b-red { background:var(--red-bg); color:var(--red); }
        .b-blue { background:var(--blue-bg); color:var(--blue); }
        .b-amber { background:var(--amber-bg); color:var(--amber); }
        .b-gray { background:#eef0f3; color:#5b6472; }

        /* Buttons */
        .btn { display:inline-flex; align-items:center; gap:6px; padding:7px 12px; border-radius:9px; border:1px solid var(--line);
            background:#fff; color:var(--ink); font-weight:700; font-size:12.5px; cursor:pointer; transition:background .12s,border-color .12s; }
        .btn:hover { background:#f7f4ef; }
        .btn.sm { padding:5px 9px; font-size:12px; }
        .btn.primary { background:linear-gradient(160deg,var(--navy),var(--navy-2)); color:#fff; border-color:transparent; box-shadow:0 6px 16px rgba(8,36,67,.2); }
        .btn.primary:hover { opacity:.94; }
        .btn.gold { background:linear-gradient(160deg,var(--gold),var(--gold-d)); color:#fff; border-color:transparent; }
        .btn.danger { color:var(--red); border-color:#eccccc; }
        .btn.danger:hover { background:var(--red-bg); }
        .btn.ok { color:var(--green); border-color:#c7e5d4; }
        .btn.ok:hover { background:var(--green-bg); }
        .row-actions { display:flex; gap:6px; flex-wrap:wrap; }

        /* Payment settings */
        .payment-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:16px; }
        .payment-grid .wide { grid-column:1 / -1; }
        .field { display:grid; gap:6px; }
        .field label { font-size:12.5px; font-weight:750; color:var(--navy); }
        .field input:not([type=checkbox]), .field select { width:100%; }
        .field small { color:var(--muted); line-height:1.45; }
        .switch-line { display:flex; align-items:center; gap:9px; min-height:36px; font-weight:700; }
        .notice { padding:12px 14px; border-radius:12px; background:var(--amber-bg); color:#71531d; line-height:1.55; }
        .notice.info { background:var(--blue-bg); color:#184d82; }
        .secret-state { display:flex; align-items:center; justify-content:space-between; gap:8px; flex-wrap:wrap; }
        .code-box { padding:10px 12px; border:1px dashed #c8c2b7; background:#faf8f4; border-radius:10px; font-family:ui-monospace,SFMono-Regular,Consolas,monospace; overflow-wrap:anywhere; }
        .event-list { display:flex; flex-wrap:wrap; gap:7px; margin-top:9px; }
        @media (max-width: 700px) { .payment-grid { grid-template-columns:1fr; } .payment-grid .wide { grid-column:auto; } }

        .empty { padding:34px; text-align:center; color:var(--muted); }
        .loading { padding:34px; text-align:center; color:var(--muted); }
        .spin { display:inline-block; width:18px; height:18px; border:2.5px solid #e2ded4; border-top-color:var(--gold); border-radius:50%; animation:spin .7s linear infinite; vertical-align:-4px; }
        @keyframes spin { to { transform:rotate(360deg); } }

        /* Modal */
        .overlay { position:fixed; inset:0; background:rgba(8,20,38,.5); display:none; align-items:center; justify-content:center; z-index:50; padding:20px; }
        .overlay.show { display:flex; }
        .modal { background:#fff; border-radius:16px; width:min(520px,100%); max-height:88vh; overflow:auto; box-shadow:0 30px 70px rgba(8,20,38,.35); }
        .modal-head { padding:18px 20px; border-bottom:1px solid var(--line); font-weight:900; color:var(--navy); font-size:16px; }
        .modal-body { padding:18px 20px; display:grid; gap:14px; }
        .modal-body label { font-size:12.5px; font-weight:700; }
        .modal-body .fld { display:grid; gap:6px; }
        .modal-body input, .modal-body select, .modal-body textarea { width:100%; }
        .modal-foot { padding:14px 20px; border-top:1px solid var(--line); display:flex; justify-content:flex-end; gap:10px; }
        .kv { display:grid; grid-template-columns: 130px 1fr; gap:6px 12px; font-size:13px; }
        .kv dt { color:var(--muted); font-weight:600; }
        .chk { display:flex; align-items:center; justify-content:space-between; padding:9px 0; border-bottom:1px solid #f0ece3; }
        .doc-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(210px,1fr)); gap:12px; }
        .doc-card { border:1px solid var(--line); border-radius:14px; background:#fff; overflow:hidden; display:flex; flex-direction:column; min-height:250px; }
        .doc-preview { height:132px; background:#f4f1eb; display:grid; place-items:center; color:var(--muted); border-bottom:1px solid var(--line); overflow:hidden; }
        .doc-preview img { width:100%; height:100%; object-fit:cover; display:block; }
        .doc-preview .doc-icon { font-size:34px; opacity:.75; }
        .doc-body { padding:12px; display:grid; gap:8px; flex:1; }
        .doc-title { display:flex; align-items:center; justify-content:space-between; gap:8px; font-weight:900; color:var(--navy); }
        .doc-meta { color:var(--muted); font-size:11.5px; line-height:1.45; overflow-wrap:anywhere; }
        .doc-actions { display:flex; gap:6px; flex-wrap:wrap; margin-top:auto; }
        .fare-form { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:14px; }
        .fare-form .wide { grid-column:1 / -1; }
        .fare-preview { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:12px; margin-top:12px; }
        @media (max-width: 900px) { .fare-form { grid-template-columns:1fr; } .fare-form .wide { grid-column:auto; } }
        .msg { padding:10px 12px; border-radius:12px; background:#f5f2ec; margin-bottom:8px; }
        .msg .meta { font-size:11px; color:var(--muted); margin-bottom:3px; }

        /* Toast */
        .toast-wrap { position:fixed; right:18px; bottom:18px; z-index:80; display:flex; flex-direction:column; gap:10px; }
        .toast { background:var(--navy); color:#fff; padding:12px 16px; border-radius:11px; font-size:13.5px; font-weight:600;
            box-shadow:0 12px 30px rgba(8,20,38,.35); animation:pop .2s ease; max-width:340px; }
        .toast.ok { background:var(--green); } .toast.err { background:var(--red); }
        @keyframes pop { from { transform:translateY(8px); opacity:0; } }

        .menu-btn { display:none; }
        @media (max-width: 900px) {
            .app { grid-template-columns: 1fr; }
            .sidebar { position:fixed; left:0; top:0; z-index:40; width:250px; transform:translateX(-100%); transition:transform .2s; }
            .sidebar.open { transform:none; }
            .menu-btn { display:inline-flex; }
        }
    </style>
</head>
<body>
    <div class="app">
        <aside class="sidebar" id="sidebar">
            <div class="brand"><span class="mark">🚕</span><span><b>MoroRide</b><small>Admin</small></span></div>
            <nav class="nav" id="nav"></nav>
            <div class="signout">
                <a onclick="logout()"><span class="ic">⏻</span> Sign out</a>
            </div>
        </aside>

        <div class="main">
            <div class="topbar">
                <div style="display:flex;align-items:center;gap:12px;">
                    <button class="btn menu-btn" onclick="document.getElementById('sidebar').classList.toggle('open')">☰</button>
                    <h1 id="pageTitle">Dashboard</h1>
                </div>
                <div class="who">
                    <div style="text-align:right;line-height:1.25;">
                        <div id="whoName" style="font-weight:800;">—</div>
                        <small>Administrator</small>
                    </div>
                    <div class="avatar" id="whoAvatar">A</div>
                </div>
            </div>
            <div class="content" id="view"><div class="loading"><span class="spin"></span> Loading…</div></div>
        </div>
    </div>

    <div class="overlay" id="overlay"></div>
    <div class="toast-wrap" id="toasts"></div>

    <script>
    const TOKEN_KEY = 'mororide_admin_token';
    const DOC_TYPES = ['profile','vehicle_out','vehicle_in','id_front','id_back','license','tourism_agreement'];
    const DOC_LABELS = {
        profile: 'Profile photo',
        vehicle_out: 'Vehicle exterior',
        vehicle_in: 'Vehicle interior',
        id_front: 'ID card front',
        id_back: 'ID card back',
        license: 'Driving license',
        tourism_agreement: 'Tourism agreement',
    };

    const SECTIONS = [
        { id:'dashboard', label:'Dashboard', icon:'📊' },
        { id:'users', label:'Users', icon:'👥' },
        { id:'drivers', label:'Drivers & Verification', icon:'🪪' },
        { id:'fares', label:'Fare Pricing', icon:'🧮' },
        { id:'orders', label:'Orders', icon:'🚕' },
        { id:'wallets', label:'Wallets & Points', icon:'👛' },
        { id:'transactions', label:'Transactions & Revenue', icon:'💰' },
        { id:'payments', label:'Payment Settings', icon:'💳' },
        { id:'payouts', label:'Payouts', icon:'🏦' },
        { id:'chats', label:'Chat Archive', icon:'💬' },
        { id:'audit', label:'Audit Logs', icon:'📜' },
        { id:'settings', label:'Settings', icon:'⚙️' },
    ];

    /* ---------- API ---------- */
    function token() { return localStorage.getItem(TOKEN_KEY); }
    function logout() {
        const t = token();
        localStorage.removeItem(TOKEN_KEY);
        if (t) fetch('/api/logout', { method:'POST', headers:{ Authorization:`Bearer ${t}`, Accept:'application/json' } }).catch(()=>{});
        location.replace('/admin/login');
    }
    async function api(method, path, body) {
        const res = await fetch(path, {
            method,
            headers: {
                Authorization: `Bearer ${token()}`,
                Accept: 'application/json',
                ...(body ? { 'Content-Type':'application/json' } : {}),
            },
            body: body ? JSON.stringify(body) : undefined,
        });
        if (res.status === 401) { localStorage.removeItem(TOKEN_KEY); location.replace('/admin/login'); throw new Error('Session expired'); }
        const payload = await res.json().catch(()=>({}));
        if (!res.ok) {
            let msg = payload?.message || 'Request failed';
            if (payload?.errors) msg = Object.values(payload.errors).flat().join(' ');
            throw new Error(msg);
        }
        return payload;
    }
    const listOf = (p) => Array.isArray(p?.data?.data) ? p.data.data : (Array.isArray(p?.data) ? p.data : []);
    const totalOf = (p) => p?.data?.meta?.total ?? listOf(p).length;

    /* ---------- UI helpers ---------- */
    function toast(msg, type='ok') {
        const t = document.createElement('div');
        t.className = `toast ${type}`; t.textContent = msg;
        document.getElementById('toasts').appendChild(t);
        setTimeout(()=>t.remove(), 3200);
    }
    const esc = (s) => String(s ?? '').replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
    const money = (n) => `${Number(n||0).toLocaleString('en-US',{minimumFractionDigits:2,maximumFractionDigits:2})}`;
    const dt = (s) => s ? new Date(s).toLocaleString() : '—';
    function statusBadge(s) {
        const map = { active:'b-green', approved:'b-green', confirmed:'b-green', completed:'b-green', succeeded:'b-green', ready:'b-green',
            pending:'b-amber', searching:'b-blue', offered:'b-blue', assigned:'b-blue', arrived:'b-blue', in_progress:'b-blue',
            test:'b-blue', live:'b-amber', suspended:'b-red', blocked:'b-red', rejected:'b-red', cancelled:'b-red', disabled:'b-red', incomplete:'b-gray', missing:'b-gray' };
        return `<span class="badge ${map[s]||'b-gray'}">${esc(s)}</span>`;
    }
    function view(html) { document.getElementById('view').innerHTML = html; }
    function loading() { view('<div class="loading"><span class="spin"></span> Loading…</div>'); }

    /* ---------- Modal ---------- */
    function closeModal() { document.getElementById('overlay').classList.remove('show'); document.getElementById('overlay').innerHTML=''; }
    function infoModal(title, bodyHtml) {
        const o = document.getElementById('overlay');
        o.innerHTML = `<div class="modal"><div class="modal-head">${esc(title)}</div><div class="modal-body">${bodyHtml}</div>
            <div class="modal-foot"><button class="btn" onclick="closeModal()">Close</button></div></div>`;
        o.classList.add('show');
    }
    // fields: [{name,label,type,options,value,placeholder,required}]
    function formModal(title, fields, submitLabel='Save') {
        return new Promise((resolve) => {
            const o = document.getElementById('overlay');
            const fieldHtml = fields.map(f => {
                const id = `f_${f.name}`;
                if (f.type === 'select') {
                    const opts = f.options.map(op => `<option value="${esc(op.value)}" ${op.value==f.value?'selected':''}>${esc(op.label)}</option>`).join('');
                    return `<div class="fld"><label for="${id}">${esc(f.label)}</label><select id="${id}">${opts}</select></div>`;
                }
                if (f.type === 'textarea') {
                    return `<div class="fld"><label for="${id}">${esc(f.label)}</label><textarea id="${id}" rows="3" placeholder="${esc(f.placeholder||'')}">${esc(f.value||'')}</textarea></div>`;
                }
                return `<div class="fld"><label for="${id}">${esc(f.label)}</label><input id="${id}" type="${f.type||'text'}" value="${esc(f.value??'')}" placeholder="${esc(f.placeholder||'')}"></div>`;
            }).join('');
            o.innerHTML = `<div class="modal"><div class="modal-head">${esc(title)}</div>
                <div class="modal-body">${fieldHtml}</div>
                <div class="modal-foot"><button class="btn" id="mCancel">Cancel</button><button class="btn primary" id="mOk">${esc(submitLabel)}</button></div></div>`;
            o.classList.add('show');
            document.getElementById('mCancel').onclick = () => { closeModal(); resolve(null); };
            document.getElementById('mOk').onclick = () => {
                const out = {};
                for (const f of fields) out[f.name] = document.getElementById(`f_${f.name}`).value;
                closeModal(); resolve(out);
            };
        });
    }

    /* ---------- Sections ---------- */
    const render = {};

    render.dashboard = async () => {
        loading();
        const p = await api('GET', '/api/admin/dashboard');
        const d = p.data;
        const cards = [
            ['Users', d.users.total], ['Online drivers', d.drivers.online], ['Pending verification', d.drivers.pending_verification],
            ['Live orders', d.orders.live], ['Completed orders', d.orders.completed], ['Flagged orders', d.orders.flagged],
            ['Docs pending', d.documents_pending], ['Payouts pending', d.payouts_pending],
            ['Gross MAD', money(d.revenue.gross)], ['Platform fees MAD', money(d.revenue.platform_fees)], ['Driver net MAD', money(d.revenue.driver_net)],
        ];
        const activity = (d.recent_activity?.data || d.recent_activity || []).map(a => `
            <tr><td>${esc(a.action)}</td><td>${esc(a.target_type||'—')} ${a.target_id?('#'+a.target_id):''}</td><td>${a.actor_id?('#'+a.actor_id):'system'}</td><td>${dt(a.created_at)}</td></tr>`).join('');
        view(`
            <div class="stat-grid">${cards.map(([k,v])=>`<div class="stat"><div class="k">${k}</div><div class="v ${String(v).length>7?'sm':''}">${v}</div></div>`).join('')}</div>
            <div class="panel"><div class="panel-head"><h2>Recent activity</h2></div>
              <div class="table-wrap"><table><thead><tr><th>Action</th><th>Target</th><th>Actor</th><th>When</th></tr></thead>
              <tbody>${activity || '<tr><td colspan="4" class="empty">No activity yet.</td></tr>'}</tbody></table></div></div>`);
    };

    render.users = async (params={}) => {
        loading();
        const qs = new URLSearchParams(params).toString();
        const p = await api('GET', '/api/admin/users' + (qs?`?${qs}`:''));
        const rows = listOf(p).map(u => `
            <tr>
              <td>${u.id}</td><td>${esc(u.name)}</td><td class="muted">${esc(u.email)}</td>
              <td>${statusBadge(u.role)}</td><td>${statusBadge(u.status)}</td>
              <td class="row-actions"><button class="btn sm" onclick="actUserStatus(${u.id},'${esc(u.status)}')">Change status</button></td>
            </tr>`).join('');
        view(`
            <div class="panel">
              <div class="panel-head"><h2>Users · ${totalOf(p)}</h2>
                <div class="toolbar">
                  <select id="fRole" onchange="reloadUsers()"><option value="">All roles</option>
                    ${['rider','driver','concierge','admin'].map(r=>`<option ${params.role===r?'selected':''}>${r}</option>`).join('')}</select>
                  <select id="fStatus" onchange="reloadUsers()"><option value="">Any status</option>
                    ${['active','suspended','blocked'].map(r=>`<option ${params.status===r?'selected':''}>${r}</option>`).join('')}</select>
                  <input type="search" id="fSearch" placeholder="Search name/email" value="${esc(params.search||'')}" onkeydown="if(event.key==='Enter')reloadUsers()">
                </div></div>
              <div class="table-wrap"><table><thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Actions</th></tr></thead>
              <tbody>${rows || '<tr><td colspan="6" class="empty">No users found.</td></tr>'}</tbody></table></div></div>`);
    };
    window.reloadUsers = () => render.users({
        role: document.getElementById('fRole').value, status: document.getElementById('fStatus').value, search: document.getElementById('fSearch').value,
    });
    window.actUserStatus = async (id, current) => {
        const r = await formModal('Change user status', [
            { name:'status', label:'Status', type:'select', value:current, options:[{value:'active',label:'active'},{value:'suspended',label:'suspended'},{value:'blocked',label:'blocked'}] },
            { name:'reason', label:'Reason (optional)', type:'textarea' },
        ], 'Update');
        if (!r) return;
        try { await api('PATCH', `/api/admin/users/${id}/status`, r); toast('Status updated'); reloadUsers(); }
        catch(e){ toast(e.message,'err'); }
    };

    render.drivers = async (params={}) => {
        loading();
        const qs = new URLSearchParams(params).toString();
        const p = await api('GET', '/api/admin/drivers' + (qs?`?${qs}`:''));
        const rows = listOf(p).map(u => {
            const ap = u.driver_profile?.approval_state || 'incomplete';
            return `<tr>
              <td>${u.id}</td><td>${esc(u.name)}</td><td class="muted">${esc(u.email)}</td>
              <td>${esc(u.driver_profile?.vehicle_name||'—')}</td><td>${statusBadge(ap)}</td>
              <td class="row-actions">
                <button class="btn sm" onclick="viewDocs(${u.id})">Documents</button>
                <button class="btn sm ok" onclick="approveDriver(${u.id})">Approve</button>
                <button class="btn sm danger" onclick="rejectDriver(${u.id})">Reject</button>
                <button class="btn sm" onclick="requestDoc(${u.id})">Request doc</button>
              </td></tr>`;
        }).join('');
        view(`
            <div class="panel">
              <div class="panel-head"><h2>Drivers · ${totalOf(p)}</h2>
                <div class="toolbar"><select id="fAppr" onchange="render.drivers({approval_state:this.value})">
                  <option value="">All states</option>${['pending','approved','rejected','incomplete'].map(s=>`<option ${params.approval_state===s?'selected':''}>${s}</option>`).join('')}
                </select></div></div>
              <div class="table-wrap"><table><thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Vehicle</th><th>Approval</th><th>Actions</th></tr></thead>
              <tbody>${rows || '<tr><td colspan="6" class="empty">No drivers found.</td></tr>'}</tbody></table></div></div>`);
    };
    window.viewDocs = async (id) => {
        try {
            const p = await api('GET', `/api/admin/drivers/${id}/documents`);
            const d = p.data;
            const cards = d.checklist.map(c => {
                const doc = c.document;
                const label = DOC_LABELS[c.type] || c.type;
                if (!doc) {
                    return `<div class="doc-card">
                      <div class="doc-preview"><span class="doc-icon">＋</span></div>
                      <div class="doc-body">
                        <div class="doc-title"><span>${esc(label)}</span>${statusBadge('missing')}</div>
                        <div class="doc-meta">No file uploaded yet.</div>
                        <div class="doc-actions"><button class="btn sm" onclick="requestDoc(${id}, '${esc(c.type)}')">Request</button></div>
                      </div>
                    </div>`;
                }

                return `<div class="doc-card">
                  <div class="doc-preview" id="docPrev${doc.id}"><span class="doc-icon">${doc.is_pdf ? 'PDF' : '📄'}</span></div>
                  <div class="doc-body">
                    <div class="doc-title"><span>${esc(label)}</span>${statusBadge(doc.status)}</div>
                    <div class="doc-meta">${esc(doc.file_name || doc.original_name || doc.file_path)}<br>Uploaded ${dt(doc.created_at)}${doc.note ? `<br><b>Note:</b> ${esc(doc.note)}` : ''}</div>
                    <div class="doc-actions">
                      <button class="btn sm" onclick="openDocument(${doc.id})">Open</button>
                      <button class="btn sm ok" onclick="reviewDocument(${doc.id}, 'approved', ${id})">Approve</button>
                      <button class="btn sm danger" onclick="reviewDocument(${doc.id}, 'rejected', ${id})">Reject</button>
                      <button class="btn sm" onclick="requestDoc(${id}, '${esc(c.type)}')">Request new</button>
                    </div>
                  </div>
                </div>`;
            }).join('');
            infoModal(`Documents — ${esc(d.driver?.name||('#'+id))}`,
                `<div class="notice info"><b>${d.has_all_required ? 'All required files are uploaded.' : 'Some required files are still missing.'}</b><br>Open each document, then approve, reject, or request a replacement from the driver.</div>
                 <div class="doc-grid">${cards}</div>`);
            for (const c of d.checklist) {
                if (c.document?.is_image) loadDocumentPreview(c.document.id);
            }
        } catch(e){ toast(e.message,'err'); }
    };
    window.fetchDocumentBlob = async (docId) => {
        const res = await fetch(`/api/admin/documents/${docId}/file`, {
            headers: { Authorization: `Bearer ${token()}`, Accept: '*/*' },
        });
        if (!res.ok) throw new Error('Could not open document');
        return res.blob();
    };
    window.loadDocumentPreview = async (docId) => {
        const box = document.getElementById(`docPrev${docId}`);
        if (!box) return;
        try {
            const blob = await fetchDocumentBlob(docId);
            const url = URL.createObjectURL(blob);
            box.innerHTML = `<img src="${url}" alt="Document preview">`;
        } catch (_) {
            box.innerHTML = '<span class="doc-icon">📄</span>';
        }
    };
    window.openDocument = async (docId) => {
        try {
            const blob = await fetchDocumentBlob(docId);
            const url = URL.createObjectURL(blob);
            window.open(url, '_blank', 'noopener');
        } catch(e){ toast(e.message,'err'); }
    };
    window.reviewDocument = async (docId, status, driverId) => {
        let note = null;
        if (status === 'rejected') {
            const r = await formModal('Reject document', [{ name:'note', label:'Reason / note', type:'textarea', placeholder:'Tell the driver what must be fixed.' }], 'Reject');
            if (!r) return;
            note = r.note || null;
        }
        try {
            await api('POST', `/api/admin/documents/${docId}/review`, { status, ...(note ? { note } : {}) });
            toast(status === 'approved' ? 'Document approved' : 'Document rejected');
            await viewDocs(driverId);
        } catch(e){ toast(e.message,'err'); }
    };
    window.approveDriver = async (id) => {
        try { await api('POST', `/api/admin/drivers/${id}/approve`); toast('Driver approved'); render.drivers(); }
        catch(e){ toast(e.message,'err'); }
    };
    window.rejectDriver = async (id) => {
        const r = await formModal('Reject driver', [{ name:'reason', label:'Reason', type:'textarea', placeholder:'Why is this driver rejected?' }], 'Reject');
        if (!r) return;
        try { await api('POST', `/api/admin/drivers/${id}/reject`, r); toast('Driver rejected'); render.drivers(); }
        catch(e){ toast(e.message,'err'); }
    };
    window.requestDoc = async (id, initialType='profile') => {
        const r = await formModal('Request a document', [
            { name:'type', label:'Document', type:'select', value:initialType, options: DOC_TYPES.map(t=>({value:t,label:DOC_LABELS[t] || t})) },
            { name:'note', label:'Note (optional)', type:'textarea' },
        ], 'Request');
        if (!r) return;
        try { await api('POST', `/api/admin/drivers/${id}/request-document`, r); toast('Document requested'); }
        catch(e){ toast(e.message,'err'); }
    };

    render.fares = async () => {
        loading();
        const p = await api('GET', '/api/admin/fare-config');
        const active = p.data.active || {};
        const history = p.data.history || [];
        const value = (key, fallback='') => esc(active[key] ?? fallback);
        const rows = history.map(f => `
            <tr>
              <td>${f.id}</td><td>${money(f.base)}</td><td>${money(f.per_km)}</td><td>${money(f.per_min)}</td>
              <td>${money(f.floor)}</td><td>${Number(f.platform_fee_pct || 0) * 100}%</td>
              <td>${statusBadge(f.is_active ? 'active' : 'inactive')}</td><td>${dt(f.active_from || f.created_at)}</td>
            </tr>`).join('');
        view(`
            <div class="stat-grid">
              <div class="stat"><div class="k">Charge per KM</div><div class="v sm">${money(active.per_km)} ${esc(active.currency || 'MAD')}</div></div>
              <div class="stat"><div class="k">Base fare</div><div class="v sm">${money(active.base)} ${esc(active.currency || 'MAD')}</div></div>
              <div class="stat"><div class="k">Minimum fare</div><div class="v sm">${money(active.floor)} ${esc(active.currency || 'MAD')}</div></div>
              <div class="stat"><div class="k">Platform fee</div><div class="v sm">${money(Number(active.platform_fee_pct || 0) * 100)}%</div></div>
            </div>

            <div class="panel">
              <div class="panel-head"><h2>Suggested rider fare formula</h2><button class="btn primary sm" onclick="saveFareConfig()">Save new pricing</button></div>
              <div class="pad">
                <div class="notice info"><b>How it works:</b> the rider sees this as the suggested fare after choosing pickup and drop-off. The rider can still change the offered price, and drivers can still accept that rider price or send a counter offer.</div>
                <div class="fare-form" id="fareForm">
                  <div class="field"><label>Currency</label><input id="fareCurrency" type="text" maxlength="3" value="${value('currency','MAD')}"></div>
                  <div class="field"><label>Base fare</label><input id="fareBase" type="number" min="0" step="0.01" value="${value('base',0)}" oninput="updateFarePreview()"></div>
                  <div class="field"><label>Charge per KM</label><input id="farePerKm" type="number" min="0" step="0.01" value="${value('per_km',0)}" oninput="updateFarePreview()"></div>
                  <div class="field"><label>Charge per minute</label><input id="farePerMin" type="number" min="0" step="0.01" value="${value('per_min',0)}" oninput="updateFarePreview()"></div>
                  <div class="field"><label>Extra passenger charge</label><input id="farePerPax" type="number" min="0" step="0.01" value="${value('per_pax',0)}" oninput="updateFarePreview()"></div>
                  <div class="field"><label>Minimum fare floor</label><input id="fareFloor" type="number" min="0" step="0.01" value="${value('floor',0)}" oninput="updateFarePreview()"></div>
                  <div class="field"><label>Platform fee %</label><input id="farePlatformPct" type="number" min="0" max="100" step="0.01" value="${money(Number(active.platform_fee_pct || 0) * 100)}"></div>
                  <div class="field"><label>Sedan multiplier</label><input id="fareSedan" type="number" min="0.1" step="0.01" value="${value('sedan_multiplier',1)}" oninput="updateFarePreview()"></div>
                  <div class="field"><label>Minivan multiplier</label><input id="fareMinivan" type="number" min="0.1" step="0.01" value="${value('minivan_multiplier',1.25)}"></div>
                  <div class="field"><label>SUV multiplier</label><input id="fareSuv" type="number" min="0.1" step="0.01" value="${value('suv_multiplier',1.35)}"></div>
                  <div class="field"><label>Minibus multiplier</label><input id="fareMinibus" type="number" min="0.1" step="0.01" value="${value('minibus_multiplier',1.75)}"></div>
                  <div class="field"><label>Luxury multiplier</label><input id="fareLuxury" type="number" min="0.1" step="0.01" value="${value('luxury_multiplier',2)}"></div>
                </div>
                <div class="fare-preview">
                  <div class="stat"><div class="k">Example trip</div><div class="v sm">9.4 km · 18 min · 2 pax</div></div>
                  <div class="stat"><div class="k">Rider suggested fare</div><div class="v sm" id="farePreviewSuggested">—</div></div>
                  <div class="stat"><div class="k">Driver receives offer</div><div class="v sm">Accept or counter</div></div>
                </div>
              </div>
            </div>

            <div class="panel"><div class="panel-head"><h2>Pricing history</h2></div>
              <div class="table-wrap"><table><thead><tr><th>ID</th><th>Base</th><th>Per KM</th><th>Per min</th><th>Floor</th><th>Fee</th><th>Status</th><th>Active from</th></tr></thead>
              <tbody>${rows || '<tr><td colspan="8" class="empty">No fare config history.</td></tr>'}</tbody></table></div></div>`);
        updateFarePreview();
    };
    window.fareNum = (id) => Number(document.getElementById(id)?.value || 0);
    window.updateFarePreview = () => {
        const subtotal = fareNum('fareBase') + (9.4 * fareNum('farePerKm')) + (18 * fareNum('farePerMin')) + fareNum('farePerPax');
        const suggested = Math.max(fareNum('fareFloor'), subtotal * (fareNum('fareSedan') || 1));
        const el = document.getElementById('farePreviewSuggested');
        if (el) el.textContent = `${money(suggested)} ${document.getElementById('fareCurrency')?.value || 'MAD'}`;
    };
    window.saveFareConfig = async () => {
        const body = {
            currency: document.getElementById('fareCurrency').value.trim().toUpperCase() || 'MAD',
            base: fareNum('fareBase'),
            per_km: fareNum('farePerKm'),
            per_min: fareNum('farePerMin'),
            per_pax: fareNum('farePerPax'),
            floor: fareNum('fareFloor'),
            platform_fee_pct: fareNum('farePlatformPct') / 100,
            sedan_multiplier: fareNum('fareSedan') || 1,
            minivan_multiplier: fareNum('fareMinivan') || 1.25,
            suv_multiplier: fareNum('fareSuv') || 1.35,
            minibus_multiplier: fareNum('fareMinibus') || 1.75,
            luxury_multiplier: fareNum('fareLuxury') || 2,
        };
        try { await api('POST', '/api/admin/fare-config', body); toast('Fare pricing saved'); await render.fares(); }
        catch(e){ toast(e.message,'err'); }
    };

    render.orders = async (params={}) => {
        loading();
        const qs = new URLSearchParams(params).toString();
        const p = await api('GET', '/api/admin/orders' + (qs?`?${qs}`:''));
        const rows = listOf(p).map(o => `
            <tr>
              <td>#${o.id}</td><td>${statusBadge(o.status)}</td><td>${esc(o.source)}</td>
              <td class="wrap">${esc(o.pickup_address)} → ${esc(o.dropoff_address)}</td>
              <td>${money(o.final_fare ?? o.offered_fare)}</td>
              <td>${o.assigned_driver_id?('#'+o.assigned_driver_id):'—'}</td>
              <td class="row-actions">
                <button class="btn sm" onclick="flagOrder(${o.id})">Flag</button>
                <button class="btn sm danger" onclick="cancelOrder(${o.id})">Cancel</button>
              </td></tr>`).join('');
        view(`
            <div class="panel">
              <div class="panel-head"><h2>Orders · ${totalOf(p)}</h2>
                <div class="toolbar"><select id="fOstatus" onchange="render.orders({status:this.value})">
                  <option value="">All statuses</option>${['searching','offered','assigned','arrived','in_progress','completed','cancelled'].map(s=>`<option ${params.status===s?'selected':''}>${s}</option>`).join('')}
                </select>
                <button class="btn sm" onclick="render.orders({flagged:1})">Flagged only</button></div></div>
              <div class="table-wrap"><table><thead><tr><th>ID</th><th>Status</th><th>Source</th><th>Route</th><th>Fare</th><th>Driver</th><th>Actions</th></tr></thead>
              <tbody>${rows || '<tr><td colspan="7" class="empty">No orders found.</td></tr>'}</tbody></table></div></div>`);
    };
    window.cancelOrder = async (id) => {
        const r = await formModal('Cancel order', [{ name:'reason', label:'Reason', type:'textarea' }], 'Cancel order');
        if (!r) return;
        try { await api('POST', `/api/admin/orders/${id}/cancel`, r); toast('Order cancelled'); render.orders(); }
        catch(e){ toast(e.message,'err'); }
    };
    window.flagOrder = async (id) => {
        const r = await formModal('Flag order for review', [{ name:'reason', label:'Reason', type:'textarea' }], 'Flag');
        if (!r) return;
        try { await api('POST', `/api/admin/orders/${id}/flag`, r); toast('Order flagged'); render.orders(); }
        catch(e){ toast(e.message,'err'); }
    };

    render.wallets = async () => {
        loading();
        const p = await api('GET', '/api/admin/wallets');
        const rows = listOf(p).map(w => `
            <tr>
              <td>${w.id}</td><td>${esc(w.user?.name || ('user #'+w.user_id))}</td>
              <td>${money(w.points_balance)}</td><td>${money(w.wallet_balance)} ${esc(w.currency)}</td><td>${w.free_rides_remaining}</td>
              <td class="row-actions"><button class="btn sm" onclick="adjustWallet(${w.id})">Adjust</button></td>
            </tr>`).join('');
        view(`
            <div class="panel"><div class="panel-head"><h2>Wallets · ${totalOf(p)}</h2></div>
              <div class="table-wrap"><table><thead><tr><th>ID</th><th>User</th><th>Points</th><th>Balance</th><th>Free rides</th><th>Actions</th></tr></thead>
              <tbody>${rows || '<tr><td colspan="6" class="empty">No wallets.</td></tr>'}</tbody></table></div></div>`);
    };
    window.adjustWallet = async (id) => {
        const r = await formModal('Adjust wallet', [
            { name:'points_delta', label:'Points delta (+/-)', type:'number', value:'0' },
            { name:'balance_delta', label:'Balance delta MAD (+/-)', type:'number', value:'0' },
            { name:'reason', label:'Reason', type:'textarea' },
        ], 'Apply');
        if (!r) return;
        try { await api('POST', `/api/admin/wallets/${id}/adjust`, r); toast('Wallet adjusted'); render.wallets(); }
        catch(e){ toast(e.message,'err'); }
    };

    render.transactions = async () => {
        loading();
        const [rev, tx] = await Promise.all([ api('GET','/api/admin/revenue'), api('GET','/api/admin/transactions') ]);
        const d = rev.data;
        const cards = [['Gross MAD',money(d.gross)],['Fees MAD',money(d.fees)],['Driver net MAD',money(d.driver_net)],
            ['Transactions',d.transactions],['Cash rides',d.cash_rides],['Card rides',d.card_rides]];
        const rows = listOf(tx).map(t => `
            <tr><td>#${t.id}</td><td>${t.order_id?('#'+t.order_id):'—'}</td><td>${statusBadge(t.type)}</td>
            <td>${money(t.fare)}</td><td>${money(t.fee)}</td><td>${money(t.net)}</td><td>${statusBadge(t.status)}</td><td>${dt(t.created_at)}</td></tr>`).join('');
        view(`
            <div class="stat-grid">${cards.map(([k,v])=>`<div class="stat"><div class="k">${k}</div><div class="v ${String(v).length>7?'sm':''}">${v}</div></div>`).join('')}</div>
            <div class="panel"><div class="panel-head"><h2>Transactions · ${totalOf(tx)}</h2></div>
              <div class="table-wrap"><table><thead><tr><th>ID</th><th>Order</th><th>Type</th><th>Fare</th><th>Fee</th><th>Net</th><th>Status</th><th>When</th></tr></thead>
              <tbody>${rows || '<tr><td colspan="8" class="empty">No transactions yet.</td></tr>'}</tbody></table></div></div>`);
    };

    render.payouts = async () => {
        loading();
        const p = await api('GET', '/api/admin/payouts');
        const rows = listOf(p).map(o => `
            <tr><td>#${o.id}</td><td>${o.driver?.name || ('#'+o.driver_id)}</td><td>${money(o.amount)} ${esc(o.currency)}</td>
            <td>${statusBadge(o.status)}</td><td>${esc(o.method)}</td><td>${esc(o.reference||'—')}</td>
            <td class="row-actions">${o.status==='pending'?`<button class="btn sm ok" onclick="confirmPayout(${o.id})">Confirm</button>`:'—'}</td></tr>`).join('');
        view(`
            <div class="panel"><div class="panel-head"><h2>Payouts · ${totalOf(p)}</h2>
              <button class="btn primary sm" onclick="newPayout()">+ New payout</button></div>
              <div class="table-wrap"><table><thead><tr><th>ID</th><th>Driver</th><th>Amount</th><th>Status</th><th>Method</th><th>Reference</th><th>Actions</th></tr></thead>
              <tbody>${rows || '<tr><td colspan="7" class="empty">No payouts yet.</td></tr>'}</tbody></table></div></div>`);
    };
    window.newPayout = async () => {
        let drivers = [];
        try { drivers = listOf(await api('GET','/api/admin/drivers?approval_state=approved')); } catch(_) {}
        const r = await formModal('Create payout', [
            { name:'driver_id', label:'Driver', type:'select', options: drivers.map(d=>({value:d.id,label:`${d.name} (#${d.id}) — ${money(d.wallet?.wallet_balance||0)} MAD`})) },
            { name:'amount', label:'Amount MAD (blank = full balance)', type:'number' },
            { name:'note', label:'Note (optional)', type:'text' },
        ], 'Create');
        if (!r) return;
        const body = { driver_id: Number(r.driver_id) };
        if (r.amount) body.amount = Number(r.amount);
        if (r.note) body.note = r.note;
        try { await api('POST', '/api/admin/payouts', body); toast('Payout created'); render.payouts(); }
        catch(e){ toast(e.message,'err'); }
    };
    window.confirmPayout = async (id) => {
        const r = await formModal('Confirm payout', [{ name:'reference', label:'Payment reference (optional)', type:'text' }], 'Confirm');
        if (!r) return;
        try { await api('POST', `/api/admin/payouts/${id}/confirm`, r.reference?{reference:r.reference}:{}); toast('Payout confirmed'); render.payouts(); }
        catch(e){ toast(e.message,'err'); }
    };

    render.chats = async () => {
        loading();
        const p = await api('GET', '/api/admin/chats');
        const rows = listOf(p).map(o => `
            <tr><td>#${o.id}</td><td>${statusBadge(o.status)}</td><td class="wrap">${esc(o.pickup_address)} → ${esc(o.dropoff_address)}</td>
            <td>${o.messages_count ?? '—'}</td>
            <td class="row-actions"><button class="btn sm" onclick="viewChat(${o.id})">Open</button></td></tr>`).join('');
        view(`
            <div class="panel"><div class="panel-head"><h2>Chat archive · ${totalOf(p)}</h2></div>
              <div class="table-wrap"><table><thead><tr><th>Order</th><th>Status</th><th>Route</th><th>Messages</th><th></th></tr></thead>
              <tbody>${rows || '<tr><td colspan="5" class="empty">No chats yet.</td></tr>'}</tbody></table></div></div>`);
    };
    window.viewChat = async (id) => {
        try {
            const p = await api('GET', `/api/admin/chats/${id}`);
            const msgs = (p.data.messages||[]).map(m => `<div class="msg"><div class="meta">${esc(m.sender_role)} · ${dt(m.created_at)}</div>${esc(m.text||'')}${m.image_url?`<div class="muted">[image]</div>`:''}</div>`).join('');
            infoModal(`Conversation — order #${id}`, msgs || '<p class="muted">No messages.</p>');
        } catch(e){ toast(e.message,'err'); }
    };

    render.audit = async (params={}) => {
        loading();
        const qs = new URLSearchParams(params).toString();
        const p = await api('GET', '/api/admin/audit-logs' + (qs?`?${qs}`:''));
        const rows = listOf(p).map(a => `
            <tr><td>${a.id}</td><td>${statusBadge(a.action)}</td><td>${esc(a.target_type||'—')} ${a.target_id?('#'+a.target_id):''}</td>
            <td>${a.actor?.name || (a.actor_id?('#'+a.actor_id):'system')}</td><td>${esc(a.ip_address||'—')}</td><td>${dt(a.created_at)}</td></tr>`).join('');
        view(`
            <div class="panel"><div class="panel-head"><h2>Audit logs · ${totalOf(p)}</h2>
              <div class="toolbar"><input type="search" id="fAction" placeholder="Filter by action" value="${esc(params.action||'')}" onkeydown="if(event.key==='Enter')render.audit({action:this.value})"></div></div>
              <div class="table-wrap"><table><thead><tr><th>ID</th><th>Action</th><th>Target</th><th>Actor</th><th>IP</th><th>When</th></tr></thead>
              <tbody>${rows || '<tr><td colspan="6" class="empty">No audit entries.</td></tr>'}</tbody></table></div></div>`);
    };

    render.payments = async () => {
        loading();
        const p = await api('GET', '/api/admin/payment-settings');
        const d = p.data;
        const v = d.values;
        const s = d.status;
        const state = s.ready ? 'ready' : (v.enabled ? 'incomplete' : 'disabled');
        const missing = (s.missing_fields || []).map(x => x.replaceAll('_',' ')).join(', ') || 'None';
        view(`
            <div class="stat-grid">
              <div class="stat"><div class="k">Provider</div><div class="v sm">${esc(v.provider)}</div></div>
              <div class="stat"><div class="k">Payment state</div><div class="v sm">${statusBadge(state)}</div></div>
              <div class="stat"><div class="k">Stripe mode</div><div class="v sm">${statusBadge(v.mode)}</div></div>
              <div class="stat"><div class="k">Config source</div><div class="v sm">${esc(s.source)}</div></div>
              <div class="stat"><div class="k">Webhook failures</div><div class="v sm">${d.webhook.failed_count}</div></div>
            </div>

            <div class="panel">
              <div class="panel-head"><h2>Stripe & card payments</h2><div class="row-actions">
                <button class="btn" onclick="testPaymentConnection()">Test Stripe connection</button>
                <button class="btn primary" onclick="savePaymentSettings()">Save payment settings</button>
              </div></div>
              <div class="pad payment-grid" id="paymentForm">
                <div class="field"><label>Payment provider</label><select id="payProvider">
                  <option value="cash_only" ${v.provider==='cash_only'?'selected':''}>Cash only</option>
                  <option value="stripe" ${v.provider==='stripe'?'selected':''}>Stripe</option>
                </select><small>Cash only disables card payments and Stripe Connect.</small></div>
                <div class="field"><label>Stripe mode</label><select id="payMode">
                  <option value="test" ${v.mode==='test'?'selected':''}>Test</option>
                  <option value="live" ${v.mode==='live'?'selected':''}>Live</option>
                </select><small>Keys must match the selected mode.</small></div>

                <div class="field wide"><label class="switch-line"><input id="payEnabled" type="checkbox" ${v.enabled?'checked':''}> Enable Stripe card payments</label>
                  <small>Stripe can only be enabled after all three credentials below are valid.</small></div>

                <div class="field wide"><label>Publishable key</label><input id="payPublishable" type="text" autocomplete="off" value="${esc(v.publishable_key||'')}" placeholder="pk_test_... or pk_live_..."></div>
                <div class="field"><div class="secret-state"><label>Secret key</label>${s.secret_key_configured?'<span class="badge b-green">Configured</span>':'<span class="badge b-gray">Missing</span>'}</div>
                  <input id="paySecret" type="password" autocomplete="new-password" placeholder="Blank keeps ${esc(s.secret_key_masked||'current value')}">
                  <label class="switch-line"><input id="clearSecret" type="checkbox"> Clear saved secret key</label></div>
                <div class="field"><div class="secret-state"><label>Webhook signing secret</label>${s.webhook_secret_configured?'<span class="badge b-green">Configured</span>':'<span class="badge b-gray">Missing</span>'}</div>
                  <input id="payWebhookSecret" type="password" autocomplete="new-password" placeholder="Blank keeps ${esc(s.webhook_secret_masked||'current value')}">
                  <label class="switch-line"><input id="clearWebhookSecret" type="checkbox"> Clear saved webhook secret</label></div>

                <div class="field"><label>Currency</label><select id="payCurrency">
                  <option value="MAD" ${v.currency==='MAD'?'selected':''}>MAD — Moroccan dirham</option>
                  <option value="USD" ${v.currency==='USD'?'selected':''}>USD — US dollar ($)</option>
                </select></div>
                <div class="field wide"><div class="notice"><b>Driver points pricing</b><br>Set the selling price of one point. The server uses this price for every driver purchase and records the credited points in the wallet ledger.</div></div>
                <div class="field"><label>Price of 1 point (${esc(v.currency)})</label><input id="payPointPrice" type="number" min="0.0001" step="0.0001" value="${esc(v.point_price)}"><small>Example: 1.50 means 100 points cost 150 ${esc(v.currency)}.</small></div>
                <div class="field"><label>Minimum card payment (${esc(v.currency)})</label><input id="payPointsMin" type="number" min="1" step="0.01" value="${esc(v.points_min_topup)}"></div>
                <div class="field"><label>Maximum card payment (${esc(v.currency)})</label><input id="payPointsMax" type="number" min="1" step="0.01" value="${esc(v.points_max_topup)}"></div>

                <div class="field wide"><label class="switch-line"><input id="payConnectEnabled" type="checkbox" ${v.connect_enabled?'checked':''}> Enable Stripe Connect driver onboarding</label></div>
                <div class="field"><label>Connected account country</label><input id="payConnectCountry" type="text" maxlength="2" value="${esc(v.connect_country||'')}" placeholder="FR"></div>
                <div class="field"><label>Connect refresh URL</label><input id="payConnectRefresh" type="url" value="${esc(v.connect_refresh_url||'')}" placeholder="https://app.example.com/payments/connect/refresh"></div>
                <div class="field wide"><label>Connect return URL</label><input id="payConnectReturn" type="url" value="${esc(v.connect_return_url||'')}" placeholder="https://app.example.com/payments/connect/return"></div>

                <div class="notice wide">Secrets are encrypted before storage, masked in this dashboard, and excluded from audit logs. Blank secret fields keep their current saved values. Missing: <b>${esc(missing)}</b>.</div>
                <div class="notice wide">Live Stripe accounts are not currently available for businesses registered in Morocco. Keep Test mode unless the business uses a Stripe-supported legal entity and bank account.</div>
              </div>
            </div>

            <div class="panel"><div class="panel-head"><h2>Stripe webhook</h2></div><div class="pad">
              <p class="muted" style="margin-top:0">Create one Stripe webhook endpoint with this exact URL, then save its <b>whsec_...</b> signing secret above.</p>
              <div class="code-box">${esc(d.webhook.url)}</div>
              <div class="event-list">${d.webhook.events.map(e=>`<span class="badge b-blue">${esc(e)}</span>`).join('')}</div>
              <p class="muted">Latest received: ${dt(d.webhook.latest_received_at)} · ${statusBadge(d.webhook.latest_status || 'none')}</p>
            </div></div>`);
    };

    window.savePaymentSettings = async () => {
        const clear = [];
        if (document.getElementById('clearSecret').checked) clear.push('secret_key');
        if (document.getElementById('clearWebhookSecret').checked) clear.push('webhook_secret');
        const body = {
            provider: document.getElementById('payProvider').value,
            enabled: document.getElementById('payEnabled').checked,
            mode: document.getElementById('payMode').value,
            publishable_key: document.getElementById('payPublishable').value.trim(),
            secret_key: document.getElementById('paySecret').value.trim(),
            webhook_secret: document.getElementById('payWebhookSecret').value.trim(),
            currency: document.getElementById('payCurrency').value,
            points_min_topup: Number(document.getElementById('payPointsMin').value),
            points_max_topup: Number(document.getElementById('payPointsMax').value),
            point_price: Number(document.getElementById('payPointPrice').value),
            connect_enabled: document.getElementById('payConnectEnabled').checked,
            connect_country: document.getElementById('payConnectCountry').value.trim().toUpperCase(),
            connect_refresh_url: document.getElementById('payConnectRefresh').value.trim(),
            connect_return_url: document.getElementById('payConnectReturn').value.trim(),
            clear_secrets: clear,
        };
        try { await api('PUT', '/api/admin/payment-settings', body); toast('Payment settings saved'); await render.payments(); }
        catch(e){ toast(e.message,'err'); }
    };

    window.testPaymentConnection = async () => {
        try {
            const p = await api('POST', '/api/admin/payment-settings/test');
            const mode = p.data.livemode ? 'live' : 'test';
            toast(`Stripe connected (${mode} mode)`);
        } catch(e){ toast(e.message,'err'); }
    };

    render.settings = async () => {
        loading();
        const p = await api('GET', '/api/admin/settings');
        const rows = (p.data||[]).map(s => `
            <tr><td>${esc(s.group)}</td><td>${esc(s.label||s.key)}<div class="muted" style="font-size:11px">${esc(s.key)}</div></td>
            <td>${ s.type==='boolean'
                ? `<select data-key="${esc(s.key)}"><option value="1" ${s.value=='1'?'selected':''}>On</option><option value="0" ${s.value!='1'?'selected':''}>Off</option></select>`
                : `<input type="${s.type==='number'?'number':'text'}" data-key="${esc(s.key)}" value="${esc(s.value)}">` }</td>
            <td class="muted">${esc(s.type)}</td></tr>`).join('');
        view(`
            <div class="panel"><div class="panel-head"><h2>Platform settings</h2><button class="btn primary sm" onclick="saveSettings()">Save changes</button></div>
              <div class="table-wrap"><table><thead><tr><th>Group</th><th>Setting</th><th>Value</th><th>Type</th></tr></thead>
              <tbody id="settingsRows">${rows || '<tr><td colspan="4" class="empty">No settings.</td></tr>'}</tbody></table></div></div>`);
    };
    window.saveSettings = async () => {
        const settings = {};
        document.querySelectorAll('#settingsRows [data-key]').forEach(el => settings[el.dataset.key] = el.value);
        try { await api('POST', '/api/admin/settings', { settings }); toast('Settings saved'); }
        catch(e){ toast(e.message,'err'); }
    };

    /* ---------- Router ---------- */
    function buildNav(active) {
        document.getElementById('nav').innerHTML = SECTIONS.map(s =>
            `<a class="${s.id===active?'active':''}" href="#${s.id}"><span class="ic">${s.icon}</span> ${s.label}</a>`).join('');
    }
    async function route() {
        const id = (location.hash.replace('#','') || 'dashboard');
        const section = SECTIONS.find(s => s.id === id) || SECTIONS[0];
        buildNav(section.id);
        document.getElementById('pageTitle').textContent = section.label;
        document.getElementById('sidebar').classList.remove('open');
        try { await (render[section.id] || render.dashboard)(); }
        catch (e) { view(`<div class="panel"><div class="empty">${esc(e.message)}</div></div>`); }
    }

    /* ---------- Boot ---------- */
    (async () => {
        if (!token()) { location.replace('/admin/login'); return; }
        try {
            const me = await api('GET', '/api/me');
            if (me.data.role !== 'admin') { toast('Not an admin account','err'); return logout(); }
            document.getElementById('whoName').textContent = me.data.name;
            document.getElementById('whoAvatar').textContent = (me.data.name||'A').trim().charAt(0).toUpperCase();
        } catch (e) { return; } // api() already redirected on 401
        window.addEventListener('hashchange', route);
        if (!location.hash) location.hash = '#dashboard';
        route();
    })();
    </script>
</body>
</html>
