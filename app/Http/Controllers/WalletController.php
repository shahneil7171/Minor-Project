<?php

namespace App\Http\Controllers;

use App\Models\SmartCoinTransaction;
use App\Services\SmartCoinsService;
use Illuminate\Http\Request;

/**
 * Customer "Smart Coins" wallet.
 *
 * Read-only for the customer: a buyer may VIEW their balance and the immutable
 * ledger, but never edit a balance or a ledger row directly. Every credit/debit
 * is produced server-side by RefundService (refunds) or CheckoutController
 * (redemption) through SmartCoinsService, which is the only writer.
 */
class WalletController extends Controller
{
    public function index(Request $request, SmartCoinsService $coins)
    {
        $user = $request->user();

        // Resolving the wallet here also creates it on first visit so the page
        // always renders a real (zero) balance instead of erroring.
        $wallet = $coins->walletFor($user);

        $transactions = SmartCoinTransaction::where('user_id', $user->id)
            ->latest()
            ->paginate(15);

        return view('wallet.index', [
            'wallet'       => $wallet,
            'balance'      => (float) $wallet->balance,
            'transactions' => $transactions,
            'coinValue'    => SmartCoinsService::valueOf((float) $wallet->balance),
            'rate'         => SmartCoinsService::rate(),
        ]);
    }
}
