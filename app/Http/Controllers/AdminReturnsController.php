<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\ReturnRequest;
use Illuminate\Http\Request;

class AdminReturnsController extends Controller
{
    public function index(Request $request)
    {
        $this->authorizeAdmin();

        $status = $request->get('status', 'all');

        $returns = ReturnRequest::with(['order', 'customer'])
            ->status($status === 'all' ? null : $status)
            ->latest()
            ->paginate(15);
        $returns->appends($request->query());

        return view('admin.returns.index', [
            'returns'     => $returns,
            'status'      => $status,
            'statuses'    => ReturnRequest::STATUSES,
            'statusLabels' => ReturnRequest::STATUS_LABELS,
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

    public function show(ReturnRequest $return)
    {
        $this->authorizeAdmin();

        $return->load(['order.items', 'customer']);

        return view('admin.returns.show', ['returnRequest' => $return]);
    }

    public function updateStatus(Request $request, ReturnRequest $return)
    {
        $this->authorizeAdmin();

        $data = $request->validate([
            'status' => ['required', 'in:' . implode(',', ReturnRequest::STATUSES)],
        ]);

        $return->update(['status' => $data['status']]);

        return back()->with(
            'success',
            'Return marked as ' . ReturnRequest::STATUS_LABELS[$data['status']] . '.'
        );
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
