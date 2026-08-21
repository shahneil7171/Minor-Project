@extends('admin.layouts.panel')

@section('title', 'Customers')

@section('content')
<style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; background: #080d1c; color: #e5e7eb; }
        .container { width: 96%; max-width: 1400px; margin: 30px auto; }
        h1 { margin-bottom: 25px; }
        .message { padding: 14px; border-radius: 8px; margin-bottom: 20px; background: #064e3b; color: #d1fae5; }
        .message.error { background: #7f1d1d; color: #fecaca; }

        /* Statistics cards */
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; margin-bottom: 25px; }
        .stat-card { background: #111827; border: 1px solid #26304a; border-radius: 12px; padding: 18px 20px; }
        .stat-card .label { color: #94a3b8; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px; }
        .stat-card .value { font-size: 1.9rem; font-weight: 800; color: #fff; }
        .stat-card.accent .value { color: #60a5fa; }

        /* Search */
        .search-bar { display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 18px; }
        .search-bar input[type="text"] { flex: 1; min-width: 220px; padding: 10px 14px; border-radius: 8px; border: 1px solid #374151; background: #111827; color: #e5e7eb; font-size: 0.95rem; }
        .search-bar select { padding: 10px 12px; border-radius: 8px; border: 1px solid #374151; background: #111827; color: #e5e7eb; font-weight: 600; cursor: pointer; }
        .search-bar button { padding: 10px 20px; border: none; border-radius: 8px; background: #2563eb; color: white; font-weight: 700; cursor: pointer; }
        .search-bar a.reset { display: inline-flex; align-items: center; padding: 10px 16px; border-radius: 8px; background: #1e293b; color: #cbd5e1; text-decoration: none; font-weight: 600; }

        /* Filters */
        .filters { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; margin-bottom: 25px; }
        .filters .group-label { color: #94a3b8; font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.05em; margin-right: 2px; }
        .filters a { padding: 9px 16px; border-radius: 7px; text-decoration: none; color: white; background: #1e293b; }
        .filters a.active { background: #2563eb; }
        .filters .divider { width: 1px; height: 26px; background: #26304a; margin: 0 4px; }

        .table-wrap { background: #111827; border: 1px solid #26304a; border-radius: 12px; padding: 8px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 14px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.06); }
        th { color: #94a3b8; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; }
        td .badge { display: inline-block; padding: 5px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: bold; text-transform: capitalize; }
        .badge.active { background: #065f46; color: #6ee7b7; }
        .badge.inactive { background: #78350f; color: #fcd34d; }
        .badge.blocked { background: #7f1d1d; color: #fca5a5; }
        .orders-count { display: inline-block; min-width: 34px; text-align: center; padding: 5px 10px; border-radius: 20px; background: #164e63; color: #7dd3fc; font-weight: bold; font-size: 0.8rem; text-decoration: none; }
        .customer-cell { display: flex; align-items: center; gap: 10px; }
        .avatar { width: 34px; height: 34px; border-radius: 50%; background: #1d4ed8; color: white; display: inline-flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.85rem; flex-shrink: 0; }
        .muted { color: #94a3b8; font-size: 0.8rem; }

        .actions { white-space: nowrap; }
        .actions a, .actions button { display: inline-flex; align-items: center; justify-content: center; padding: 7px 13px; border-radius: 8px; text-decoration: none; font-size: 0.85rem; font-weight: 700; color: white; margin-right: 6px; border: none; cursor: pointer; }
        .actions a.view { background: #2563eb; }
        .actions a.edit { background: #4c1d95; }
        .actions button.disable { background: #b91c1c; }
        .actions button.enable { background: #10b981; }

        .pagination { margin-top: 20px; display: flex; gap: 8px; justify-content: center; flex-wrap: wrap; }
        .pagination a, .pagination span { padding: 8px 13px; border-radius: 8px; text-decoration: none; font-weight: 700; color: #e2e8f0; background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.14); }
        .pagination .current { background: #2563eb; border-color: #2563eb; }

        /* Confirmation modal */
        .modal-backdrop { display: none; position: fixed; inset: 0; background: rgba(2, 6, 23, 0.8); z-index: 50; align-items: center; justify-content: center; padding: 20px; }
        .modal-backdrop.open { display: flex; }
        .modal { background: #111827; border: 1px solid #26304a; border-radius: 14px; padding: 26px; width: 100%; max-width: 440px; box-shadow: 0 24px 50px rgba(0,0,0,0.5); }
        .modal h3 { margin: 0 0 10px; color: #fff; }
        .modal p { margin: 0 0 20px; color: #cbd5e1; line-height: 1.5; }
        .modal-actions { display: flex; justify-content: flex-end; gap: 10px; }
        .modal-actions button { padding: 9px 18px; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; color: white; }
        .modal-actions .cancel { background: #374151; }
        .modal-actions .confirm-danger { background: #b91c1c; }
        .modal-actions .confirm-success { background: #10b981; }

        @media (max-width: 640px) {
            th, td { padding: 10px 8px; }
            .actions a, .actions button { padding: 6px 9px; margin-right: 3px; font-size: 0.78rem; }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Customers</h1>

        @if (session('success'))
            <div class="message">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="message error">{{ session('error') }}</div>
        @endif

        <!-- Statistics Cards -->
        <div class="stats">
            <div class="stat-card">
                <div class="label">Total Customers</div>
                <div class="value">{{ $stats['total'] }}</div>
            </div>
            <div class="stat-card">
                <div class="label">Active Customers</div>
                <div class="value">{{ $stats['active'] }}</div>
            </div>
            <div class="stat-card">
                <div class="label">Customers This Month</div>
                <div class="value">{{ $stats['this_month'] }}</div>
            </div>
            <div class="stat-card accent">
                <div class="label">Customers With Orders</div>
                <div class="value">{{ $stats['with_orders'] }}</div>
            </div>
        </div>

        <!-- Search -->
        <form method="GET" action="{{ route('admin.customers.index') }}" class="search-bar">
            <input type="text" name="search" value="{{ $search }}" placeholder="Search customers..." aria-label="Search customers">
            <select name="search_field" aria-label="Search field">
                <option value="all" {{ $searchField === 'all' ? 'selected' : '' }}>All fields</option>
                <option value="name" {{ $searchField === 'name' ? 'selected' : '' }}>Name</option>
                <option value="email" {{ $searchField === 'email' ? 'selected' : '' }}>Email</option>
                <option value="phone" {{ $searchField === 'phone' ? 'selected' : '' }}>Phone</option>
            </select>
            <button type="submit">Search</button>
            <a href="{{ route('admin.customers.index') }}" class="reset">Reset</a>
        </form>

        <!-- Filters -->
        <div class="filters">
            <span class="group-label">Status</span>
            <a href="{{ request()->fullUrlWithQuery(['status' => 'all']) }}" class="{{ $status === 'all' ? 'active' : '' }}">All</a>
            <a href="{{ request()->fullUrlWithQuery(['status' => 'active']) }}" class="{{ $status === 'active' ? 'active' : '' }}">Active</a>
            <a href="{{ request()->fullUrlWithQuery(['status' => 'inactive']) }}" class="{{ $status === 'inactive' ? 'active' : '' }}">Inactive</a>
            <a href="{{ request()->fullUrlWithQuery(['status' => 'blocked']) }}" class="{{ $status === 'blocked' ? 'active' : '' }}">Disabled</a>
            <span class="divider"></span>
            <span class="group-label">Orders</span>
            <a href="{{ request()->fullUrlWithQuery(['orders' => 'all']) }}" class="{{ $ordersFilter === 'all' ? 'active' : '' }}">All</a>
            <a href="{{ request()->fullUrlWithQuery(['orders' => 'with']) }}" class="{{ $ordersFilter === 'with' ? 'active' : '' }}">With orders</a>
            <a href="{{ request()->fullUrlWithQuery(['orders' => 'without']) }}" class="{{ $ordersFilter === 'without' ? 'active' : '' }}">Without orders</a>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Orders</th>
                        <th>Joined</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($customers as $customer)
                        <tr>
                            <td>
                                <div class="customer-cell">
                                    <span class="avatar">{{ strtoupper(substr($customer->name, 0, 1)) }}</span>
                                    <strong>{{ $customer->name }}</strong>
                                </div>
                            </td>
                            <td>{{ $customer->email }}</td>
                            <td>{{ $customer->phone ?: '—' }}</td>
                            <td>
                                <a class="orders-count" href="{{ route('admin.customers.show', $customer) }}" title="View orders">{{ $customer->orders_count }}</a>
                            </td>
                            <td>
                                {{ $customer->created_at->format('M d, Y') }}
                                <div class="muted">{{ $customer->created_at->diffForHumans() }}</div>
                            </td>
                            <td><span class="badge {{ $customer->status }}">{{ $customer->statusLabel() }}</span></td>
                            <td class="actions">
                                <a class="view" href="{{ route('admin.customers.show', $customer) }}">View</a>
                                <a class="edit" href="{{ route('admin.customers.edit', $customer) }}">Edit</a>
                                @if ($customer->status === 'blocked')
                                    <button type="button" class="enable"
                                        data-action="{{ route('admin.customers.status', $customer) }}"
                                        data-status="active"
                                        data-message="Enable the account of {{ $customer->name }}? They will be able to sign in and place orders again."
                                        data-confirm-class="confirm-success">Enable Account</button>
                                @else
                                    <button type="button" class="disable"
                                        data-action="{{ route('admin.customers.status', $customer) }}"
                                        data-status="blocked"
                                        data-message="Disable the account of {{ $customer->name }}? They will be signed out and will no longer be able to sign in. Their orders and history are kept."
                                        data-confirm-class="confirm-danger">Disable Account</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="text-align:center; padding:30px; color:#94a3b8;">No customers found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($customers->hasPages())
            <div class="pagination">
                {{ $customers->links() }}
            </div>
        @endif
    </div>

    <!-- Status change confirmation modal -->
    <div class="modal-backdrop" id="confirm-modal" role="dialog" aria-modal="true" aria-labelledby="confirm-title">
        <div class="modal">
            <h3 id="confirm-title">Change account status</h3>
            <p id="confirm-text"></p>
            <form method="POST" id="confirm-form" action="">
                @csrf
                <input type="hidden" name="status" id="confirm-status">
                <div class="modal-actions">
                    <button type="button" class="cancel" id="confirm-cancel">Cancel</button>
                    <button type="submit" class="confirm-danger" id="confirm-submit">Confirm</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function () {
            var modal = document.getElementById('confirm-modal');
            var form = document.getElementById('confirm-form');
            var statusInput = document.getElementById('confirm-status');
            var text = document.getElementById('confirm-text');
            var submit = document.getElementById('confirm-submit');
            var cancel = document.getElementById('confirm-cancel');

            document.querySelectorAll('[data-action]').forEach(function (button) {
                button.addEventListener('click', function () {
                    form.action = button.getAttribute('data-action');
                    statusInput.value = button.getAttribute('data-status');
                    text.textContent = button.getAttribute('data-message');
                    submit.className = button.getAttribute('data-confirm-class');
                    modal.classList.add('open');
                });
            });

            function closeModal() { modal.classList.remove('open'); }

            cancel.addEventListener('click', closeModal);
            modal.addEventListener('click', function (event) {
                if (event.target === modal) { closeModal(); }
            });
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') { closeModal(); }
            });
        })();
    </script>
@endsection

