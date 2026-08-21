<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminCustomersController extends Controller
{
    /**
     * Display every customer (admin only) with statistics,
     * search and filters.
     */
    public function index(Request $request)
    {
        $this->authorizeAdmin();

        $status = $request->get('status', 'all');          // active | inactive | blocked | all
        $ordersFilter = $request->get('orders', 'all');    // with | without | all
        $search = trim((string) $request->get('search', ''));
        $searchField = $request->get('search_field', 'all'); // name | email | phone | all

        // Customers are the existing users; admin accounts are staff, not customers.
        $query = User::query()
            ->where('account_type', '!=', 'admin')
            ->withCount('orders');

        if ($status !== 'all' && in_array($status, User::STATUSES, true)) {
            $query->where('status', $status);
        }

        if ($ordersFilter === 'with') {
            $query->has('orders');
        } elseif ($ordersFilter === 'without') {
            $query->doesntHave('orders');
        }

        if ($search !== '') {
            $field = in_array($searchField, ['name', 'email', 'phone'], true) ? $searchField : 'all';
            $query->search($search, $field);
        }

        $customers = $query->latest()->paginate(15);
        $customers->appends($request->query());

        $base = User::query()->where('account_type', '!=', 'admin');

        $stats = [
            'total'       => (clone $base)->count(),
            'active'      => (clone $base)->where('status', 'active')->count(),
            'this_month'  => (clone $base)->where('created_at', '>=', now()->startOfMonth())->count(),
            'with_orders' => (clone $base)->has('orders')->count(),
        ];

        return view('admin.customers.index', compact(
            'customers',
            'stats',
            'status',
            'ordersFilter',
            'search',
            'searchField'
        ));
    }

    /**
     * Show a customer's profile, statistics and order history (admin only).
     */
    public function show(Request $request, User $customer)
    {
        $this->authorizeAdmin();
        $this->abortIfStaff($customer);

        $statistics = [
            'total_orders'    => $customer->orders()->count(),
            // Cancelled orders were never kept, so they don't count as spent money.
            'total_spent'     => (float) $customer->orders()->where('status', '!=', 'cancelled')->sum('total'),
            'wishlist_items'  => $customer->wishlistItems()->count(),
            'reviews_written' => $customer->reviews()->count(),
        ];

        $recentOrders = $customer->orders()->with('items')->latest()->take(5)->get();

        $orders = $customer->orders()->with('items')->latest()->paginate(10);
        $orders->appends($request->query());

        return view('admin.customers.show', compact('customer', 'statistics', 'recentOrders', 'orders'));
    }

    /**
     * Show the form to edit a customer (admin only).
     */
    public function edit(Request $request, User $customer)
    {
        $this->authorizeAdmin();
        $this->abortIfStaff($customer);

        return view('admin.customers.edit', compact('customer'));
    }

    /**
     * Update a customer's profile (admin only).
     */
    public function update(Request $request, User $customer)
    {
        $this->authorizeAdmin();
        $this->abortIfStaff($customer);

        $data = $request->validate([
            'name'   => ['required', 'string', 'max:255'],
            'email'  => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($customer->id)],
            'phone'  => ['nullable', 'string', 'max:20'],
            'status' => ['required', Rule::in(User::STATUSES)],
        ]);

        $customer->update($data);

        // Deactivated or blocked customers must not keep live sessions.
        if (! $customer->isActive()) {
            $this->endCustomerSessions($customer);
        }

        return redirect()
            ->route('admin.customers.show', $customer)
            ->with('success', 'Customer updated successfully.');
    }

    /**
     * Change a customer's account status (admin only).
     *
     * The UI asks for confirmation before submitting; the change itself is a
     * simple validated update so no order/wishlist/review data is touched.
     */
    public function updateStatus(Request $request, User $customer)
    {
        $this->authorizeAdmin();
        $this->abortIfStaff($customer);

        if ($customer->id === $request->user()->id) {
            return back()->with('error', 'You cannot change the status of your own account.');
        }

        $data = $request->validate([
            'status' => ['required', Rule::in(User::STATUSES)],
        ]);

        $customer->update(['status' => $data['status']]);

        if (! $customer->isActive()) {
            $this->endCustomerSessions($customer);
        }

        return back()->with(
            'success',
            'Customer ' . $customer->name . ' marked as ' . $customer->statusLabel() . '.'
        );
    }

    /**
     * Force-log-out a customer everywhere by dropping their sessions.
     */
    private function endCustomerSessions(User $customer): void
    {
        DB::table('sessions')->where('user_id', $customer->id)->delete();
    }

    /**
     * Staff accounts (admins) are not manageable as customers.
     */
    private function abortIfStaff(User $customer): void
    {
        abort_if($customer->isAdmin(), 403, 'Administrator accounts cannot be managed as customers.');
    }

    /**
     * Only admins can manage customers.
     */
    private function authorizeAdmin(): void
    {
        abort_unless(auth()->check() && auth()->user()->isAdmin(), 403);
    }
}
