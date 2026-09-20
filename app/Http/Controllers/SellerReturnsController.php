<?php

namespace App\Http\Controllers;

use App\Models\ReturnRequest;
use Illuminate\Http\Request;

/**
 * Seller view of return requests for THEIR products only.
 *
 * Role enforced by the "seller" middleware; ownership re-checked per request
 * so a seller can never open another seller's return by editing the URL.
 */
class SellerReturnsController extends Controller
{
    /**
     * Returns raised against this seller's products.
     */
    public function index(Request $request)
    {
        $seller = $request->user();
        $status = $request->get('status', 'all');

        $query = ReturnRequest::query()
            ->where('seller_id', $seller->id)
            ->with(['order', 'customer']);

        if ($status !== 'all' && in_array($status, ReturnRequest::STATUSES, true)) {
            $query->where('status', $status);
        }

        $returns = $query->latest()->paginate(10);
        $returns->appends($request->query());

        return view('seller.returns.index', [
            'returns'      => $returns,
            'status'       => $status,
            'statuses'     => ReturnRequest::STATUSES,
            'statusLabels' => ReturnRequest::STATUS_LABELS,
        ]);
    }

    /**
     * One return for one of this seller's own product lines.
     */
    public function show(Request $request, ReturnRequest $return)
    {
        if ((int) $return->seller_id !== (int) $request->user()->id) {
            abort(403, 'You can only view returns for your own products.');
        }

        $return->load([
            'order',
            'orderItem',
            'customer',
            'order.items' => fn ($q) => $q->where('seller_id', $request->user()->id),
        ]);

        return view('seller.returns.show', ['returnRequest' => $return]);
    }
}
