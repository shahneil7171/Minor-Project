<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PHASE 3 — Inventory history / audit trail.
 *
 * One row per REAL inventory change so a seller/admin can always answer
 * "why did this stock level change?".
 *
 * Design notes (matching the existing KDP MART architecture):
 *
 * - KDP MART stores products in the relational `products` table
 *   (products.quantity = the product-level stock) and product VARIANTS inside
 *   the product row's `variants` JSON column (each variant carries its own
 *   `stock`). There is therefore no `product_variants` table to point a
 *   foreign key at, so `product_variant_id` is a STRING holding the stable
 *   variant id that already lives in `products.variants` (e.g. "v1a2b3c4d5e6f").
 *   This deliberately avoids creating a SECOND source of truth for variant
 *   stock.
 *
 * - NO foreign keys are declared on purpose. Inventory history is an audit
 *   trail: it must survive a product, order or return being deleted/archived
 *   so historical order lines stay understandable (see the existing product
 *   delete flow). Every reference is a plain indexed column.
 *
 * - `quantity` is SIGNED: negative = stock left the inventory (sale),
 *   positive = stock came back (restock / cancellation / adjustment).
 *   `previous_stock` / `new_stock` are the stock level of the exact inventory
 *   unit (the product row, or the variant inside the product row) BEFORE and
 *   AFTER the change.
 *
 * Purely additive: no existing table, row or column is modified or removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('inventory_transactions')) {
            return;
        }

        Schema::create('inventory_transactions', function (Blueprint $table) {
            $table->id();

            // What changed.
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('product_variant_id', 64)->nullable();

            // Whose inventory it was.
            $table->unsignedBigInteger('seller_id')->nullable();

            // Why it changed (traceability back to the business event).
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('order_item_id')->nullable();
            $table->unsignedBigInteger('return_request_id')->nullable();

            // One of App\Models\InventoryTransaction::TYPES.
            $table->string('type', 30);

            // Signed delta applied to the stock level.
            $table->integer('quantity');

            // Stock level of this inventory unit before / after the change.
            $table->integer('previous_stock')->default(0);
            $table->integer('new_stock')->default(0);

            $table->text('reason')->nullable();
            $table->string('reference', 120)->nullable();

            // Who performed the change (seller, admin, system...).
            $table->unsignedBigInteger('actor_id')->nullable();

            $table->timestamps();

            $table->index(['product_id', 'product_variant_id']);
            $table->index('seller_id');
            $table->index('order_id');
            $table->index('order_item_id');
            $table->index('return_request_id');
            $table->index(['type', 'created_at']);
            $table->index('actor_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_transactions');
    }
};
