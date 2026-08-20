<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use Illuminate\Http\Request;

/**
 * Admin management for coupon / promo codes.
 *
 * Coupons can be created, edited and revoked by administrators from the
 * store admin. Shoppers apply them on checkout; the actual discount is
 * computed and persisted by the checkout closure in web.php.
 */
class CouponsController extends Controller
{
    /**
     * Display the list of coupons with an inline creation form.
     */
    public function index()
    {
        $this->authorizeAdmin();

        $coupons = Coupon::orderBy('created_at', 'desc')->paginate(15);

        return view('admin.coupons.index', compact('coupons'));
    }

    /**
     * Store a newly created coupon.
     */
    public function store(Request $request)
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'code'             => ['required', 'string', 'max:50', 'unique:coupons,code'],
            'type'             => ['required', 'in:fixed,percent'],
            'value'            => ['required', 'integer', 'min:1'],
            'usage_limit'      => ['nullable', 'integer', 'min:1'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'expires_at'       => ['nullable', 'date', 'after_or_equal:today'],
            'active'           => ['boolean'],
        ]);

        $data['active'] = $request->boolean('active', true);

        Coupon::create($data);

        return redirect()->route('admin.coupons.index')->with('success', 'Coupon created successfully.');
    }

    /**
     * Show the edit form for a coupon.
     */
    public function edit(Coupon $coupon)
    {
        $this->authorizeAdmin();

        return view('admin.coupons.edit', compact('coupon'));
    }

    /**
     * Update an existing coupon.
     */
    public function update(Request $request, Coupon $coupon)
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'code'             => ['required', 'string', 'max:50', 'unique:coupons,code,' . $coupon->id],
            'type'             => ['required', 'in:fixed,percent'],
            'value'            => ['required', 'integer', 'min:1'],
            'usage_limit'      => ['nullable', 'integer', 'min:1'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'expires_at'       => ['nullable', 'date', 'after_or_equal:today'],
            'active'           => ['boolean'],
        ]);

        $data['active'] = $request->boolean('active', true);

        $coupon->update($data);

        return redirect()->route('admin.coupons.index')->with('success', 'Coupon updated successfully.');
    }

    /**
     * Delete a coupon.
     */
    public function destroy(Coupon $coupon)
    {
        $this->authorizeAdmin();

        $coupon->delete();

        return redirect()->route('admin.coupons.index')->with('success', 'Coupon removed.');
    }

    /**
     * Only admins can manage coupons.
     */
    private function authorizeAdmin(): void
    {
        $user = auth()->user();

        if (! $user || $user->account_type !== 'admin') {
            abort(403);
        }
    }
}
