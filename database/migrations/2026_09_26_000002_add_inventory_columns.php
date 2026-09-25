<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PHASE 3 — Inventory columns (purely additive, no existing data touched).
 *
 * 1) products
 *    - `reserved`              : quantity held back for orders. The existing
 *                                `products.quantity` column is REUSED as the
 *                                product-level stock; nothing is duplicated.
 *    - `low_stock_threshold`   : per-product low-stock trigger. NULL means
 *                                "use the store-wide default"
 *                                (App\Models\Setting::lowStockThreshold()).
 *
 * 2) order_items
 *    - `product_id` / `variant_id` : snapshot of WHICH inventory unit the line
 *      consumed. `product_slug` already exists and stays the readable
 *      snapshot; these columns let the inventory layer restore the exact
 *      variant later (e.g. when the order is cancelled or returned) even if
 *      the product was renamed in the meantime.
 *    - `inventory_released_at` : set exactly once when the line's stock has
 *      been returned to inventory. This is the idempotency guard that makes a
 *      cancellation restore happen ONCE and never twice.
 *
 * 3) returns
 *    - `product_variant_id`    : the variant the return is about.
 *    - `inventory_condition`   : resellable | damaged | non_resellable — a
 *      returned product is only put back into sellable stock once an admin
 *      confirms it is resellable.
 *    - `restocked_at` / `restocked_quantity` : the idempotency guard for the
 *      "Restock" action (clicking it twice must never add stock twice).
 *
 * Every column is nullable/guarded with Schema::hasColumn so the migration is
 * safe to run against the existing database and against a fresh one.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                if (! Schema::hasColumn('products', 'reserved')) {
                    $table->unsignedInteger('reserved')->default(0)->after('quantity');
                }

                if (! Schema::hasColumn('products', 'low_stock_threshold')) {
                    $table->unsignedInteger('low_stock_threshold')->nullable()->after('reserved');
                }
            });
        }

        if (Schema::hasTable('order_items')) {
            Schema::table('order_items', function (Blueprint $table) {
                if (! Schema::hasColumn('order_items', 'product_id')) {
                    $table->unsignedBigInteger('product_id')->nullable()->after('product_slug');
                }

                if (! Schema::hasColumn('order_items', 'variant_id')) {
                    $table->string('variant_id', 64)->nullable()->after('product_id');
                }

                if (! Schema::hasColumn('order_items', 'inventory_released_at')) {
                    $table->timestamp('inventory_released_at')->nullable()->after('variant_id');
                }
            });

            Schema::table('order_items', function (Blueprint $table) {
                // Plain indexes (no foreign keys): an order line must stay
                // readable even after its product is deleted/archived.
                if (Schema::hasColumn('order_items', 'product_id')) {
                    $table->index('product_id');
                }
                if (Schema::hasColumn('order_items', 'variant_id')) {
                    $table->index('variant_id');
                }
            });
        }

        if (Schema::hasTable('returns')) {
            Schema::table('returns', function (Blueprint $table) {
                if (! Schema::hasColumn('returns', 'product_variant_id')) {
                    $table->string('product_variant_id', 64)->nullable()->after('product_id');
                }

                if (! Schema::hasColumn('returns', 'inventory_condition')) {
                    $table->string('inventory_condition', 30)->nullable()->after('received_at');
                }

                if (! Schema::hasColumn('returns', 'restocked_at')) {
                    $table->timestamp('restocked_at')->nullable()->after('inventory_condition');
                }

                if (! Schema::hasColumn('returns', 'restocked_quantity')) {
                    $table->unsignedInteger('restocked_quantity')->nullable()->after('restocked_at');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('returns')) {
            Schema::table('returns', function (Blueprint $table) {
                foreach (['restocked_quantity', 'restocked_at', 'inventory_condition', 'product_variant_id'] as $column) {
                    if (Schema::hasColumn('returns', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('order_items')) {
            Schema::table('order_items', function (Blueprint $table) {
                foreach (['inventory_released_at', 'variant_id', 'product_id'] as $column) {
                    if (Schema::hasColumn('order_items', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('products')) {
            Schema::table('products', function (Blueprint $table) {
                foreach (['low_stock_threshold', 'reserved'] as $column) {
                    if (Schema::hasColumn('products', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};