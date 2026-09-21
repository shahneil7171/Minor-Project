<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 1 / Step 1 — normalise the order lifecycle onto the canonical
 * status vocabulary and record the lifecycle timestamps.
 *
 * Existing data is NEVER destroyed:
 *
 *   approved         -> confirmed
 *   packed           -> ready_for_pickup
 *   shipped          -> out_for_delivery
 *
 * `assigned` and `picked_up` become first-class ORDER statuses (they already
 * exist on the separate order_deliveries lifecycle).
 *
 * Timestamps: the existing `approved_at` column IS the confirmation
 * timestamp and is reused (no duplicate confirmed_at column is added); only
 * the genuinely missing lifecycle timestamps are appended as nullable
 * columns so old orders keep loading untouched.
 *
 * Laravel handles both MySQL (ALTER TABLE ... MODIFY) and SQLite (native
 * table rebuild) for the enum modifications — same additive pattern as the
 * earlier "packed"/"approved" status migrations.
 */
return new class extends Migration
{
    /**
     * Legacy (pre-lifecycle) status values that were renamed.
     */
    private const LEGACY_STATUSES = ['approved', 'packed', 'shipped'];

    /**
     * Widened enum: legacy + canonical, so rows can be converted on MySQL
     * (an enum only accepts declared values while the data is being mapped).
     */
    private const WIDE_STATUSES = [
        'pending',
        'approved',
        'confirmed',
        'processing',
        'packed',
        'ready_for_pickup',
        'assigned',
        'picked_up',
        'shipped',
        'out_for_delivery',
        'delivered',
        'cancelled',
    ];

    /**
     * The final canonical lifecycle vocabulary.
     */
    private const CANONICAL_STATUSES = [
        'pending',
        'confirmed',
        'processing',
        'ready_for_pickup',
        'assigned',
        'picked_up',
        'out_for_delivery',
        'delivered',
        'cancelled',
    ];

    /**
     * New, nullable lifecycle timestamps.
     *
     * `confirmed_at` is deliberately absent: `approved_at` already records
     * that exact moment and is reused (Order also exposes it as confirmed_at).
     */
    private const TIMESTAMP_COLUMNS = [
        'processing_at',
        'ready_for_pickup_at',
        'assigned_at',
        'picked_up_at',
        'out_for_delivery_at',
        'delivered_at',
        'cancelled_at',
    ];

    /**
     * Position of each canonical status on the forward timeline.
     */
    private const ORDER_POSITIONS = [
        'pending'          => 0,
        'confirmed'        => 1,
        'processing'       => 2,
        'ready_for_pickup' => 3,
        'assigned'         => 4,
        'picked_up'        => 5,
        'out_for_delivery' => 6,
        'delivered'        => 7,
    ];

    public function up(): void
    {
        // 1. Lifecycle timestamps (nullable — existing orders stay valid).
        Schema::table('orders', function (Blueprint $table) {
            foreach (self::TIMESTAMP_COLUMNS as $column) {
                if (! Schema::hasColumn('orders', $column)) {
                    $table->timestamp($column)->nullable();
                }
            }
        });

        // 2. Widen the enum so both vocabularies can coexist during the map.
        $this->setStatusEnum(self::WIDE_STATUSES);

        // 3. Map the legacy values onto the canonical ones (same rows/ids).
        DB::table('orders')->where('status', 'approved')->update(['status' => 'confirmed']);
        DB::table('orders')->where('status', 'packed')->update(['status' => 'ready_for_pickup']);
        DB::table('orders')->where('status', 'shipped')->update(['status' => 'out_for_delivery']);

        // 4. Backfill order-level timestamps from the delivery row (the only
        //    trustworthy source we already have) — never inventing dates.
        $this->backfillTimestampsFromDeliveries();

        // 5. Keep legacy orders coherent with the separate delivery layer:
        //    when a delivery row is already further along than its order,
        //    advance the order forward. Forward-only; finished orders are
        //    left exactly as they are.
        $this->syncOrdersForwardFromDeliveries();

        // 6. Narrow the enum back down to the canonical vocabulary once no
        //    legacy value is left anywhere (guarded for safety).
        if (! DB::table('orders')->whereIn('status', self::LEGACY_STATUSES)->exists()) {
            $this->setStatusEnum(self::CANONICAL_STATUSES);
        }

        // 7. Seed the audit trail for orders that predate the history table
        //    so no order renders an empty timeline. System-generated entry:
        //    no actor, with an explicit note (changed_by stays NULL).
        $this->backfillStatusHistory();
    }

    public function down(): void
    {
        // Restore the widened enum first so the reverse mapping is allowed.
        $this->setStatusEnum(self::WIDE_STATUSES);

        DB::table('orders')->where('status', 'confirmed')->update(['status' => 'approved']);
        DB::table('orders')->where('status', 'ready_for_pickup')->update(['status' => 'packed']);
        DB::table('orders')->where('status', 'out_for_delivery')->update(['status' => 'shipped']);

        // Statuses that did not exist in the legacy vocabulary fall back onto
        // the closest pre-delivery step so the legacy enum stays valid.
        DB::table('orders')->whereIn('status', ['assigned', 'picked_up'])->update(['status' => 'packed']);

        if (! DB::table('orders')
            ->whereIn('status', ['confirmed', 'ready_for_pickup', 'assigned', 'picked_up', 'out_for_delivery'])
            ->exists()) {
            $this->setStatusEnum(['pending', 'approved', 'processing', 'packed', 'shipped', 'delivered', 'cancelled']);
        }

        Schema::table('orders', function (Blueprint $table) {
            foreach (self::TIMESTAMP_COLUMNS as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    /**
     * Replace the orders.status enum with the given vocabulary.
     *
     * @param  array<int, string>  $values
     */
    private function setStatusEnum(array $values): void
    {
        Schema::table('orders', function (Blueprint $table) use ($values) {
            $table->enum('status', $values)->default('pending')->change();
        });
    }

    /**
     * Copy the delivery-layer timestamps onto the order (NULL columns only).
     */
    private function backfillTimestampsFromDeliveries(): void
    {
        if (! Schema::hasTable('order_deliveries')) {
            return;
        }

        $map = [
            'assigned_at'         => 'assigned_at',
            'picked_up_at'        => 'picked_up_at',
            'out_for_delivery_at' => 'out_for_delivery_at',
            'delivered_at'        => 'delivered_at',
        ];

        DB::table('order_deliveries')
            ->select(array_merge(['id', 'order_id'], array_keys($map)))
            ->orderBy('id')
            ->chunk(200, function ($deliveries) use ($map): void {
                foreach ($deliveries as $delivery) {
                    foreach ($map as $deliveryColumn => $orderColumn) {
                        if ($delivery->{$deliveryColumn} === null) {
                            continue;
                        }

                        DB::table('orders')
                            ->where('id', $delivery->order_id)
                            ->whereNull($orderColumn)
                            ->update([$orderColumn => $delivery->{$deliveryColumn}]);
                    }
                }
            });
    }

    /**
     * Advance legacy orders that are behind their own (already validated)
     * delivery row. Forward-only: a status is never moved backwards and
     * delivered/cancelled orders are never touched.
     */
    private function syncOrdersForwardFromDeliveries(): void
    {
        if (! Schema::hasTable('order_deliveries')) {
            return;
        }

        // Delivery status => the equivalent ORDER status.
        $map = [
            'assigned'         => 'assigned',
            'ready_for_pickup' => 'ready_for_pickup',
            'picked_up'        => 'picked_up',
            'out_for_delivery' => 'out_for_delivery',
            'delivered'        => 'delivered',
        ];

        DB::table('order_deliveries')
            ->select(['id', 'order_id', 'status'])
            ->whereIn('status', array_keys($map))
            ->orderBy('id')
            ->chunk(200, function ($deliveries) use ($map): void {
                foreach ($deliveries as $delivery) {
                    $target = $map[$delivery->status];

                    $order = DB::table('orders')->where('id', $delivery->order_id)->first(['id', 'status']);

                    if (! $order || ! isset(self::ORDER_POSITIONS[$order->status])) {
                        continue;
                    }

                    // Finished orders are terminal — never rewrite them.
                    if (in_array($order->status, ['delivered', 'cancelled'], true)) {
                        continue;
                    }

                    // Only ever move forward (delivery is further along).
                    if (self::ORDER_POSITIONS[$order->status] >= self::ORDER_POSITIONS[$target]) {
                        continue;
                    }

                    DB::table('orders')->where('id', $order->id)->update(['status' => $target]);
                }
            });
    }

    /**
     * Create one audit entry per pre-existing order.
     */
    private function backfillStatusHistory(): void
    {
        if (! Schema::hasTable('order_status_histories')) {
            return;
        }

        $now = now();

        DB::table('orders')
            ->select(['id', 'status', 'created_at'])
            ->orderBy('id')
            ->chunk(200, function ($orders) use ($now): void {
                $rows = [];

                foreach ($orders as $order) {
                    $rows[] = [
                        'order_id'    => $order->id,
                        'from_status' => null,
                        'to_status'   => $order->status,
                        'changed_by'  => null,
                        'note'        => 'Status recorded during the order lifecycle upgrade.',
                        'created_at'  => $order->created_at ?? $now,
                        'updated_at'  => $now,
                    ];
                }

                if ($rows !== []) {
                    DB::table('order_status_histories')->insert($rows);
                }
            });
    }
};

