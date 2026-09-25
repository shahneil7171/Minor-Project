<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A buyer return & refund request for one order line.
 *
 * The workflow (tracked through $status) is:
 *
 *   pending -> approved -> pickup_assigned -> picked_up -> received
 *           -> refund_processing -> refunded -> completed
 *   pending/approved -> rejected (terminal, requires a reason)
 *   pending/approved -> cancelled (terminal)
 *
 * The stored canonical status for the pickup step is `pickup_assigned`
 * (the spec vocabulary); the legacy `pickup_scheduled` value is accepted as
 * an alias and normalized on save so existing rows keep working.
 *
 * Refund accounting is kept separate from the return status (see
 * REFUND_STATUSES): a return can be approved long before money is refunded.
 * Refunds are tracked, not transferred — no payment-gateway refund API is
 * integrated in this project.
 */
class ReturnRequest extends Model
{
    protected $table = 'returns';

    protected $fillable = [
        'order_id',
        'order_item_id',
        'user_id',
        'order_number',
        'customer_email',
        'product_slug',
        'product_id',
        'product_variant_id',
        'seller_id',
        'product_title',
        'quantity',
        'reason',
        'description',
        'images',
        'status',
        'refund_status',
        'refund_amount',
        'shipping_refund_amount',
        'refund_reference',
        'return_deadline',
        'requested_at',
        'approved_at',
        'rejected_at',
        'completed_at',
        'refunded_at',
        'rejection_reason',
        'admin_note',
        'delivery_partner_id',
        'assigned_by',
        'pickup_notes',
        'pickup_scheduled_at',
        'pickup_assigned_at',
        'picked_up_at',
        'received_at',
        'inspected_at',
        'refund_processing_at',
        // PHASE 3 inventory: the variant concerned, the condition the admin
        // recorded after inspecting the returned item, and the idempotency
        // guard that makes a restock happen exactly once.
        'inventory_condition',
        'restocked_at',
        'restocked_quantity',
    ];

    /**
     * The condition a returned product can be given after inspection.
     *
     * Only `resellable` puts units back into sellable stock; damaged and
     * non-resellable returns are recorded for the audit trail but never
     * increase the stock a buyer can purchase.
     */
    public const INVENTORY_CONDITIONS = [
        'resellable',
        'damaged',
        'non_resellable',
    ];

    /**
     * Human labels for the inspection result.
     */
    public const INVENTORY_CONDITION_LABELS = [
        'resellable'     => 'Resellable',
        'damaged'        => 'Damaged',
        'non_resellable' => 'Non-resellable',
    ];

    /**
     * The return statuses in which a returned product is physically at the
     * warehouse and can therefore be inspected and restocked.
     */
    public const INSPECTABLE_STATUSES = [
        'received',
        'inspected',
        'refund_processing',
        'refunded',
        'completed',
    ];

    protected $casts = [
        'images' => 'array',
        'quantity' => 'integer',
        'refund_amount' => 'decimal:2',
        'shipping_refund_amount' => 'decimal:2',
        'return_deadline' => 'datetime',
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'completed_at' => 'datetime',
        'refunded_at' => 'datetime',
        'pickup_scheduled_at' => 'datetime',
        'pickup_assigned_at' => 'datetime',
        'picked_up_at' => 'datetime',
        'received_at' => 'datetime',
        'inspected_at' => 'datetime',
        'refund_processing_at' => 'datetime',
        'restocked_at' => 'datetime',
        'restocked_quantity' => 'integer',
    ];

    /**
     * Return lifecycle statuses (canonical spec vocabulary).
     */
    public const STATUSES = [
        'pending',
        'approved',
        'rejected',
        'pickup_assigned',
        'picked_up',
        'received',
        'refund_processing',
        'refunded',
        'cancelled',
        'completed',
    ];

    /**
     * Legacy values still accepted (normalized to canonical on save/boot).
     * `pickup_scheduled` == `pickup_assigned`; `inspected` is a received-state
     * alias kept readable for rows created before completion.
     */
    public const STATUS_ALIASES = [
        'pickup_scheduled' => 'pickup_assigned',
        'pickup_assigned' => 'pickup_assigned',
        'inspected' => 'received',
    ];

    public const STATUS_LABELS = [
        'pending'           => 'Return Requested',
        'approved'          => 'Return Approved',
        'rejected'          => 'Return Rejected',
        'pickup_assigned'   => 'Pickup Assigned',
        'pickup_scheduled'  => 'Pickup Assigned',
        'picked_up'         => 'Product Picked Up',
        'received'          => 'Return Received',
        'inspected'         => 'Return Received',
        'refund_processing' => 'Refund Processing',
        'refunded'          => 'Refunded',
        'cancelled'         => 'Cancelled',
        'completed'         => 'Return Completed',
    ];

    /**
     * Statuses that consume the item's returnable quantity — the quantity is
     * "locked" so the same unit can never be returned twice.
     */
    public const LOCKING_STATUSES = [
        'pending',
        'approved',
        'pickup_assigned',
        'pickup_scheduled',
        'picked_up',
        'received',
        'inspected',
        'refund_processing',
        'refunded',
        'completed',
    ];

    /**
     * Statuses that end the request without consuming returnable quantity.
     */
    public const TERMINAL_STATUSES = ['rejected', 'cancelled', 'refunded', 'completed'];

    /**
     * Refund tracking, kept separate from the return status.
     */
    public const REFUND_STATUSES = ['none', 'pending', 'processing', 'refunded'];

    public const REFUND_STATUS_LABELS = [
        'none'       => 'Not Applicable',
        'pending'    => 'Pending',
        'processing' => 'Refund Processing',
        'refunded'   => 'Refunded',
    ];

    /**
     * The reasons a buyer may choose from on the return form.
     */
    public const REASONS = [
        'Product damaged',
        'Product defective',
        'Wrong product received',
        'Product not as described',
        'Missing parts/accessories',
        'Size/fit issue',
        'Changed my mind',
        'Other',
    ];

    /**
     * The order this return belongs to.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * The exact order line being returned (drives quantity + refund math).
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /**
     * The catalog product behind the line (when it can be resolved).
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * The buyer who raised the request.
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The seller whose product is being returned.
     */
    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    /**
     * The delivery partner assigned to collect the parcel.
     */
    public function deliveryPartner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delivery_partner_id');
    }

    /**
     * The admin who assigned the pickup.
     */
    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    /**
     * Human readable return request number (for example RET-0001).
     */
    public function getReturnNumberAttribute(): string
    {
        return 'RET-' . str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? ucfirst((string) $this->status);
    }

    public function refundStatusLabel(): string
    {
        return self::REFUND_STATUS_LABELS[$this->refund_status] ?? ucfirst((string) $this->refund_status);
    }

    public function isRefunded(): bool
    {
        return $this->refund_status === 'refunded';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * Whether this request still holds the item's returnable quantity.
     */
    public function locksQuantity(): bool
    {
        return in_array($this->status, self::LOCKING_STATUSES, true);
    }

    /**
     * Whether the request is still being processed (not rejected/cancelled).
     */
    public function isActive(): bool
    {
        return ! in_array($this->status, ['rejected', 'cancelled'], true);
    }

    /**
     * Whether a return pickup has to happen for this request.
     */
    public function needsPickup(): bool
    {
        return in_array($this->normalizedStatus(), ['approved', 'pickup_assigned', 'picked_up'], true);
    }

    /**
     * Canonical status: legacy `pickup_scheduled` reads as `pickup_assigned`.
     */
    public function normalizedStatus(): string
    {
        return self::STATUS_ALIASES[$this->status] ?? (string) $this->status;
    }

    /**
     * Normalize legacy statuses on save so the database converges on the
     * canonical spec vocabulary without losing existing rows.
     */
    protected static function booted(): void
    {
        static::saving(function (ReturnRequest $return): void {
            $alias = self::STATUS_ALIASES[$return->status] ?? null;
            if ($alias !== null && $return->status !== $alias) {
                $return->status = $alias;
            }
            if (in_array($return->status, ['pickup_assigned'], true)) {
                $assignedAt = $return->pickup_assigned_at ?? $return->pickup_scheduled_at;
                $return->pickup_assigned_at = $assignedAt;
                $return->pickup_scheduled_at = $assignedAt;
            }
        });
    }

    /**
     * Every status-change audit entry for this request (return history,
     * separate from order history).
     */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(ReturnStatusHistory::class)->orderBy('id');
    }

    /**
     * Buyer-facing tracking timeline.
     *
     * Each entry is ['label' => string, 'state' => done|current|todo] and
     * mirrors the step list rendered on the return detail page.
     *
     * @return array<int, array{label: string, state: string}>
     */
    public function timeline(): array
    {
        if ($this->status === 'rejected') {
            return [
                ['label' => 'Return Requested', 'state' => 'done'],
                ['label' => 'Rejected', 'state' => 'current'],
            ];
        }

        if ($this->status === 'cancelled') {
            return [
                ['label' => 'Return Requested', 'state' => 'done'],
                ['label' => 'Cancelled', 'state' => 'current'],
            ];
        }

        // Position of the current status on the happy path.
        $positions = [
            'pending'           => 0,
            'approved'          => 1,
            'pickup_assigned'   => 2,
            'pickup_scheduled'  => 2,
            'picked_up'         => 3,
            'received'          => 4,
            'inspected'         => 5,
            'refund_processing' => 6,
            'refunded'          => 7,
            'completed'         => 8,
        ];

        $current = $positions[$this->status] ?? 0;

        $steps = [
            ['label' => 'Return Requested', 'at' => 0],
            ['label' => 'Return Approved', 'at' => 1],
            ['label' => 'Pickup Assigned', 'at' => 2],
            ['label' => 'Product Picked Up', 'at' => 3],
            ['label' => 'Return Received', 'at' => 4],
            ['label' => 'Refund Processing', 'at' => 6],
            ['label' => 'Refunded', 'at' => 7],
            ['label' => 'Return Completed', 'at' => 8],
        ];

        return array_map(function (array $step) use ($current): array {
            $state = 'todo';

            if ($current > $step['at']) {
                $state = 'done';
            } elseif ($current === $step['at']) {
                $state = 'current';
            }

            return ['label' => $step['label'], 'state' => $state];
        }, $steps);
    }

    /**
     * Evidence images as publicly reachable URLs.
     *
     * @return array<int, string>
     */
    public function imageUrls(): array
    {
        return array_values(array_map(
            fn (string $path) => asset('storage/' . $path),
            $this->images ?? []
        ));
    }

    /**
     * Whether stock has already been given back for this return. The guard
     * that makes a "Restock" click idempotent.
     */
    public function isRestocked(): bool
    {
        return $this->restocked_at !== null;
    }

    /**
     * Whether the returned product can be inspected / restocked yet (it must
     * have been received first).
     */
    public function isInspectable(): bool
    {
        return in_array($this->status, self::INSPECTABLE_STATUSES, true);
    }

    /**
     * The recorded inspection result, e.g. "Resellable".
     */
    public function inventoryConditionLabel(): ?string
    {
        return $this->inventory_condition === null
            ? null
            : (self::INVENTORY_CONDITION_LABELS[$this->inventory_condition] ?? $this->inventory_condition);
    }

    /**
     * Every inventory movement caused by this return.
     */
    public function inventoryTransactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    /**
     * Filter by a single status ("all" or null disables the filter).
     */
    public function scopeStatus(Builder $query, ?string $status): Builder
    {
        if ($status !== null && $status !== 'all') {
            $canonical = self::STATUS_ALIASES[$status] ?? $status;
            if (in_array($canonical, self::STATUSES, true)) {
                // `pickup_assigned` also matches legacy `pickup_scheduled` rows.
                if ($canonical === 'pickup_assigned') {
                    $query->whereIn('status', ['pickup_assigned', 'pickup_scheduled']);
                } else {
                    $query->where('status', $canonical);
                }
            } elseif (in_array($status, ['pickup_scheduled', 'inspected'], true)) {
                $query->where('status', $status);
            }
        }

        return $query;
    }

    /**
     * Only requests for the given seller's products.
     */
    public function scopeForSeller(Builder $query, int $sellerId): Builder
    {
        return $query->where('seller_id', $sellerId);
    }

    /**
     * Only pickups assigned to the given delivery partner.
     */
    public function scopeForDeliveryPartner(Builder $query, int $partnerId): Builder
    {
        return $query->where('delivery_partner_id', $partnerId);
    }
}
