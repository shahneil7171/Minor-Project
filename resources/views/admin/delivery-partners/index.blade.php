@extends('admin.layouts.panel')
@include('admin.partials.page-styles')

@section('title', 'Delivery Partners')

@section('content')
    <div class="container" style="width:96%; max-width:1300px; margin:30px auto;">
        <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:20px;">
            <h1 style="margin:0;">Delivery Partners</h1>
            <a href="{{ route('admin.delivery-partners.create') }}" style="padding:10px 18px; border-radius:8px; background:#2563eb; color:#fff; text-decoration:none; font-weight:700;">+ New Delivery Partner</a>
        </div>

        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:12px; margin-bottom:22px;">
            <div style="border:1px solid #26304a; border-radius:10px; padding:12px 14px; background:#111827;">
                <div style="color:#94a3b8; font-size:.72rem; text-transform:uppercase; letter-spacing:.07em; margin-bottom:6px;">Total</div>
                <div style="font-size:1.25rem; font-weight:800; color:#60a5fa;">{{ $stats['total'] }}</div>
            </div>
            <div style="border:1px solid #26304a; border-radius:10px; padding:12px 14px; background:#111827;">
                <div style="color:#94a3b8; font-size:.72rem; text-transform:uppercase; letter-spacing:.07em; margin-bottom:6px;">Active</div>
                <div style="font-size:1.25rem; font-weight:800; color:#34d399;">{{ $stats['active'] }}</div>
            </div>
            <div style="border:1px solid #26304a; border-radius:10px; padding:12px 14px; background:#111827;">
                <div style="color:#94a3b8; font-size:.72rem; text-transform:uppercase; letter-spacing:.07em; margin-bottom:6px;">With Deliveries</div>
                <div style="font-size:1.25rem; font-weight:800; color:#fbbf24;">{{ $stats['with_deliveries'] }}</div>
            </div>
        </div>

        <form method="GET" action="{{ route('admin.delivery-partners.index') }}" style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:18px;">
            <input type="text" name="search" value="{{ $search }}" placeholder="Search by name, email or phone" style="padding:9px 12px; border-radius:8px; border:1px solid #26304a; background:#111827; color:#e5e7eb; min-width:260px;">
            <select name="status" style="padding:9px 12px; border-radius:8px; border:1px solid #26304a; background:#111827; color:#e5e7eb;">
                @foreach (['all' => 'All statuses', 'active' => 'Active', 'inactive' => 'Inactive', 'blocked' => 'Blocked'] as $value => $label)
                    <option value="{{ $value }}" {{ $status === $value ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
            <button type="submit" style="padding:9px 18px; border:none; border-radius:8px; background:#2563eb; color:#fff; font-weight:700;">Filter</button>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Name</th><th>Email</th><th>Phone</th><th>Status</th><th>Deliveries</th><th>Joined</th><th></th></tr>
                </thead>
                <tbody>
                    @forelse ($partners as $partner)
                        <tr>
                            <td><strong>{{ $partner->name }}</strong></td>
                            <td>{{ $partner->email }}</td>
                            <td>{{ $partner->phone ?? '—' }}</td>
                            <td><span class="badge {{ $partner->status === 'active' ? 'delivered' : 'cancelled' }}">{{ $partner->status }}</span></td>
                            <td>{{ $partner->delivery_assignments_count }}</td>
                            <td style="font-size:.8rem; color:#94a3b8;">{{ $partner->created_at->format('M d, Y') }}</td>
                            <td class="actions">
                                <a href="{{ route('admin.delivery-partners.show', $partner) }}">View</a>
                                <a href="{{ route('admin.delivery-partners.edit', $partner) }}" style="background:#1e293b;">Edit</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" style="text-align:center; padding:30px; color:#94a3b8;">No delivery partners yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
