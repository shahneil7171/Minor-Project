<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Services\ReturnService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;

/**
 * Admin "Returns & Refunds" management.
 *
 * Read actions stay permission-open to staff; every state-changing workflow
 * action (approve / reject / pickup / refund) is delegated to ReturnService,
 * which validates the transition map server-side so out-of-order state
 * changes can never be smuggled through the forms.
 */
class AdminReturnsController extends Controller
{
    public function __construct(private ReturnService $returns)
    {
    }

    public function index(Request $request)
    {
        $this->authorizeAdmin();

        $status = $request->get('status', 'all');
        $refundStatus = $request->get('refund', 'all');

        $returns = ReturnRequest::with(['order', 'customer', 'seller'])
            ->status($status === 'all' ? null : $status)
            ->when($refundStatus !== 'all', fn ($q) => $q->where('refund_status', $refundStatus))
            ->latest()
            ->paginate(15);
        $returns->appends($request->query());

        return view('admin.returns.index', [
            'returns'       => $returns,
            'status'        => $status,
            'statuses'      => ReturnRequest::STATUSES,
            'statusLabels'  => ReturnRequest::STATUS_LABELS,
            'refundStatuses' => ReturnRequest::REFUND_STATUSES,
            'refundLabels'  => ReturnRequest::REFUND_STATUS_LABELS,
        ]);
    }

    public function create()
    {
        $this->authorizeAdmin();

        return view('admin.returns.form', [
            'returnRequest' => new ReturnRequest(),
            'orders'        => Order::with('items')->latest()->limit(100)->get(),
            'statuses'      => ReturnRequest::STATUSES,
            'statusLabels'  => ReturnRequest::STATUS_LABELS,
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeAdmin();

        $data = $this->validated($request);

        $order = Order::find($data['order_id'] ?? null);

        ReturnRequest::create([
            'order_id'       => $order?->id,
            'user_id'        => $order?->user_id,
            'order_number'   => $order?->order_number,
            'customer_email' => $order?->user?->email ?? $order?->customer_email,
            'product_slug'   => $data['product_slug'] ?? null,
            'product_title'  => $data['product_title'],
            'reason'         => $data['reason'],
            'status'         => $data['status'],
            'admin_note'     => $data['admin_note'] ?? null,
        ]);

        return redirect()->route('admin.returns.index')->with('success', 'Return request created successfully.');
    }

    public function show(Request $request, ReturnRequest $return)
    {
        $this->authorizeAdmin();

        $return->load(['order.items', 'orderItem', 'customer', 'seller', 'deliveryPartner', 'assignedBy']);

        $breakdown = $return->orderItem
            ? $this->returns->preview($return->orderItem, (int) $return->quantity)['breakdown']
            : null;

        return view('admin.returns.show', [
            'returnRequest' => $return,
            'breakdown'     => $breakdown,
            'partners'      => User::where('account_type', 'delivery_partner')
                ->where('status', 'active')
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
        ]);
    }

    // ------------------------------------------------------------------
    // Workflow actions (each validates its transition inside ReturnService)
    // ------------------------------------------------------------------

    public function approve(Request $request, ReturnRequest $return)
    {
        $this->authorizeAdmin();

        try {
            $this->returns->approve($return, $request->user(), $request->input('admin_note'));
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Return ' . $return->return_number . ' approved. Buyer and seller notified.');
    }

    public function reject(Request $request, ReturnRequest $return)
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'rejection_reason' => ['required', 'string', 'min:5', 'max:2000'],
            'admin_note'       => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->returns->reject($return, $request->user(), $data['rejection_reason'], $data['admin_note'] ?? null);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Return ' . $return->return_number . ' rejected. Buyer notified.');
    }

    public function schedulePickup(Request $request, ReturnRequest $return)
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'delivery_partner_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('account_type', 'delivery_partner'),
            ],
            'pickup_instructions' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->returns->schedulePickup(
                $return,
                (int) $data['delivery_partner_id'],
                $request->user(),
                $data['pickup_instructions'] ?? null
            );
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Return pickup scheduled. Delivery partner notified (in-app + email).');
    }

    public function markReceived(Request $request, ReturnRequest $return)
    {
        $this->authorizeAdmin();

        try {
            $this->returns->markReceived($return, $request->user(), $request->input('notes'));
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Returned product marked as received. Buyer notified.');
    }

    /**
     * Record the inspection result of a received return and, only for a
     * RESELLABLE product, put the units back into sellable stock.
     *
     * Damaged / non-resellable returns are recorded for the audit trail but
     * never increase the stock a buyer can purchase. Pressing the button
     * twice is rejected by the service's idempotency guard, so stock can
     * never be added twice.
     */
    public function restock(Request $request, ReturnRequest $return)
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'inventory_condition' => ['required', 'in:' . implode(',', ReturnRequest::INVENTORY_CONDITIONS)],
            'note'                => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->returns->restock(
                $return,
                $request->user(),
                $data['inventory_condition'],
                $data['note'] ?? null,
            );
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->with(
                'error',
                collect($e->errors())->flatten()->first()
            );
        }

        $message = $data['inventory_condition'] === 'resellable'
            ? 'Returned product marked as Resellable. Stock restored once.'
            : 'Returned product marked as ' . (ReturnRequest::INVENTORY_CONDITION_LABELS[$data['inventory_condition']] ?? $data['inventory_condition'])
                . '. Sellable stock was not changed.';

        return back()->with('success', $message);
    }

    public function startRefund(Request $request, ReturnRequest $return)
    {
        $this->authorizeAdmin();

        try {
            $this->returns->startRefund($return, $request->user(), $request->input('refund_reference'));
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Refund marked as processing. Buyer notified.');
    }

    public function markRefunded(Request $request, ReturnRequest $return)
    {
        $this->authorizeAdmin();

        try {
            $this->returns->markRefunded($return, $request->user(), $request->input('refund_reference'));
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'Refund recorded as completed. Buyer notified (in-app + email).');
    }

    public function destroy(ReturnRequest $return)
    {
        $this->authorizeAdmin();

        $return->delete();

        return redirect()->route('admin.returns.index')->with('success', 'Return deleted successfully.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'order_id'      => ['nullable', 'integer', 'exists:orders,id'],
            'product_slug'  => ['nullable', 'string', 'max:255'],
            'product_title' => ['required', 'string', 'max:255'],
            'reason'        => ['required', 'string', 'max:2000'],
            'status'        => ['required', 'in:' . implode(',', ReturnRequest::STATUSES)],
            'admin_note'    => ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->check() && auth()->user()->isStaff(), 403);
    }
}
