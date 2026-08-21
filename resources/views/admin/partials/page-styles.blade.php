@push('styles')
<style>
    .page-head { display:flex; justify-content:space-between; align-items:center; gap:14px; flex-wrap:wrap; margin-bottom:22px; }
    .page-head h2 { margin:0; color:#fff; font-size:1.35rem; }
    .page-head p { margin:4px 0 0; color:var(--ka-muted); font-size:.88rem; }
    .btn { display:inline-flex; align-items:center; gap:8px; padding:9px 16px; border-radius:9px; border:none; background:var(--ka-primary); color:#fff; font-weight:700; font-size:.86rem; cursor:pointer; text-decoration:none; }
    .btn:hover { filter:brightness(1.12); }
    .btn.gray { background:#334155; } .btn.green { background:#0d9488; } .btn.red { background:var(--ka-danger); } .btn.purple { background:#6d28d9; }
    .card { background:var(--ka-panel); border:1px solid var(--ka-border); border-radius:12px; padding:18px 20px; margin-bottom:20px; }
    .card h3 { margin:0 0 14px; color:#fff; font-size:1.02rem; }
    .grid-2 { display:grid; grid-template-columns:repeat(auto-fit,minmax(300px,1fr)); gap:16px; }
    .table-wrap { background:var(--ka-panel); border:1px solid var(--ka-border); border-radius:12px; padding:6px; overflow-x:auto; margin-bottom:20px; }
    .table-wrap table { width:100%; border-collapse:collapse; }
    .table-wrap th, .table-wrap td { padding:11px 13px; text-align:left; border-bottom:1px solid rgba(255,255,255,.06); font-size:.9rem; }
    .table-wrap th { color:var(--ka-muted); text-transform:uppercase; font-size:.72rem; letter-spacing:.06em; }
    .table-wrap td strong.num, .num { text-align:right; }
    th.num, td.num { text-align:right; }
    .badge { display:inline-block; padding:4px 11px; border-radius:999px; font-size:.72rem; font-weight:800; text-transform:capitalize; }
    .badge.active,.badge.approved,.badge.completed,.badge.delivered,.badge.enabled { background:#065f46; color:#6ee7b7; }
    .badge.pending,.badge.inactive,.badge.requested,.badge.disabled { background:#78350f; color:#fcd34d; }
    .badge.rejected,.badge.blocked,.badge.cancelled { background:#7f1d1d; color:#fca5a5; }
    .badge.processing,.badge.packed,.badge.shipped { background:#3730a3; color:#c7d2fe; }
    .field { margin-bottom:13px; }
    .field label { display:block; margin-bottom:6px; color:var(--ka-muted); font-size:.8rem; font-weight:700; }
    .field input[type=text],.field input[type=email],.field input[type=password],.field input[type=number],.field input[type=date],.field input[type=url],.field select,.field textarea { width:100%; padding:10px 12px; border-radius:9px; border:1px solid #374151; background:#0b1120; color:var(--ka-text); font-family:inherit; font-size:.9rem; }
    .field .hint { color:#64748b; font-size:.75rem; margin-top:4px; }
    .form-actions { display:flex; gap:10px; margin-top:16px; }
    .filters { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:18px; }
    .filters a { padding:8px 14px; border-radius:8px; background:#1e293b; color:#cbd5e1; text-decoration:none; font-size:.84rem; font-weight:700; }
    .filters a.active { background:var(--ka-primary); color:#fff; }
    .pagination { margin-top:18px; display:flex; gap:8px; justify-content:center; flex-wrap:wrap; }
    .pagination a, .pagination span { padding:8px 13px; border-radius:8px; text-decoration:none; font-weight:700; color:#e2e8f0; background:rgba(255,255,255,.07); border:1px solid rgba(255,255,255,.14); }
    .pagination .current { background:var(--ka-primary); border-color:var(--ka-primary); }
    .empty { text-align:center; padding:26px; color:var(--ka-muted); }
    .row-actions { display:flex; gap:6px; flex-wrap:wrap; }
    .row-actions a, .row-actions button { display:inline-flex; padding:6px 11px; border-radius:7px; font-size:.76rem; font-weight:700; color:#fff; background:#1e293b; border:1px solid var(--ka-border); text-decoration:none; cursor:pointer; }
    .row-actions a.primary { background:var(--ka-primary); }
    .row-actions a.green, .row-actions button.green { background:#0d9488; }
    .row-actions a.red, .row-actions button.red { background:var(--ka-danger); }
</style>
@endpush