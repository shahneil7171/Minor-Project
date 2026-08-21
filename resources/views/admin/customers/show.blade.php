@extends('admin.layouts.panel')

@section('title', 'Customer — ' . $customer->name)

@section('content')
<style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; background: #080d1c; color: #e5e7eb; }
        .container { width: 96%; max-width: 1400px; margin: 30px auto; }
        h1 { margin-bottom: 6px; }
        .back { display: inline-flex; align-items: center; gap: 6px; margin-bottom: 18px; padding: 9px 16px; border-radius: 8px; background: #1e293b; color: #cbd5e1; text-decoration: none; font-weight: 600; font-size: 0.9rem; }
        .message { padding: 14px; border-radius: 8px; margin-bottom: 20px; background: #064e3b; color: #d1fae5; }
        .message.error { background: #7f1d1d; color: #fecaca; }
        .subtitle { color: #94a3b8; margin: 0 0 25px; }

        /* Profile card */
        .profile-card { display: flex; gap: 20px; align-items: center; flex-wrap: wrap; background: #111827; border: 1px solid #26304a; border-radius: 14px; padding: 22px 24px; margin-bottom: 25px; }
        .profile-card .avatar { width: 64px; height: 64px; border-radius: 50%; background: #1d4ed8; color: white; display: inline-flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.5rem; flex-shrink: 0; }
        .profile-meta { flex: 1; min-width: 240px; }
        .profile-meta h2 { margin: 0 0 4px; color: #fff; }
        .profile-meta .line { color: #cbd5e1; font-size: 0.92rem; margin-top: 3px; }
        .profile-meta .line span { color: #94a3b8; }
        .badge { display: inline-block; padding: 5px 12px; border-radius: 20px; font-size: 0.75rem; font-weight: bold; text-transform: capitalize; }
        .badge.active { background: #065f46; color: #6ee7b7; }
        .badge.inactive { background: #78350f; color: #fcd34d; }

        /* Status controls */
        .status-box { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .status-box form { display: inline-flex; gap: 8px; align-items: center; }
        .status-box select { padding: 8px 12px; border-radius: 8px; border: 1px solid #374151; background: #111827; color: #e5e7eb; font-weight: 600; cursor: pointer; }
        .status-box button { padding: 8px 16px; border: none; border-radius: 8px; background: #2563eb; color: white; font-weight: 700; cursor: pointer; }
        .btn-edit { display: inline-flex; padding: 9px 18px; border-radius: 8px; background: #4c1d95; color: white; text-decoration: none; font-weight: 700; font-size: 0.9rem; }

        /* Statistics */
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; margin-bottom: 25px; }
        .stat-card { background: #111827; border: 1px solid #26304a; border-radius: 12px; padding: 18px 20px; }
        .stat-card .label { color: #94a3b8; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px; }
        .stat-card .value { font-size: 1.6rem; font-weight: 800; color: #fff; }
        .stat-card.accent .value { color: #60a5fa; }

        /* Sections & tables */
        section { margin-bottom: 28px; }
        section > h2 { font-size: 1.15rem; color: #fff; margin: 0 0 14px; }
        .table-wrap { background: #111827; border: 1px solid #26304a; border-radius: 12px; padding: 8px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 14px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.06); }
        th { color: #94a3b8; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; }
        td.order-id strong { color: #7dd3fc; }
        .muted { color: #94a3b8; font-size: 0.8rem; }
        td.order-status .badge.pending { background: #78350f; color: #fcd34d; }
        td.order-status .badge.processing { background: #164e63; color: #7dd3fc; }
        td.order-status .badge.packed { background: #3730a3; color: #c7d2fe; }
        td.order-status .badge.shipped { background: #4c1d95; color: #c4b5fd; }
        td.order-status .badge.delivered { background: #065f46; color: #6ee7b7; }
        td.order-status .badge.cancelled { background: #7f1d1d; color: #fca5a5; }
        .row-actions { display: flex; gap: 6px; flex-wrap: wrap; }
        .row-actions a { display: inline-flex; align-items: center; padding: 6px 11px; border-radius: 7px; text-decoration: none; font-size: 0.78rem; font-weight: 700; color: white; background: #1e293b; border: 1px solid #26304a; }
        .row-actions a.primary { background: #2563eb; border-color: #2563eb; }
        .row-actions a.invoice { background: #065f46; border-color: #065f46; }

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
            .row-actions a { padding: 5px 8px; font-size: 0.72rem; }
            .profile-card { padding: 18px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <a class="back" href="{{ route('admin.customers.index') }}">← Back to customers</a>
        <h1>Customer Profile</h1>
        <p class="subtitle">Profile, statistics and complete order history.</p>

        @if (session('success'))
            <div class="message">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="message error">{{ session('error') }}</div>
        @endif

        <!-- Customer Profile -->
        <div class="profile-card">
            <span class="avatar">{{ strtoupper(substr($customer->name, 0, 1)) }}</span>
            <div class="profile-meta">
                <h2>{{ $customer->name }}</h2>
                <div class="line"><span>Email:</span> {{ $customer->email }}</div>
                <div class="line"><span>Phone:</span> {{ $customer->phone ?: '—' }}</div>
                <div class="line"><span>Registration Date:</span> {{ $customer->created_at->format('M d, Y h:i A') }}</div>
                <div class="line"><span>Status:</span>
                    <span class="badge {{ $customer->status }}">{{ $customer->statusLabel() }}</span>
                </div>
            </div>
            <div class="status-box">
                <a class="btn-edit" href="{{ route('admin.customers.edit', $customer) }}">Edit</a>
                <form method="POST" action="" id="status-form">
                    @csrf
                    <input type="hidden" name="status" id="status-input" value="{{ $customer->status }}">
                    <button type="button" id="status-open">Change Status</button>
                </form>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats">
            <div class="stat-card">
                <div class="label">Total Orders</div>
                <div class="value">{{ $statistics['total_orders'] }}</div>
            </div>
            <div class="stat-card">
                <div class="label">Total Amount Spent</div>
                <div class="value">&#8377;{{ number_format($statistics['total_spent'], 2) }}</div>
            </div>

        <!-- Recent Orders -->
        <section>
            <h2>Recent Orders</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentOrders as $order)
                            <tr>
                                <td class="order-id">
                                    <strong>#{{ $order->order_number }}</strong>
                                </td>
                                <td>
                                    {{ $order->created_at->format('M d, Y h:i A') }}
                                </td>
                                <td>&#8377;{{ number_format((float) $order->total, 2) }}</td>
                                <td class="order-status"><span class="badge {{ $order->status }}">{{ $order->statusLabel() }}</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" style="text-align:center; padding:30px; color:#94a3b8;">This customer has not placed any orders yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <!-- Order History -->
        <section>
            <h2>Order History</h2>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($orders as $order)
                            <tr>
                                <td class="order-id">
                                    <strong>#{{ $order->order_number }}</strong>
                                    <div class="muted">{{ $order->items->count() }} item(s)</div>
                                </td>
                                <td>
                                    {{ $order->created_at->format('M d, Y h:i A') }}
                                </td>
                                <td>&#8377;{{ number_format((float) $order->total, 2) }}</td>
                                <td class="order-status"><span class="badge {{ $order->status }}">{{ $order->statusLabel() }}</span></td>
                                <td>
                                    <div class="row-actions">
                                        <a class="primary" href="{{ route('orders.show', $order) }}">Open order</a>
                                        <a href="{{ route('orders.show', $order) }}#tracking">View tracking</a>
                                        <a class="invoice" href="{{ route('admin.orders.invoice', $order) }}">View invoice</a>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" style="text-align:center; padding:30px; color:#94a3b8;">No orders in history.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($orders->hasPages())
                <div class="pagination">
                    {{ $orders->links() }}
                </div>
            @endif
        </section>
    </div>

            <div class="stat-card">
                <div class="label">Wishlist Items</div>
                <div class="value">{{ $statistics['wishlist_items'] }}</div>
            </div>

    <!-- Status change confirmation modal -->
    <div class="modal-backdrop" id="confirm-modal" role="dialog" aria-modal="true" aria-labelledby="confirm-title">
        <div class="modal">
            <h3 id="confirm-title">Change account status</h3>
            <p id="confirm-text">Select the new status for {{ $customer->name }}.</p>
            <form method="POST" id="confirm-form" action="{{ route('admin.customers.status', $customer) }}">
                @csrf
                <div style="margin-bottom:18px;">
                    <select id="confirm-status" name="status" style="width:100%; padding:10px 12px; border-radius:8px; border:1px solid #374151; background:#0b1120; color:#e5e7eb; font-weight:600;">
                        @foreach (\App\Models\User::STATUSES as $statusOption)
                            <option value="{{ $statusOption }}" {{ $customer->status === $statusOption ? 'selected' : '' }}>
                                {{ \App\Models\User::STATUS_LABELS[$statusOption] }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="modal-actions">
                    <button type="button" class="cancel" id="confirm-cancel">Cancel</button>
                    <button type="submit" class="confirm-danger" id="confirm-submit">Confirm Change</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        (function () {
            var modal = document.getElementById('confirm-modal');
            var openButton = document.getElementById('status-open');
            var cancelButton = document.getElementById('confirm-cancel');
            var statusSelect = document.getElementById('confirm-status');
            var submitButton = document.getElementById('confirm-submit');

            openButton.addEventListener('click', function () {
                modal.classList.add('open');
            });

            function closeModal() { modal.classList.remove('open'); }

            cancelButton.addEventListener('click', closeModal);
            modal.addEventListener('click', function (event) {
                if (event.target === modal) { closeModal(); }
            });
            document.addEventListener('keydown', function (event) {
                if (event.key === 'Escape') { closeModal(); }
            });

            // Red confirm button when the selected status restricts the account.
            statusSelect.addEventListener('change', function () {
                submitButton.className = statusSelect.value === 'active' ? 'confirm-success' : 'confirm-danger';
            });
        })();
    </script>
@endsection
