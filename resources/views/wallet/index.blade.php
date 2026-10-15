<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Coins | KDP MART</title>
    <style>
        :root { color-scheme: dark; font-family: Inter, Arial, sans-serif; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; background: linear-gradient(135deg, #020617 0%, #111827 45%, #1d4ed8 100%); color: #f8fafc; padding: 24px; }
        .container { max-width: 920px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 20px; flex-wrap: wrap; }
        .header h1 { margin: 0 0 4px; font-size: 1.6rem; }
        .header p { margin: 0; color: #cbd5e1; }
        .header a { display: inline-flex; align-items: center; padding: 10px 14px; border-radius: 10px; text-decoration: none; font-weight: 700; color: white; background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.16); }
        .balance { border-radius: 20px; padding: 26px; background: linear-gradient(135deg, rgba(37,99,235,0.22), rgba(16,185,129,0.16)); border: 1px solid rgba(255,255,255,0.16); margin-bottom: 20px; }
        .balance .label { color: #cbd5e1; font-size: .8rem; text-transform: uppercase; letter-spacing: .08em; }
        .balance .amount { font-size: 2.4rem; font-weight: 900; margin: 6px 0 2px; }
        .balance .value { color: #a7f3d0; font-size: .9rem; }
        .cards { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 22px; }
        .card { border-radius: 14px; padding: 16px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.12); }
        .card .k { color: #94a3b8; font-size: .78rem; }
        .card .v { font-size: 1.25rem; font-weight: 800; margin-top: 4px; }
        .panel { border-radius: 18px; padding: 20px; background: rgba(2,6,23,0.7); border: 1px solid rgba(255,255,255,0.12); }
        .panel h2 { margin: 0 0 14px; font-size: 1.05rem; }
        table { width: 100%; border-collapse: collapse; font-size: .88rem; }
        th, td { text-align: left; padding: 10px 8px; border-bottom: 1px solid rgba(255,255,255,0.08); }
        th { color: #94a3b8; font-size: .72rem; text-transform: uppercase; letter-spacing: .06em; }
        .credit { color: #6ee7b7; font-weight: 800; }
        .debit { color: #fca5a5; font-weight: 800; }
        .badge { display: inline-block; padding: 3px 9px; border-radius: 999px; font-size: .68rem; font-weight: 700; background: rgba(255,255,255,0.08); }
        .note { color: #94a3b8; font-size: .8rem; margin-top: 14px; line-height: 1.6; }
        .pager { margin-top: 16px; }
        @media (max-width: 620px) { .cards { grid-template-columns: repeat(2, 1fr); } }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1>Smart Coins</h1>
                <p>Your store-credit wallet. 1 Smart Coin = ₹{{ number_format($rate, 2) }}.</p>
            </div>
            <a href="{{ route('orders.index') }}">&larr; My Orders</a>
        </div>

        <div class="balance">
            <div class="label">Available balance</div>
            <div class="amount">{{ number_format($balance, 2) }} coins</div>
            <div class="value">Worth ₹{{ number_format($coinValue, 2) }} in store credit</div>
        </div>

        <div class="cards">
            <div class="card"><div class="k">Total earned</div><div class="v">{{ number_format((float) $wallet->total_earned, 2) }}</div></div>
            <div class="card"><div class="k">Total spent</div><div class="v">{{ number_format((float) $wallet->total_spent, 2) }}</div></div>
            <div class="card"><div class="k">Refund credits</div><div class="v">{{ number_format((float) $wallet->total_refund_credited, 2) }}</div></div>
            <div class="card"><div class="k">Transactions</div><div class="v">{{ $wallet->transactions()->count() }}</div></div>
        </div>


        <div class="panel">
            <h2>Recent transactions</h2>
            <table>
                <thead>
                    <tr>
                        <th>Date</th><th>Type</th><th>Description</th><th>Reference</th><th>Amount</th><th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($transactions as $tx)
                        <tr>
                            <td>{{ $tx->created_at->format('M d, Y') }}</td>
                            <td>{{ ucwords(str_replace('_', ' ', $tx->type)) }}</td>
                            <td>{{ $tx->description ?: '—' }}</td>
                            <td>
                                @if ($tx->reference_type === 'order')
                                    Order #{{ $tx->reference_id }}
                                @elseif ($tx->reference_type === 'refund')
                                    Refund #{{ $tx->reference_id }}
                                @elseif ($tx->reference_type === 'admin')
                                    Admin adjustment
                                @else
                                    —
                                @endif
                            </td>
                            <td class="{{ $tx->direction }}">{{ $tx->direction === 'credit' ? '+' : '−' }}{{ number_format((float) $tx->amount, 2) }}</td>
                            <td><span class="badge">{{ ucfirst($tx->status) }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" style="color:#94a3b8;">No transactions yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="pager">{{ $transactions->links() }}</div>
            <p class="note">
                Smart Coins are internal store credit, not a bank balance and not cash. Coins received from an
                approved refund never expire. Redemptions and refunds are applied automatically and cannot be edited here.
            </p>
        </div>
    </div>
</body>
</html>
