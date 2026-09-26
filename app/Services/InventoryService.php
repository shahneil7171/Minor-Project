<?php

namespace App\Services;

use App\Models\InventoryTransaction;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ReturnRequest;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\StoreAlert;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * InventoryService — the single source of truth for every stock decision.
 *
 * PHASE 3 of KDP MART. It REUSES the existing inventory architecture instead
 * of adding a second one:
 *
 *   - `products.quantity` is the product-level STOCK (column already existed).
 *   - `products.variants` (JSON, already existed) is the VARIANT-level stock:
 *     each variant carries its own `stock`. There is no `product_variants`
 *     table in this project and one is deliberately NOT created — a second
 *     source of truth is exactly what must never happen.
 *   - `products.reserved` is the new RESERVED counter.
 *
 * Terminology used everywhere in the app:
 *
 *   stock     = physical quantity owned by the seller
 *   reserved  = quantity temporarily held for orders
 *   available = stock - reserved  (what a buyer may purchase)
 *
 * The database is authoritative. Controllers, Blade and JavaScript never do
 * stock arithmetic — they ask this service and render the answer. Every write
 * happens inside a database transaction with a row lock (lockForUpdate) on the
 * product row, so two buyers can never purchase more inventory than exists,
 * and every write records exactly one inventory_transactions row
 * (previous_stock / new_stock / type / actor).
 */
class InventoryService
{
    public const STATUS_IN_STOCK = 'in_stock';
    public const STATUS_LOW_STOCK = 'low_stock';
    public const STATUS_OUT_OF_STOCK = 'out_of_stock';
    public const STATUS_PRE_ORDER = 'pre_order';

    /**
     * Status labels used by every badge in the app.
     */
    public const STATUS_LABELS = [
        self::STATUS_IN_STOCK     => 'In Stock',
        self::STATUS_LOW_STOCK    => 'Low Stock',
        self::STATUS_OUT_OF_STOCK => 'Out of Stock',
        self::STATUS_PRE_ORDER    => 'Pre-Order',
    ];

    /**
     * Hard ceiling for a single stock movement (defends against absurd input
     * such as 999999999 from a tampered form).
     */
    private const MAX_MOVEMENT = 1000000;

    /**
     * Per-request slug => Product memo so a cart with N lines does not run N
     * identical queries. Cleared on every write so a stale stock level can
     * never be read back.
     *
     * @var array<string, Product|null>
     */
    private array $productMemo = [];

    /**
     * Alerts collected while alerts are suppressed (used by checkout, which
     * must not notify before it is known that the transaction committed).
     *
     * @var array<int, array<string, mixed>>
     */
    private array $deferredAlerts = [];

    private bool $suppressAlerts = false;

    // ------------------------------------------------------------------
    // Reads
    // ------------------------------------------------------------------

    /**
     * Resolve a catalog product from its slug (memoized, never trusted from
     * the browser).
     */
    public function productBySlug(?string $slug): ?Product
    {
        if ($slug === null || $slug === '') {
            return null;
        }

        if (! array_key_exists($slug, $this->productMemo)) {
            $this->productMemo[$slug] = Product::findBySlug($slug);
        }

        return $this->productMemo[$slug];
    }

    /**
     * The raw stock level of one inventory unit.
     *
     * With a variant id this is the variant's own stock; without one it is the
     * product-level stock (products.quantity). The variant is the single
     * source of truth for a variant product, so the parent quantity is never
     * touched by a variant purchase.
     */
    public function stockFor(Product $product, ?string $variantId = null): int
    {
        if ($variantId === null || $variantId === '') {
            return max(0, (int) $product->quantity);
        }

        $variant = $this->findVariant($product, $variantId);

        return $variant === null ? 0 : max(0, (int) ($variant['stock'] ?? 0));
    }

    /**
     * Quantity temporarily reserved for orders.
     *
     * KDP MART commits a sale at the exact moment the order is created (this
     * architecture has no "awaiting payment" reservation step), so a sold unit
     * leaves `stock` immediately and reserved returns to 0 — available
     * therefore drops by exactly the sold quantity, never twice. The counter
     * exists so the reservation model (available = stock - reserved) is real
     * and a future reservation flow needs no other change.
     */
    public function reservedFor(Product $product, ?string $variantId = null): int
    {
        // Variants carry no reserved counter of their own: a reservation is
        // always held against the product row.
        if ($variantId !== null && $variantId !== '') {
            return 0;
        }

        return max(0, min((int) $product->quantity, (int) ($product->reserved ?? 0)));
    }

    /**
     * What a buyer may purchase right now: stock - reserved (never negative).
     */
    public function availableFor(Product $product, ?string $variantId = null): int
    {
        return max(0, $this->stockFor($product, $variantId) - $this->reservedFor($product, $variantId));
    }

    /**
     * Whether at least $quantity units can be purchased right now.
     */
    public function hasStock(Product $product, ?string $variantId, int $quantity = 1): bool
    {
        return $this->availableFor($product, $variantId) >= max(0, $quantity);
    }

    /**
     * The low-stock trigger for a product: its own threshold, else the
     * store-wide default (never hard-coded in views/controllers).
     */
    public function thresholdFor(?Product $product = null): int
    {
        $threshold = $product?->low_stock_threshold;

        if ($threshold === null || $threshold === '') {
            return Setting::lowStockThreshold();
        }

        return max(0, (int) $threshold);
    }

    /**
     * In Stock / Low Stock / Out of Stock (or Pre-Order) for one unit.
     */
    public function statusFor(Product $product, ?string $variantId = null): string
    {
        if ($this->isPreOrder($product)) {
            return self::STATUS_PRE_ORDER;
        }

        return $this->statusFromAvailable(
            $this->availableFor($product, $variantId),
            $this->thresholdFor($product),
        );
    }

    /**
     * Map an available quantity to a status using a threshold.
     */
    public function statusFromAvailable(int $available, int $threshold): string
    {
        if ($available <= 0) {
            return self::STATUS_OUT_OF_STOCK;
        }

        return $available <= $threshold
            ? self::STATUS_LOW_STOCK
            : self::STATUS_IN_STOCK;
    }

    public function statusLabel(?string $status): string
    {
        return self::STATUS_LABELS[$status ?? ''] ?? 'In Stock';
    }

    /**
     * Whether a product accepts purchases at all.
     *
     * A disabled product (products.status = 0) and a product explicitly
     * flagged "out-of-stock" by its owner are never purchasable, whatever the
     * numbers say.
     */
    public function isPurchasable(Product $product, ?string $variantId = null): bool
    {
        if ((int) $product->status !== 1) {
            return false;
        }

        if ($this->isForceOutOfStock($product)) {
            return false;
        }

        if ($this->isPreOrder($product)) {
            return true;
        }

        return $this->availableFor($product, $variantId) > 0;
    }

    /**
     * A pre-order product is not stock-tracked (existing KDP MART behaviour):
     * it can be ordered before it is received from the supplier.
     */
    public function isPreOrder(Product $product): bool
    {
        return $product->stock_status === 'pre-order';
    }

    /**
     * The owner explicitly switched the product off ("out-of-stock" flag).
     */
    public function isForceOutOfStock(Product $product): bool
    {
        return $product->stock_status === 'out-of-stock';
    }

    /**
     * THE single availability check used by add-to-cart, buy-now, the cart
     * quantity controls, checkout and the wishlist.
     *
     * Returns a human explanation when the request cannot be served, or null
     * when it can. Nothing here trusts the browser: product state, variant
     * ownership and stock are always resolved from the database.
     */
    public function availabilityError(Product $product, ?string $variantId, int $quantity): ?string
    {
        $label = $this->productLabel($product, $variantId);

        if ($quantity < 1) {
            return 'Please choose a quantity of at least 1.';
        }

        if ((int) $product->status !== 1) {
            return $label . ' is no longer available.';
        }

        if ($this->isForceOutOfStock($product)) {
            return $label . ' is out of stock.';
        }

        if ($variantId !== null && $variantId !== '' && $this->findVariant($product, $variantId) === null) {
            return $label . ' is no longer available.';
        }

        // Pre-order products are not stock tracked.
        if ($this->isPreOrder($product)) {
            return null;
        }

        $available = $this->availableFor($product, $variantId);

        if ($available <= 0) {
            return $label . ' is out of stock.';
        }

        if ($quantity > $available) {
            return 'Only ' . $available . ' ' . $this->units($available) . ' are available.';
        }

        return null;
    }

    /**
     * Short storefront label for one inventory unit:
     * "20 units available" / "Only 3 left" / "Out of Stock".
     */
    public function storefrontLabel(Product $product, ?string $variantId = null): string
    {
        if ($this->isPreOrder($product)) {
            return 'Pre-Order';
        }

        if ($this->isForceOutOfStock($product)) {
            return 'Out of Stock';
        }

        $available = $this->availableFor($product, $variantId);

        return match ($this->statusFromAvailable($available, $this->thresholdFor($product))) {
            self::STATUS_OUT_OF_STOCK => 'Out of Stock',
            self::STATUS_LOW_STOCK    => 'Only ' . $available . ' left',
            default                   => $available . ' ' . $this->units($available) . ' available',
        };
    }

    /**
     * One inventory row (a product, or one row per variant) used by every
     * inventory table in the app.
     *
     * @return array<string, mixed>
     */
    public function rowFor(Product $product, ?array $variant = null): array
    {
        $variantId = $variant['id'] ?? null;
        $stock = max(0, (int) ($variant === null ? $product->quantity : ($variant['stock'] ?? 0)));
        $reserved = $variant === null ? $this->reservedFor($product) : 0;
        $available = max(0, $stock - $reserved);
        $threshold = $this->thresholdFor($product);

        $status = $this->isPreOrder($product)
            ? self::STATUS_PRE_ORDER
            : $this->statusFromAvailable($available, $threshold);

        return [
            'product'      => $product,
            'product_id'   => $product->id,
            'title'        => (string) $product->title,
            'slug'         => (string) $product->slug,
            'sku'          => $variant === null ? ($product->sku ?? null) : ($variant['sku'] ?? null),
            'variant_id'   => $variantId,
            'variant'      => $variant === null ? null : (string) ($this->describeVariant($variant) ?? ''),
            'stock'        => $stock,
            'reserved'     => $reserved,
            'available'    => $available,
            'threshold'    => $threshold,
            'status'       => $status,
            'status_label' => $this->statusLabel($status),
            'seller_id'    => $product->seller_id,
            'updated_at'   => $product->updated_at,
        ];
    }

    /**
     * Every inventory row of a product: a single row for a plain product, one
     * row per variant for a variant product.
     *
     * @return array<int, array<string, mixed>>
     */
    public function rowsFor(Product $product): array
    {
        $variants = $product->variants ?? [];

        if (empty($variants)) {
            return [$this->rowFor($product)];
        }

        return array_map(
            fn (array $variant) => $this->rowFor($product, $variant),
            array_values($variants),
        );
    }

    /**
     * Per-variant availability keyed by variant id, for the product page's
     * variant picker.
     *
     * @return array<string, array<string, mixed>>
     */
    public function variantAvailability(Product $product): array
    {
        $map = [];

        foreach ($product->variants ?? [] as $variant) {
            $id = $variant['id'] ?? null;

            if ($id === null) {
                continue;
            }

            $available = $this->availableFor($product, (string) $id);
            $status = $this->statusFromAvailable($available, $this->thresholdFor($product));

            $map[(string) $id] = [
                'available'   => $available,
                'status'      => $status,
                'label'       => $this->isPreOrder($product)
                    ? 'Pre-Order'
                    : ($status === self::STATUS_OUT_OF_STOCK
                        ? 'Out of Stock'
                        : ($status === self::STATUS_LOW_STOCK ? 'Only ' . $available . ' left' : $available . ' available')),
                'purchasable' => $available > 0 || $this->isPreOrder($product),
            ];
        }

        return $map;
    }

    // ------------------------------------------------------------------
    // Writes
    // ------------------------------------------------------------------

    /**
     * Commit a sale: the ONE authoritative stock decrement of KDP MART.
     *
     * Called from the checkout transaction, per order line, right after the
     * order item is created. It re-reads the product under a row lock, so two
     * buyers racing for the last unit can never both win — the loser gets a
     * ValidationException, the whole order transaction rolls back, and no
     * order and no stock change is left behind.
     *
     * @param  array<string, mixed>  $context  order_id / order_item_id / actor_id / reference / reason
     *
     * @throws ValidationException
     */
    public function commitSale(Product $product, ?string $variantId, int $quantity, array $context = []): ?InventoryTransaction
    {
        $this->assertPositiveQuantity($quantity);

        if ((int) $product->status !== 1) {
            throw ValidationException::withMessages([
                'quantity' => $this->productLabel($product, $variantId) . ' is no longer available.',
            ]);
        }

        if ($this->isForceOutOfStock($product)) {
            throw ValidationException::withMessages([
                'quantity' => $this->productLabel($product, $variantId) . ' is out of stock.',
            ]);
        }

        // Pre-order items are not stock tracked — no movement, no transaction.
        if ($this->isPreOrder($product)) {
            return null;
        }

        return $this->applyStockChange($product, $variantId, -$quantity, 'sale', $context);
    }

    /**
     * Put stock back after a cancelled order.
     *
     * Restoring is driven by the order ITEM, never by the order status alone,
     * and it is idempotent: it only runs when the line actually consumed stock
     * (a `sale` transaction exists) and has not been released yet
     * (order_items.inventory_released_at). A cancellation therefore restores
     * exactly once — never zero times, never twice.
     */
    public function restoreCancelledOrder(Order $order, ?User $actor = null): int
    {
        $restored = 0;

        foreach ($order->items()->orderBy('id')->get() as $item) {
            if ($this->restoreOrderItem($item, 'cancellation', $actor, 'Order ' . $order->order_number . ' cancelled')) {
                $restored += (int) $item->quantity;
            }
        }

        return $restored;
    }

    /**
     * Restore one order line's stock exactly once.
     *
     * @return bool whether stock was actually returned to inventory
     */
    public function restoreOrderItem(
        OrderItem $item,
        string $type = 'cancellation',
        ?User $actor = null,
        ?string $reason = null,
    ): bool {
        // Already given back -> never give it back twice.
        if ($item->inventory_released_at !== null) {
            return false;
        }

        // The line must actually have consumed stock. Orders created before
        // inventory tracking existed have no `sale` row and must not create
        // phantom stock.
        $consumed = InventoryTransaction::query()
            ->where('order_item_id', $item->id)
            ->where('type', 'sale')
            ->exists();

        if (! $consumed) {
            return false;
        }

        $product = $this->productForOrderItem($item);

        if ($product === null) {
            return false;
        }

        $quantity = max(1, (int) $item->quantity);

        $this->applyStockChange($product, $item->variant_id, $quantity, $type, [
            'order_id'      => $item->order_id,
            'order_item_id' => $item->id,
            'actor_id'      => $actor?->id,
            'reference'     => $item->order?->order_number,
            'reason'        => $reason ?: 'Inventory returned for order line #' . $item->id,
        ]);

        // The guard is written together with the stock movement, so a second
        // cancellation attempt can never restore again.
        $item->forceFill(['inventory_released_at' => now()])->save();

        return true;
    }

    /**
     * Restock a returned item — only when an admin confirmed the product is
     * RESELLABLE. Damaged / non-resellable returns never touch sellable stock.
     *
     * @param  array<string, mixed>  $context
     */
    public function restoreFromReturn(ReturnRequest $return, ?User $actor = null, ?string $reason = null): ?InventoryTransaction
    {
        $quantity = max(1, (int) $return->quantity);
        $product = $this->productForReturn($return);

        if ($product === null) {
            return null;
        }

        $variantId = $return->product_variant_id ?: ($return->orderItem?->variant_id ?? null);

        return $this->applyStockChange($product, $variantId, $quantity, 'return_restock', [
            'return_request_id' => $return->id,
            'order_id'          => $return->order_id,
            'order_item_id'     => $return->order_item_id,
            'actor_id'          => $actor?->id,
            'reference'         => $return->return_number,
            'reason'            => $reason ?: 'Return ' . $return->return_number . ' restocked',
        ]);
    }

    /**
     * Manual correction by a seller or an admin (physical stock received,
     * damaged stock written off, stock-take fix, ...).
     *
     * @param  int  $delta  signed: +5 to add, -2 to remove.
     * @param  array<string, mixed>  $context
     *
     * @throws ValidationException
     */
    public function adjust(
        Product $product,
        ?string $variantId,
        int $delta,
        string $reason,
        ?User $actor = null,
        array $context = [],
    ): InventoryTransaction {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' => 'Please give a reason for the stock adjustment.',
            ]);
        }

        if ($delta === 0) {
            throw ValidationException::withMessages([
                'quantity' => 'The adjustment must change stock by at least 1 unit.',
            ]);
        }

        if (abs($delta) > self::MAX_MOVEMENT) {
            throw ValidationException::withMessages([
                'quantity' => 'That adjustment is too large.',
            ]);
        }

        return $this->applyStockChange($product, $variantId, $delta, 'manual_adjustment', array_merge([
            'actor_id'  => $actor?->id,
            'reason'    => $reason,
            'reference' => $context['reference'] ?? null,
        ], $context));
    }

    /**
     * Record the opening stock of a freshly created product so the history
     * explains where the first number came from. A zero opening stock, or a
     * product that already has one, records nothing (idempotent).
     */
    public function recordInitialStock(Product $product, ?User $actor = null, ?string $reference = null): ?InventoryTransaction
    {
        $alreadyRecorded = InventoryTransaction::query()
            ->where('product_id', $product->id)
            ->whereNull('product_variant_id')
            ->where('type', 'initial_stock')
            ->exists();

        if ($alreadyRecorded) {
            return null;
        }

        $stock = max(0, (int) $product->quantity);

        if ($stock === 0) {
            return null;
        }

        return $this->recordTransaction($product, null, 'initial_stock', $stock, 0, [
            'seller_id' => $product->seller_id,
            'actor_id'  => $actor?->id,
            'reason'    => 'Opening stock',
            'reference' => $reference,
        ]);
    }

    /**
     * Update a product's low-stock trigger (null = use the store default).
     */
    public function setLowStockThreshold(Product $product, ?int $threshold): void
    {
        $product->forceFill([
            'low_stock_threshold' => $threshold === null ? null : max(0, $threshold),
        ])->save();
    }

    /**
     * Write one inventory_transactions row without changing any stock.
     *
     * @param  array<string, mixed>  $context
     */
    public function recordTransaction(
        Product $product,
        ?string $variantId,
        string $type,
        int $quantity,
        ?int $previousStock = null,
        array $context = [],
    ): InventoryTransaction {
        $previousStock ??= $this->stockFor($product, $variantId);

        return InventoryTransaction::create([
            'product_id'         => $product->id,
            'product_variant_id' => $variantId,
            'seller_id'          => $context['seller_id'] ?? $product->seller_id,
            'order_id'           => $context['order_id'] ?? null,
            'order_item_id'      => $context['order_item_id'] ?? null,
            'return_request_id'  => $context['return_request_id'] ?? null,
            'type'               => $type,
            'quantity'           => $quantity,
            'previous_stock'     => $previousStock,
            'new_stock'          => max(0, $previousStock + $quantity),
            'reason'             => $context['reason'] ?? null,
            'reference'          => $context['reference'] ?? null,
            'actor_id'           => $context['actor_id'] ?? null,
        ]);
    }

    /**
     * Set an inventory unit to an absolute stock level (product form save).
     *
     * The product edit form owns the `quantity` / variant stock fields, so a
     * product save IS an explicit inventory operation — but it is written
     * HERE, never by the form handler, so the change is always locked,
     * validated and recorded as one manual_adjustment transaction.
     *
     * Returns null when the level is already the requested one (no movement,
     * no transaction, no notification).
     *
     * Pass `'type' => 'initial_stock'` in $context to record the movement with a
     * different type (used when a brand new variant is added by a product save).
     *
     * @param  array<string, mixed>  $context
     */
    public function setStock(
        Product $product,
        ?string $variantId,
        int $absolute,
        string $reason,
        ?User $actor = null,
        array $context = [],
    ): ?InventoryTransaction {
        $absolute = max(0, $absolute);
        $delta = $absolute - $this->stockFor($product, $variantId);

        if ($delta === 0) {
            return null;
        }

        return $this->applyStockChange($product, $variantId, $delta, $context['type'] ?? 'manual_adjustment', array_merge([
            'actor_id'  => $actor?->id,
            'reason'    => $reason,
            'reference' => $context['reference'] ?? null,
        ], $context));
    }

    /**
     * Apply the stock values a product form save asked for.
     *
     * The product create/edit form owns the `quantity` / variant stock fields,
     * so a product save IS an explicit inventory operation — but the numbers
     * are written HERE (never by the form handler), so every change is locked,
     * validated, recorded as one manual_adjustment transaction and covered by
     * the low-stock alert rules.
     *
     * Price, category, description, images and every other field are ignored
     * on purpose: they must never touch inventory.
     *
     * @param  array<int, array<string, mixed>>  $previousVariants
     * @param  array<int, array<string, mixed>>  $newVariants
     */
    public function applyFormStock(
        Product $product,
        int $previousQuantity,
        array $previousVariants,
        int $newQuantity,
        array $newVariants,
        ?User $actor = null,
    ): void {
        $newQuantity = max(0, $newQuantity);

        // A product that already recorded its opening stock explains every
        // later level with a manual_adjustment instead.
        $hasOpeningStock = InventoryTransaction::query()
            ->where('product_id', $product->id)
            ->whereNull('product_variant_id')
            ->where('type', 'initial_stock')
            ->exists();

        if ($newQuantity !== $previousQuantity && ! $hasOpeningStock) {
            $this->setStock($product, null, $newQuantity, 'Product form stock update', $actor, [
                'reference' => 'Product edit',
            ]);
        }

        $previousById = [];

        foreach ($previousVariants as $variant) {
            if (isset($variant['id'])) {
                $previousById[(string) $variant['id']] = (int) ($variant['stock'] ?? 0);
            }
        }

        foreach ($newVariants as $variant) {
            $id = (string) ($variant['id'] ?? '');
            $stock = max(0, (int) ($variant['stock'] ?? 0));

            if ($id === '') {
                continue;
            }

            if (! array_key_exists($id, $previousById)) {
                // A brand new variant: its opening stock is applied AND
                // explained by an initial_stock row, so the level the seller
                // typed is really stored (the form handler deliberately only
                // carries the variant across, never its stock).
                if ($stock > 0) {
                    $this->setStock($product, $id, $stock, 'New option combination added', $actor, [
                        'type'      => 'initial_stock',
                        'reference' => 'Product edit',
                    ]);
                }

                continue;
            }

            if ($stock !== $previousById[$id]) {
                $this->setStock($product, $id, $stock, 'Product form stock update', $actor, [
                    'reference' => 'Product edit',
                ]);
            }
        }
    }

    // ------------------------------------------------------------------
    // Alert handling
    // ------------------------------------------------------------------

    /**
     * Run a callback with low-stock alerts deferred.
     *
     * Checkout uses this so a seller is never told "low stock" for an order
     * that ends up being rolled back; flushDeferredAlerts() sends them once
     * the transaction really committed.
     */
    public function withoutAlerts(callable $callback): mixed
    {
        $previous = $this->suppressAlerts;
        $this->suppressAlerts = true;

        try {
            return $callback();
        } finally {
            $this->suppressAlerts = $previous;
        }
    }

    /**
     * Send every alert collected while alerts were suppressed.
     */
    public function flushDeferredAlerts(): void
    {
        $alerts = $this->deferredAlerts;
        $this->deferredAlerts = [];

        foreach ($alerts as $alert) {
            $this->sendLowStockAlert(
                $alert['seller_id'],
                $alert['title'],
                $alert['body'],
                $alert['url'],
                $alert['meta'] ?? [],
            );
        }
    }

    // ------------------------------------------------------------------
    // Internals
    // ------------------------------------------------------------------

    /**
     * The ONE place stock is ever written.
     *
     * Runs inside a transaction with the product row locked, refuses to go
     * negative, persists the new level (the product quantity or the variant's
     * JSON stock) and records exactly one history row.
     *
     * @param  array<string, mixed>  $context
     *
     * @throws ValidationException
     */
    private function applyStockChange(
        Product $product,
        ?string $variantId,
        int $delta,
        string $type,
        array $context,
    ): InventoryTransaction {
        $this->assertPositiveQuantity(abs($delta));

        [$transaction, $previousStock, $newStock] = DB::transaction(function () use ($product, $variantId, $delta, $type, $context) {
            // Row lock: a concurrent purchase of the last unit waits here
            // instead of reading a stale level.
            $locked = Product::query()->whereKey($product->getKey())->lockForUpdate()->firstOrFail();

            $previousStock = $this->stockFor($locked, $variantId);
            $newStock = $previousStock + $delta;

            if ($newStock < 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'Only ' . $previousStock . ' ' . $this->units($previousStock)
                        . ' of ' . $this->productLabel($locked, $variantId) . ' are available.',
                ]);
            }

            $this->writeStock($locked, $variantId, $newStock);

            return [
                $this->recordTransaction($locked, $variantId, $type, $delta, $previousStock, $context),
                $previousStock,
                $newStock,
            ];
        });

        // The slug memo (and the catalog's own memo) may now be stale.
        $this->productMemo = [];
        app(ProductCatalogService::class)->flush();

        $this->queueLowStockAlert($product, $variantId, $previousStock, $newStock);

        return $transaction;
    }

    /**
     * Persist a new stock level for the given inventory unit.
     */
    private function writeStock(Product $product, ?string $variantId, int $newStock): void
    {
        if ($variantId === null || $variantId === '') {
            $product->quantity = max(0, $newStock);
            $product->save();

            return;
        }

        $variants = $product->variants ?? [];
        $found = false;

        foreach ($variants as $index => $variant) {
            if (($variant['id'] ?? null) === $variantId) {
                $variants[$index]['stock'] = max(0, $newStock);
                $found = true;
                break;
            }
        }

        if (! $found) {
            throw ValidationException::withMessages([
                'variant_id' => 'The selected option combination is no longer available.',
            ]);
        }

        $product->variants = array_values($variants);
        $product->save();
    }

    /**
     * Locate a variant inside the product's `variants` JSON.
     *
     * @return array<string, mixed>|null
     */
    public function findVariant(Product $product, ?string $variantId): ?array
    {
        if ($variantId === null || $variantId === '') {
            return null;
        }

        foreach ($product->variants ?? [] as $variant) {
            if (($variant['id'] ?? null) === $variantId) {
                return $variant;
            }
        }

        return null;
    }

    /**
     * "Size: M | Color: Black" for a variant row.
     */
    private function describeVariant(array $variant): ?string
    {
        $parts = [];

        foreach (($variant['values'] ?? []) as $name => $value) {
            $parts[] = $name . ': ' . $value;
        }

        return $parts === [] ? null : implode(' | ', $parts);
    }

    private function variantLabel(Product $product, string $variantId): string
    {
        $variant = $this->findVariant($product, $variantId);

        return $variant === null ? $variantId : ($this->describeVariant($variant) ?? $variantId);
    }

    private function productLabel(Product $product, ?string $variantId): string
    {
        $title = (string) $product->title;

        if ($variantId === null || $variantId === '') {
            return $title;
        }

        return $title . ' (' . $this->variantLabel($product, $variantId) . ')';
    }

    private function units(int $count): string
    {
        return $count === 1 ? 'unit' : 'units';
    }

    /**
     * Quantities are always whole, positive numbers (no decimals, no
     * negatives, no junk strings reaching the arithmetic).
     */
    private function assertPositiveQuantity(int $quantity): void
    {
        if ($quantity < 1 || $quantity > self::MAX_MOVEMENT) {
            throw ValidationException::withMessages([
                'quantity' => 'Please enter a whole number between 1 and ' . self::MAX_MOVEMENT . '.',
            ]);
        }
    }

    /**
     * The product an order line consumed, preferring the stored id and
     * falling back to the slug snapshot (products may have been renamed).
     */
    public function productForOrderItem(OrderItem $item): ?Product
    {
        if ($item->product_id) {
            $product = Product::find($item->product_id);

            if ($product) {
                return $product;
            }
        }

        return $this->productBySlug($item->product_slug);
    }

    /**
     * The product a return refers to.
     */
    public function productForReturn(ReturnRequest $return): ?Product
    {
        if ($return->product_id) {
            $product = Product::find($return->product_id);

            if ($product) {
                return $product;
            }
        }

        return $this->productBySlug($return->product_slug);
    }

    /**
     * Notify the seller exactly once when an inventory unit CROSSES into the
     * low-stock (or out-of-stock) state — never when a page is opened, and
     * never on every decrement that stays low.
     */
    private function queueLowStockAlert(Product $product, ?string $variantId, int $previousStock, int $newStock): void
    {
        if ($this->isPreOrder($product) || $product->seller_id === null) {
            return;
        }

        $fresh = Product::find($product->getKey());

        if (! $fresh) {
            return;
        }

        $threshold = $this->thresholdFor($fresh);
        $reserved = $this->reservedFor($fresh, $variantId);

        $previousAvailable = max(0, $previousStock - $reserved);
        $newAvailable = max(0, $newStock - $reserved);

        $previousStatus = $this->statusFromAvailable($previousAvailable, $threshold);
        $newStatus = $this->statusFromAvailable($newAvailable, $threshold);

        if ($previousStatus !== self::STATUS_IN_STOCK || $newStatus === self::STATUS_IN_STOCK) {
            return;
        }

        $label = $this->productLabel($fresh, $variantId);

        $this->queueAlert([
            'seller_id' => (int) $fresh->seller_id,
            'title'     => $newStatus === self::STATUS_OUT_OF_STOCK ? 'Out of Stock Alert' : 'Low Stock Alert',
            'body'      => $newStatus === self::STATUS_OUT_OF_STOCK
                ? $label . ' is now out of stock.'
                : $label . ' — only ' . $newAvailable . ' ' . $this->units($newAvailable) . ' remain.',
            'url'       => route('seller.inventory.index'),
            'meta'      => [
                'type'       => $newStatus === self::STATUS_OUT_OF_STOCK ? 'out_of_stock' : 'low_stock',
                'product_id' => $fresh->id,
                'variant_id' => $variantId,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $alert
     */
    private function queueAlert(array $alert): void
    {
        if ($this->suppressAlerts) {
            $this->deferredAlerts[] = $alert;

            return;
        }

        $this->sendLowStockAlert($alert['seller_id'], $alert['title'], $alert['body'], $alert['url'], $alert['meta'] ?? []);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function sendLowStockAlert(?int $sellerId, string $title, string $body, string $url, array $meta = []): void
    {
        if (! $sellerId) {
            return;
        }

        $seller = User::find($sellerId);

        if (! $seller) {
            return;
        }

        try {
            $seller->notify(new StoreAlert($title, $body, $url, $meta));
        } catch (Throwable $e) {
            // A notification failure must never break the stock movement.
            Log::error('Low stock notification failed for user ' . $sellerId . ': ' . $e->getMessage());
        }
    }
}
