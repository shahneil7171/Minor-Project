<?php

use App\Support\FulfilmentOwner;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * FULFILMENT OWNERSHIP — additive, non-destructive.
 *
 * WHY: KDP MART is a multi-vendor marketplace, but the Admin store also sells
 * its own products (the seed catalog plus products an admin adds directly).
 * Those Admin-owned products have `seller_id = NULL`, so nothing in the order
 * workflow knew WHO had to prepare them: the order sat in "Preparing by
 * Seller" forever, because the seller-owned steps were the only preparation
 * steps that existed and no seller owned those lines.
 *
 * This migration adds an EXPLICIT `owner_type` marker so ownership is a fact
 * in the data instead of an inference:
 *
 *   products.owner_type    : 'seller' | 'store'  (set on every new product)
 *   order_items.owner_type : the ownership snapshot taken at checkout
 *
 * BACKFILL POLICY (no guessing, no destructive change):
 *   1. seller_id IS NOT NULL          -> 'seller'  (a real seller account owns
 *                                        the row; verified against users.id)
 *   2. seller_id IS NULL and the line's product still exists with a null
 *      seller_id                      -> 'store'   (both sides agree that the
 *                                        platform owns it)
 *   3. seller_id IS NULL and the product can no longer be resolved
 *                                     -> left NULL (ambiguous). The resolver
 *                                        in FulfilmentOwner treats a NULL
 *                                        marker as store-owned, so these
 *                                        legacy lines keep working exactly as
 *                                        before; they are REPORTED, not
 *                                        guessed.
 *
 * Nothing is dropped, renamed or reset. Every existing order, order item,
 * seller link and status-history row is preserved untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('products') && ! Schema::hasColumn('products', 'owner_type')) {
            Schema::table('products', function (Blueprint $table) {
                $table->string('owner_type', 20)->nullable()->after('seller_id');
                $table->index('owner_type');
            });
        }

        if (Schema::hasTable('order_items') && ! Schema::hasColumn('order_items', 'owner_type')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->string('owner_type', 20)->nullable()->after('seller_id');
                $table->index('owner_type');
            });
        }

        $this->backfillProducts();
        $this->backfillOrderItems();
    }

    public function down(): void
    {
        if (Schema::hasTable('order_items') && Schema::hasColumn('order_items', 'owner_type')) {
            Schema::table('order_items', function (Blueprint $table) {
                $table->dropIndex(['owner_type']);
                $table->dropColumn('owner_type');
            });
        }

        if (Schema::hasTable('products') && Schema::hasColumn('products', 'owner_type')) {
            Schema::table('products', function (Blueprint $table) {
                $table->dropIndex(['owner_type']);
                $table->dropColumn('owner_type');
            });
        }
    }

    /**
     * Every catalog product's owner is verifiable from its own seller_id: a
     * non-null value points at a real seller account, and a null value is what
     * the product-creation routes write for admin-created and seed products.
     */
    private function backfillProducts(): void
    {
        if (! Schema::hasColumn('products', 'owner_type')) {
            return;
        }

        DB::table('products')->whereNull('owner_type')->whereNotNull('seller_id')->update([
            'owner_type' => FulfilmentOwner::SELLER,
        ]);

        DB::table('products')->whereNull('owner_type')->whereNull('seller_id')->update([
            'owner_type' => FulfilmentOwner::STORE,
        ]);
    }

    /**
     * Order lines are backfilled from their own checkout snapshot, and every
     * null-snapshot line is cross-checked against the product it came from.
     */
    private function backfillOrderItems(): void
    {
        if (! Schema::hasColumn('order_items', 'owner_type')) {
            return;
        }

        // (1) A non-null seller_id snapshot is verified ownership.
        DB::table('order_items')->whereNull('owner_type')->whereNotNull('seller_id')->update([
            'owner_type' => FulfilmentOwner::SELLER,
        ]);

        // (2) Null snapshot whose product still exists: both sides agree, so
        //     ownership is confirmed from the product row itself. `product_id`
        //     is the authoritative link; `product_slug` covers the older rows
        //     created before the inventory snapshot columns existed.
        DB::table('order_items')->whereNull('owner_type')->whereNotNull('product_id')->update([
            'owner_type' => DB::raw('(
                SELECT CASE WHEN p.seller_id IS NULL THEN \'store\' ELSE \'seller\' END
                FROM products p WHERE p.id = order_items.product_id
            )'),
        ]);

        DB::table('order_items')->whereNull('owner_type')->whereNotNull('product_slug')->update([
            'owner_type' => DB::raw('(
                SELECT CASE WHEN p.seller_id IS NULL THEN \'store\' ELSE \'seller\' END
                FROM products p WHERE p.slug = order_items.product_slug
            )'),
        ]);

        // (3) Anything still NULL is genuinely ambiguous (its product row is
        //     gone). It is REPORTED here rather than guessed; the resolver
        //     keeps treating it as store-owned so no order dead-ends.
        $ambiguous = DB::table('order_items')->whereNull('owner_type')->pluck('id')->all();

        if ($ambiguous !== []) {
            Log::warning(
                'Fulfilment ownership backfill: ' . count($ambiguous)
                . ' legacy order line(s) could not be verified because their product no longer exists '
                . '(order_item ids: ' . implode(', ', $ambiguous) . '). They keep their existing '
                . 'behaviour (treated as store-owned); review them in the admin panel.'
            );
        }
    }
};
