<?php

namespace App\Http\Controllers;

use App\Models\SellerPaymentProfile;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin view of seller payment profiles (read-only).
 *
 * Administration use only: bank account numbers are ALWAYS rendered in
 * masked form (XXXXXX1234) even for admins; the full value is never
 * displayed, logged or emailed anywhere in the application.
 */
class AdminSellerPaymentsController extends Controller
{
    public function index(Request $request): View
    {
        $profiles = SellerPaymentProfile::query()
            ->with('seller')
            ->orderBy('seller_id')
            ->get();

        return view('admin.seller-payments', ['profiles' => $profiles]);
    }
}
