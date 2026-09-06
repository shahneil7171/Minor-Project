<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Operational Staff area: a limited, read-only operations dashboard.
 *
 * Staff members are NOT admins. This area is reached through the dedicated
 * "staff" middleware (EnsureStaff) and exposes no admin panel, product,
 * category, coupon, user or role management. Every screen here is read-only
 * operational information; account types can only ever be changed from the
 * admin panel by an administrator.
 */
class StaffController extends Controller
{
    /**
     * Staff dashboard: operational counters plus the most recent orders.
     */
    public function dashboard(Request $request)
    {
        $stats = [
            'total_orders'     => Order::count(),
            'pending_orders'   => Order::where('status', 'pending')->count(),
            'active_orders'    => Order::whereIn('status', ['approved', 'processing', 'packed', 'shipped'])->count(),
            'delivered_orders' => Order::where('status', 'delivered')->count(),
            'customers'        => User::query()
                ->whereNotIn('account_type', ['admin', 'manager', 'delivery_partner', 'staff'])
                ->count(),
        ];

        $recentOrders = Order::query()->with('user')->latest()->take(8)->get();

        return view('staff.dashboard', compact('stats', 'recentOrders'));
    }

    /**
     * Read-only order list for operational staff (?status= filter).
     */
    public function orders(Request $request)
    {
        $status = $request->get('status', 'all');

        $query = Order::query()->with('user');

        if (in_array($status, Order::STATUSES, true)) {
            $query->where('status', $status);
        } else {
            $status = 'all';
        }

        $orders = $query->latest()->paginate(10);
        $orders->appends($request->query());

        return view('staff.orders.index', compact('orders', 'status'));
    }
}
