<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Orders | KDP MART</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Arial, sans-serif; background: #080d1c; color: #e5e7eb; }
        .container { width: 96%; max-width: 1400px; margin: 30px auto; }
        h1 { margin-bottom: 25px; }
        .message { padding: 14px; border-radius: 8px; margin-bottom: 20px; background: #064e3b; color: #d1fae5; }
        .message.error { background: #7f1d1d; color: #fecaca; }
        .filters { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; margin-bottom: 25px; }
        .filters a { padding: 9px 16px; border-radius: 7px; text-decoration: none; color: white; background: #1e293b; }
        .filters a.active { background: #2563eb; }
        .table-wrap { background: #111827; border: 1px solid #26304a; border-radius: 12px; padding: 8px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px 14px; text-align: left; border-bottom: 1px solid rgba(255,255,255,0.06); }
        th { color: #94a3b8; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; }
        td .badge { display: inline-block; padding: 5px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: bold; text-transform: capitalize; }
        .badge.pending { background: #78350f; color: #fcd34d; }
        .badge.processing { background: #164e63; color: #7dd3fc; }
        .badge.packed { background: #3730a3; color: #c7d2fe; }
        .badge.shipped { background: #4c1d95; color: #c4b5fd; }
        .badge.delivered { background: #065f46; color: #6ee7b7; }
        .badge.cancelled { background: #7f1d1d; color: #fca5a5; }
        .user { color: #cbd5e1; font-size: 0.85rem; }
        .actions a { display: inline-flex; align-items: center; justify-content: center; padding: 7px 13px; border-radius: 8px; text-decoration: none; font-size: 0.85rem; font-weight: 700; color: white; background: #2563eb; margin-right: 6px; }
        .actions form { display: inline-flex; gap: 6px; align-items: center; }
        .actions select { padding: 7px 10px; border-radius: 8px; border: 1px solid #374151; background: #111827; color: #e5e7eb; font-weight: 600; cursor: pointer; }
        .actions button { padding: 7px 13px; border: none; border-radius: 8px; background: #10b981; color: white; font-weight: 700; cursor: pointer; }
        .actions button:disabled { opacity: 0.5; cursor: not-allowed; }
        .pagination { margin-top: 20px; display: flex; gap: 8px; justify-content: center; flex-wrap: wrap; }
        .pagination a, .pagination span { padding: 8px 13px; border-radius: 8px; text-decoration: none; font-weight: 700; color: #e2e8f0; background: rgba(255,255,255,0.07); border: 1px solid rgba(255,255,255,0.14); }
        .pagination .current { background: #2563eb; border-color: #2563eb; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Manage Orders</h1>

        @if (session('success'))
            <div class="message">{{ session('success') }}</div>
        @endif
        @if (session('error'))
            <div class="message error">{{ session('error') }}</div>
        @endif

        <div class="filters">
            <a href="{{ route('admin.orders.index') }}" class="{{ $status === 'all' ? 'active' : '' }}">All</a>
            @foreach ($statuses as $s)
                <a href="{{ route('admin.orders.index', ['status' => $s]) }}" class="{{ $status === $s ? 'active' : '' }}">{{ $statusLabels[$s] }}</a>
            @endforeach
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Customer</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($orders as $order)
                        <tr>
                            <td>
                                <strong>#{{ $order->order_number }}</strong>
                                <div style="color:#94a3b8; font-size:0.8rem;">{{ $order->created_at->format('M d, Y h:i A') }}</div>
                            </td>
                            <td>
                                <strong>{{ $order->user->name }}</strong>
                                <div class="user">{{ $order->user->email }}</div>
                            </td>
                            <td>{{ $order->items->sum('quantity') }}</td>
                            <td>{{ '$' . number_format((float) $order->total, 2) }}</td>
                            <td><span class="badge {{ $order->status }}">{{ $order->status }}</span></td>
                            <td class="actions">
                                <a href="{{ route('orders.show', $order) }}">View</a>
                                <form method="POST" action="{{ route('admin.orders.status', $order) }}" class="status-form">
                                    @csrf
                                    <select name="status">
                                        @foreach ($statuses as $s)
                                            <option value="{{ $s }}" {{ $order->status === $s ? 'selected' : '' }}>{{ $statusLabels[$s] }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit">Update</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="text-align:center; padding:30px; color:#94a3b8;">No orders found.</td>
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
    </div>

    <script>
        // Disable status options that are not valid transitions from the order's
        // current status so admins get immediate feedback before submitting.
        document.querySelectorAll('.status-form').forEach(function (form) {
            var select = form.querySelector('select');
            var current = select.value;
            var transitions = {
                'pending':    ['processing', 'packed', 'shipped', 'delivered', 'cancelled'],
                'processing': ['packed', 'shipped', 'delivered', 'cancelled'],
                'packed':     ['shipped', 'delivered', 'cancelled'],
                'shipped':    ['delivered'],
                'delivered':  [],
                'cancelled':  []
            };
            Array.prototype.forEach.call(select.options, function (option) {
                var from = current;
                var allowed = transitions[from] || [];
                if (option.value !== from && allowed.indexOf(option.value) === -1) {
                    option.disabled = true;
                }
            });
        });
    </script>
</body>
</html>