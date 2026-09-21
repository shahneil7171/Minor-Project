<?php

namespace App\Events;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Dispatched by App\Services\OrderStatusService after every REAL order status
 * change (i.e. once per audit-trail entry).
 *
 * Nothing in the application listens to it yet, on purpose: this is the clean
 * hook the future notification/email phase and the inventory phase attach to
 * (confirmed / processing / ready_for_pickup / assigned / picked_up /
 * out_for_delivery / delivered / cancelled). The existing mails keep working
 * exactly as they do today — they are not driven from this event.
 */
class OrderStatusChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Order $order,
        public OrderStatusHistory $history,
        public ?User $actor = null,
        public ?string $fromStatus = null,
        public ?string $toStatus = null,
    ) {
    }
}
