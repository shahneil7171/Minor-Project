<?php

namespace App\Services;

use App\Mail\OrderApprovedMail;
use App\Mail\SellerOrderApprovedMail;
use App\Models\Order;
use App\Models\User;
use App\Notifications\StoreAlert;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Order approval workflow.
 *
 * A placed order stays "pending" until an admin approves it. Approval:
 *  1. transitions the order pending -> approved (transactional),
 *  2. emails the buyer,
 *  3. notifies every seller that has products in the order — each seller
 *     only ever receives the lines that belong to them.
 *
 * All mail failures are logged and swallowed so a mail outage can never
 * corrupt the workflow. Notifications fire exactly once per approval —
 * re-approving (or re-rendering pages) is a no-op because the transition
 * guard rejects orders that are not pending.
 */
class OrderWorkflowService
{
    /**
     * Approve a pending order. Returns false when the order is not in the
     * pending state (already approved/processed/cancelled).
     */
    public function approve(Order $order, User $admin): bool
    {
        if ($order->status !== 'pending') {
            return false;
        }

        DB::transaction(function () use ($order): void {
            $order->update([
                'status'      => 'approved',
                'approved_at' => now(),
            ]);
        });

        $order->refresh()->load(['items', 'user']);

        $this->notifyBuyer($order);
        $this->notifySellers($order);

        return true;
    }

    /**
     * Email + in-app notify the buyer that their order was approved.
     */
    private function notifyBuyer(Order $order): void
    {
        $recipient = $order->user?->email ?? $order->customer_email;

        if ($recipient) {
            try {
                Mail::to($recipient)->send(new OrderApprovedMail($order));
            } catch (Throwable $e) {
                Log::error('Order approval email failed for order ' . $order->order_number . ': ' . $e->getMessage());
            }
        }

        $order->user?->notify(new StoreAlert(
            'Order approved',
            'Your order #' . $order->order_number . ' has been approved and is being prepared.',
            route('orders.show', ['order' => $order->id]),
        ));
    }

    /**
     * Notify every seller with products in this order.
     *
     * Multi-seller orders are grouped by seller_id: each seller receives a
     * notification (mail + in-app) containing ONLY their own order lines —
     * never another seller's products or any admin-only information.
     */
    private function notifySellers(Order $order): void
    {
        $order->items
            ->filter(fn ($item) => $item->seller_id !== null)
            ->groupBy('seller_id')
            ->each(function ($items, int $sellerId) use ($order): void {
                $seller = User::find($sellerId);

                if (! $seller) {
                    return;
                }

                try {
                    Mail::to($seller->email)->send(new SellerOrderApprovedMail($order, $items->all()));
                } catch (Throwable $e) {
                    Log::error('Seller approval email failed for order ' . $order->order_number . ': ' . $e->getMessage());
                }

                $seller->notify(new StoreAlert(
                    'New order approved',
                    'Order #' . $order->order_number . ' contains products from your store.',
                    route('seller.orders.show', ['order' => $order->id]),
                ));
            });
    }
}
