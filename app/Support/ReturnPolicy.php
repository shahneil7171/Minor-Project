<?php

namespace App\Support;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Setting;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Single source of truth for the demo return policy:
 *
 *  - the return window (configurable, default 7 days) measured from the
 *    ACTUAL delivery date, never the order creation date;
 *  - whether an order line is currently eligible for a return;
 *  - the refund breakdown, always computed from the recorded order/line data
 *    and never from anything the buyer submits.
 *
 * Every piece of "+N days" arithmetic lives here so controllers, services and
 * views only ever ask questions instead of doing date math themselves.
 */
class ReturnPolicy
{
    /**
     * The configured return window in days (7 by default, admin-configurable).
     */
    public static function windowDays(): int
    {
        return Setting::returnWindowDays();
    }

    /**
     * Whether the original shipping cost is refunded on an approved return.
     */
    public static function refundsShipping(): bool
    {
        return Setting::refundShippingOnReturn();
    }

    /**
     * The last moment at which a return may be requested.
     *
     * The final day is INCLUSIVE: delivered Sep 10 with a 7-day window yields
     * a deadline of Sep 17 23:59:59 — "return available until September 17".
     */
    public static function deadline(?CarbonInterface $deliveredAt): ?Carbon
    {
        if ($deliveredAt === null) {
            return null;
        }

        return Carbon::instance($deliveredAt)
            ->startOfDay()
            ->addDays(self::windowDays())
            ->endOfDay();
    }

    /**
     * Whether a deadline has passed (Carbon comparison, never string compare).
     */
    public static function isExpired(?CarbonInterface $deadline, ?CarbonInterface $now = null): bool
    {
        if ($deadline === null) {
            return true;
        }

        return ($now ?? Carbon::now())->greaterThan($deadline);
    }

    /**
     * Full eligibility check for one order line.
     *
     * The backend re-checks every rule on submit — the UI never decides.
     *
     * @return array{eligible: bool, reason: ?string}
     */
    public static function check(OrderItem $item, int $quantity = 1): array
    {
        $order = $item->order;

        if (! $order) {
            return self::no('The order for this item could not be found.');
        }

        if ($order->isCancelled()) {
            return self::no('Cancelled orders are not eligible for return.');
        }

        $deliveredAt = $order->deliveredAt();

        if ($deliveredAt === null) {
            return self::no('This item has not been delivered yet.');
        }

        if (self::isExpired(self::deadline($deliveredAt))) {
            return self::no('Return period has expired.');
        }

        $returnable = $item->returnableQuantity();

        if ($returnable < 1) {
            return self::no(self::consumedReason($item));
        }

        if ($quantity > $returnable) {
            return self::no('You can return at most ' . $returnable . ' unit(s) of this item.');
        }

        return ['eligible' => true, 'reason' => null];
    }

    /**
     * Why no returnable quantity is left on a line.
     */
    private static function consumedReason(OrderItem $item): string
    {
        if ($item->refundedQuantity() > 0) {
            return 'This item has already been refunded.';
        }

        if ($item->hasActiveReturnRequest()) {
            return 'A return request for this item is already in progress.';
        }

        return 'This item has already been returned.';
    }

    /**
     * @return array{eligible: bool, reason: ?string}
     */
    private static function no(string $reason): array
    {
        return ['eligible' => false, 'reason' => $reason];
    }

    /**
     * Refund breakdown for a quantity of one order line.
     *
     * Money always comes from the HISTORICAL order data:
     *  - the unit price stored on the order item (a later price change on the
     *    product has no effect);
     *  - the order's coupon discount and tax, applied as this line's
     *    proportional share (the discount belongs to the whole order);
     *  - shipping only when the configured demo policy refunds it.
     *
     * @return array{
     *     quantity: int, unit_price: float, gross: float, discount: float,
     *     tax: float, product: float, shipping: float, total: float
     * }
     */
    public static function refundBreakdown(?Order $order, ?OrderItem $item, int $quantity): array
    {
        $quantity = max(0, $quantity);

        if (! $order || ! $item || $quantity < 1) {
            return [
                'quantity'   => 0,
                'unit_price' => 0.0,
                'gross'      => 0.0,
                'discount'   => 0.0,
                'tax'        => 0.0,
                'product'    => 0.0,
                'shipping'   => 0.0,
                'total'      => 0.0,
            ];
        }

        $unitPrice = round((float) $item->price, 2);
        $gross = round($unitPrice * $quantity, 2);

        $orderSubtotal = round((float) $order->subtotal, 2);
        $share = $orderSubtotal > 0 ? min(1.0, $gross / $orderSubtotal) : 0.0;

        // Coupon discount and tax are order-level amounts, so only this line's
        // proportional share is returned to the buyer.
        $discount = round((float) $order->discount_amount * $share, 2);
        $tax = round((float) $order->tax * $share, 2);

        $product = round(max(0.0, $gross - $discount) + $tax, 2);
        $shipping = self::refundsShipping() ? round((float) $order->shipping_cost, 2) : 0.0;

        return [
            'quantity'   => $quantity,
            'unit_price' => $unitPrice,
            'gross'      => $gross,
            'discount'   => $discount,
            'tax'        => $tax,
            'product'    => $product,
            'shipping'   => $shipping,
            'total'      => round($product + $shipping, 2),
        ];
    }

    /**
     * The buyer-facing policy text. Deliberately worded as this project's
     * configurable demo policy — it is not a real company/legal policy.
     *
     * @return array<int, string>
     */
    public static function policyLines(): array
    {
        $days = self::windowDays();

        $lines = [
            'Products can be returned within ' . $days . ' day' . ($days === 1 ? '' : 's') . ' of delivery.',
            'The product should be returned in acceptable condition with original packaging/accessories where applicable.',
            'Return requests submitted after the return period cannot be accepted.',
            'The final refund amount and eligibility are subject to return approval.',
        ];

        if (self::refundsShipping()) {
            $lines[] = 'Original shipping charges are included in the refund for approved returns.';
        } else {
            $lines[] = 'Shipping charges are not refunded. Only the product amount is refunded.';
        }

        return $lines;
    }
}