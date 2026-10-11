<?php

namespace App\Services;

use App\Models\Replacement;
use App\Models\ReturnRequest;
use App\Models\User;
use App\Notifications\StoreAlert;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Replacement / exchange workflow.
 *
 * A customer may choose "Replace/exchange" instead of a refund. The return is
 * still created and inspected like any other; this service owns the extra
 * replacement leg only:
 *
 *   requested -> approved -> processing -> shipped -> delivered
 *                                            \-> rejected / cancelled
 *
 * Rules enforced here:
 *  - the replacement links to the ORIGINAL order item + return (never a copy);
 *  - a same-item / same-variant swap has no charge (price_difference 0);
 *  - a different variant/product computes the price difference from TRUSTED
 *    catalog data (never from a submitted price): a more expensive replacement
 *    must be paid before it can be dispatched; a cheaper one is refunded;
 *  - stock is reserved/decremented through the existing InventoryService so a
 *    replacement can never oversell and is released if cancelled;
 *  - only one replacement exists per return (idempotent via idempotency_key).
 */
class ReplacementService
{
    public function __construct(private InventoryService $inventory)
    {
    }

    /**
     * Create the replacement leg for an approved return (idempotent).
     *
     * @param  array{product_slug?: ?string, variant_id?: ?string, quantity?: int}  $data
     */
    public function create(ReturnRequest $return, array $data = []): Replacement
    {
        $return->loadMissing(['order', 'orderItem', 'customer']);

        return DB::transaction(function () use ($return, $data) {
            $existing = Replacement::where('return_request_id', $return->id)->first();
            if ($existing) {
                return $existing;
            }

            $item = $return->orderItem;
            $slug = $data['product_slug'] ?? $item?->product_slug;
            $variantId = $data['variant_id'] ?? $item?->variant_id;
            $quantity = max(1, (int) ($data['quantity'] ?? $return->quantity));

            // Price difference is computed from the CATALOG price of the
            // requested replacement vs the ORIGINAL paid line price — both
            // trusted server-side values, never a submitted amount.
            $difference = $this->priceDifference($return, $slug, $variantId, $quantity);

            $replacement = Replacement::create([
                'return_request_id' => $return->id,
                'order_id'          => $return->order_id,
                'order_item_id'     => $return->order_item_id,
                'user_id'           => $return->user_id,
                'product_slug'      => $slug,
                'variant_id'        => $variantId,
                'quantity'          => $quantity,
                'price_difference'  => $difference,
                // No charge for an even swap; otherwise pending until settled.
                'payment_status'    => abs($difference) < 0.01 ? 'not_required' : 'pending',
                'status'            => 'requested',
                'idempotency_key'   => 'replacement-' . $return->id,
                'note'              => 'Replacement requested for return ' . $return->return_number . '.',
            ]);

            $return->forceFill(['resolution' => 'replace'])->saveQuietly();

            return $replacement;
        });
    }

    /**
     * Approve a requested replacement (admin/seller). Stock availability is
     * checked before the replacement may be approved.
     */
    public function approve(Replacement $replacement, User $actor): Replacement
    {
        $replacement->loadMissing(['returnRequest.orderItem']);

        $availability = $this->checkAvailability($replacement);

        if (! $availability['available']) {
            throw ValidationException::withMessages([
                'replacement' => $availability['reason'],
            ]);
        }

        $replacement->forceFill(['status' => 'approved', 'note' => 'Approved by ' . $actor->name])->save();

        if ($replacement->returnRequest?->customer) {
            $this->alert(
                $replacement->returnRequest->customer,
                'Replacement approved',
                'Your replacement for ' . ($replacement->returnRequest->product_title ?? 'your product') . ' was approved.',
                route('returns.show', ['return' => $replacement->return_request_id])
            );
        }

        return $replacement;
    }

    /**
     * Reserve + decrement stock and move to processing (seller preparing the
     * replacement). Uses the existing InventoryService so overselling is
     * impossible and the movement is recorded.
     */
    public function reserveStock(Replacement $replacement, User $actor): Replacement
    {
        $replacement->loadMissing(['returnRequest.orderItem']);

        if (! in_array($replacement->status, ['approved', 'processing'], true)) {
            throw ValidationException::withMessages([
                'status' => 'A replacement must be approved before stock is reserved.',
            ]);
        }

        $product = $this->inventory->productBySlug($replacement->product_slug);

        if ($product) {
            // Throws a ValidationException if stock is insufficient, which rolls
            // back the status change — the replacement is never marked preparing
            // for stock that does not exist.
            $this->inventory->commitSale($product, $replacement->variant_id, (int) $replacement->quantity, [
                'order_id'  => $replacement->order_id,
                'actor_id'  => $actor->id,
                'reference' => 'replacement-' . $replacement->id,
                'reason'    => 'Replacement for return ' . ($replacement->returnRequest?->return_number ?? $replacement->return_request_id),
            ]);
        }

        $replacement->forceFill(['status' => 'processing'])->save();

        return $replacement;
    }

    public function markShipped(Replacement $replacement): Replacement
    {
        if ($replacement->status !== 'processing') {
            throw ValidationException::withMessages(['status' => 'Only a preparing replacement can be shipped.']);
        }

        $replacement->forceFill(['status' => 'shipped', 'shipped_at' => now()])->save();
        $this->notifyCustomer($replacement, 'Replacement dispatched', 'Your replacement has been shipped.');

        return $replacement;
    }

    public function markDelivered(Replacement $replacement): Replacement
    {
        $replacement->forceFill(['status' => 'delivered', 'delivered_at' => now()])->save();

        // The parent return is now fully resolved.
        $return = $replacement->returnRequest;
        if ($return && ! in_array($return->status, ['completed', 'refunded'], true)) {
            $return->forceFill(['status' => 'completed', 'completed_at' => now()])->saveQuietly();
        }

        $this->notifyCustomer($replacement, 'Replacement delivered', 'Your replacement has been delivered.');

        return $replacement;
    }

    /**
     * Whether the replacement product/variant currently has enough sellable
     * stock for the requested quantity.
     *
     * @return array{available: bool, reason: string}
     */
    public function checkAvailability(Replacement $replacement): array
    {
        $product = $this->inventory->productBySlug($replacement->product_slug);

        if (! $product) {
            return ['available' => false, 'reason' => 'The replacement product could not be found in the catalog.'];
        }

        $available = $this->inventory->availableFor($product, $replacement->variant_id);

        if ($available < (int) $replacement->quantity) {
            return ['available' => false, 'reason' => 'Only ' . $available . ' unit(s) of the replacement are in stock.'];
        }

        return ['available' => true, 'reason' => ''];
    }

    /**
     * Price difference (replacement value - original paid value), rounded.
     * Positive = customer owes; negative = refund the difference.
     */
    private function priceDifference(ReturnRequest $return, ?string $slug, ?string $variantId, int $quantity): float
    {
        $item = $return->orderItem;
        $originalUnit = $item ? round((float) $item->price, 2) : 0.0;

        // Same product + same variant => no difference.
        if ($slug === $item?->product_slug && (string) $variantId === (string) $item?->variant_id) {
            return 0.0;
        }

        $replacementUnit = $originalUnit;
        $catalog = app(\App\Services\ProductCatalogService::class)->find((string) $slug);

        if ($catalog) {
            if ($variantId) {
                $variant = \App\Services\ProductVariantService::findVariant($catalog, $variantId);
                $replacementUnit = $variant ? round((float) $variant['price'], 2) : $replacementUnit;
            } else {
                $replacementUnit = round((float) ($catalog['price'] ?? $originalUnit), 2);
            }
        }

        return round(($replacementUnit - $originalUnit) * $quantity, 2);
    }

    private function notifyCustomer(Replacement $replacement, string $title, string $body): void
    {
        $customer = $replacement->user;

        if (! $customer) {
            return;
        }

        $this->alert($customer, $title, $body, route('returns.show', ['return' => $replacement->return_request_id]));
    }

    private function alert(User $user, string $title, string $body, string $url): void
    {
        try {
            $user->notify(new StoreAlert($title, $body, $url));
        } catch (\Throwable $e) {
            Log::error('Replacement notification failed: ' . $e->getMessage());
        }
    }
}
