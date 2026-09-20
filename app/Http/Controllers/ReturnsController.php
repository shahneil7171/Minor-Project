<?php

namespace App\Http\Controllers;

use App\Models\OrderItem;
use App\Models\ReturnRequest;
use App\Services\ReturnService;
use App\Support\ReturnPolicy;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Buyer "Returns & Refunds" area.
 *
 * Route middleware ("auth") plus server-side ownership checks: a buyer only
 * ever sees or creates returns for their own orders. Every business rule
 * (delivered line, configured window, remaining quantity, duplicates) is
 * enforced by ReturnService/ReturnPolicy — the UI never decides.
 */
class ReturnsController extends Controller
{
    public function __construct(private ReturnService $returns)
    {
    }

    /**
     * All return requests raised by the signed-in buyer.
     */
    public function index(Request $request)
    {
        $returns = ReturnRequest::query()
            ->where('user_id', $request->user()->id)
            ->with(['order'])
            ->latest()
            ->paginate(10);
        $returns->appends($request->query());

        return view('returns.index', [
            'returns'    => $returns,
            'windowDays' => $this->returns->windowDays(),
            'policy'     => ReturnPolicy::policyLines(),
        ]);
    }

    /**
     * The return request form for one order line.
     */
    public function create(Request $request, OrderItem $item)
    {
        $item->load(['order']);

        $order = $item->order;

        if (! $order || (int) $order->user_id !== (int) $request->user()->id) {
            abort(403, 'You can only request returns for your own orders.');
        }

        $preview = $this->returns->preview($item);

        return view('returns.create', [
            'item'       => $item,
            'order'      => $order,
            'preview'    => $preview,
            'reasons'    => ReturnRequest::REASONS,
            'policy'     => ReturnPolicy::policyLines(),
            'deadline'   => $order->returnDeadline(),
            'deliveredAt' => $order->deliveredAt(),
        ]);
    }

    /**
     * Store a new return request. All rules are re-verified server-side.
     */
    public function store(Request $request, OrderItem $item)
    {
        $data = $request->validate([
            'reason'      => ['required', 'string', 'max:2000'],
            'description' => ['nullable', 'string', 'max:2000'],
            'quantity'    => ['nullable', 'integer', 'min:1'],
            'images'      => ['nullable', 'array', 'max:5'],
            'images.*'    => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ]);

        try {
            $return = $this->returns->create(
                $item,
                $request->user(),
                $data,
                $request->file('images', [])
            );
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()
            ->route('returns.show', ['return' => $return])
            ->with('success', 'Return request ' . $return->return_number . ' submitted for Order #' . $return->order_number . '.');
    }

    /**
     * One return request with its tracking timeline.
     */
    public function show(Request $request, ReturnRequest $return)
    {
        if ((int) $return->user_id !== (int) $request->user()->id) {
            abort(403, 'You can only view your own return requests.');
        }

        $return->load(['order.items', 'orderItem', 'customer', 'seller', 'deliveryPartner']);

        return view('returns.show', ['returnRequest' => $return]);
    }
}
