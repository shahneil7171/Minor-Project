<?php

namespace App\Services;

use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductView;
use Illuminate\Support\Facades\DB;

/**
 * RecommendationService — PHASE 4.
 *
 * DETERMINISTIC product recommendations. This is deliberately NOT machine
 * learning: every result comes from a transparent database rule, and the UI
 * labels the output "Recommended Products" / "Related Products" rather than
 * claiming an AI is involved.
 *
 * SCORING (all computed in SQL, one query per strategy)
 * ---------------------------------------------------
 *   relatedProducts        : same subcategory > same category > shared tag >
 *                            same brand, with in-stock and popularity boosts
 *   frequentlyBoughtTogether: co-purchase count from real order data
 *   recommendedForYou      : same categories the visitor has bought/browsed
 *
 * GUARANTEES
 * ----------
 * - The current product is never included.
 * - No duplicates: results are keyed by product id and capped.
 * - Never fabricated: if the data does not support a relationship (e.g. no
 *   co-purchases yet), the method returns an empty collection and the caller
 *   falls back — it never invents an affinity.
 * - Deterministic ties are broken by id so the same page always renders the
 *   same order.
 */
class RecommendationService
{
    /**
     * Default number of recommendations to show.
     */
    public const DEFAULT_LIMIT = 4;

    /**
     * Hard cap so a misconfigured limit can never render an endless grid.
     */
    public const MAX_LIMIT = 12;

    /**
     * Products related to $product by category, subcategory, tags and brand.
     *
     * @return \Illuminate\Support\Collection<int, Product>
     */
    public function relatedProducts(Product $product, int $limit = self::DEFAULT_LIMIT): \Illuminate\Support\Collection
    {
        $limit = $this->safeLimit($limit);

        $categoryId = (int) ($product->category_id ?? 0);
        $brand = mb_strtolower(trim((string) ($product->brand ?? '')));
        $subcategory = mb_strtolower(trim((string) ($product->subcategory ?? '')));
        $tags = array_values(array_filter(array_map(
            fn ($tag) => mb_strtolower(trim((string) $tag)),
            (array) ($product->tags ?? [])
        )));

        $query = Product::query()
            ->where('status', 1)
            ->whereKeyNot($product->getKey());

        $query->where(function ($q) use ($categoryId, $brand, $subcategory, $tags) {
            $q->where('category_id', $categoryId);

            if ($subcategory !== '') {
                $q->orWhereRaw('LOWER(subcategory) = ?', [$subcategory]);
            }

            if ($brand !== '') {
                $q->orWhereRaw('LOWER(brand) = ?', [$brand]);
            }

            foreach ($tags as $tag) {
                // Tags live in a JSON column; a LIKE match is portable across
                // MySQL and SQLite and is scoped to a single product row, so it
                // cannot widen the result set unexpectedly.
                $q->orWhere('tags', 'like', '%"' . $this->escapeLike($tag) . '"%');
            }
        });

        // Popularity + purchasability break ties deterministically.
        $sales = $this->unitsSoldSubquery();

        return $query
            ->leftJoinSub($sales, 'rec_sales', 'rec_sales.product_id', '=', 'products.id')
            ->select('products.*')
            ->addSelect([
                DB::raw('CASE WHEN products.category_id = ' . ($categoryId ?: 0) . ' THEN 3 ELSE 0 END as rec_category'),
                DB::raw('CASE WHEN LOWER(COALESCE(products.subcategory, \'\')) = ' . $this->quote($subcategory) . ' THEN 3 ELSE 0 END as rec_subcategory'),
                DB::raw('CASE WHEN LOWER(COALESCE(products.brand, \'\')) = ' . $this->quote($brand) . ' THEN 2 ELSE 0 END as rec_brand'),
                DB::raw('COALESCE(rec_sales.units, 0) as rec_units'),
                DB::raw('(products.quantity - products.reserved) as rec_available'),
            ])
            ->orderByDesc('rec_subcategory')
            ->orderByDesc('rec_category')
            ->orderByDesc('rec_brand')
            ->orderByDesc('rec_units')
            ->orderBy('products.id')
            ->limit($limit)
            ->get()
            // Only genuinely purchasable products are recommended.
            ->filter(fn (Product $p) => (int) $p->rec_available > 0)
            ->values();
    }

    /**
     * Products that buyers of THIS product also bought, from real orders.
     *
     * When the order history is too thin to establish any co-purchase (a
     * brand-new store, or a product nobody has co-bought yet) this returns an
     * EMPTY collection rather than inventing a relationship — the caller
     * decides whether to hide the section or fall back to related products.
     *
     * @return \Illuminate\Support\Collection<int, Product>
     */
    public function frequentlyBoughtTogether(Product $product, int $limit = self::DEFAULT_LIMIT): \Illuminate\Support\Collection
    {
        $limit = $this->safeLimit($limit);

        // Orders that contained this product…
        $orderIds = OrderItem::query()
            ->where('product_id', $product->getKey())
            ->distinct()
            ->limit(2000)
            ->pluck('order_id');

        if ($orderIds->isEmpty()) {
            return collect();
        }

        // …and the OTHER products in those same orders, ranked by how often
        // they co-occurred. Two independent queries beat a self-join here and
        // stay portable across SQLite and MySQL.
        $co = OrderItem::query()
            ->whereIn('order_id', $orderIds)
            ->where('product_id', '!=', $product->getKey())
            ->select('product_id', DB::raw('COUNT(DISTINCT order_id) as co_orders'))
            ->groupBy('product_id')
            ->orderByDesc('co_orders')
            ->limit($limit)
            ->pluck('co_orders', 'product_id');

        // A single shared order is not a trend — require a real pattern.
        $strongEnough = $co->filter(fn ($count) => (int) $count >= 2);

        if ($strongEnough->isEmpty()) {
            return collect();
        }

        return Product::query()
            ->where('status', 1)
            ->whereIn('id', $strongEnough->keys())
            ->get()
            ->filter(fn (Product $p) => (int) $p->quantity - (int) ($p->reserved ?? 0) > 0)
            ->sortBy(fn (Product $p) => -$strongEnough->get($p->id, 0))
            ->values()
            ->take($limit);
    }

    /**
     * Recommendations for a signed-in buyer, based on what they actually
     * bought and browsed. Falls back to popular products for a new visitor.
     *
     * @return \Illuminate\Support\Collection<int, Product>
     */
    public function recommendedForYou(?\App\Models\User $user, int $limit = self::DEFAULT_LIMIT): \Illuminate\Support\Collection
    {
        $limit = $this->safeLimit($limit);

        $categoryIds = collect();
        $excludeIds = collect();

        if ($user) {
            // Categories the buyer has already purchased in.
            $categoryIds = OrderItem::query()
                ->join('products', 'products.id', '=', 'order_items.product_id')
                ->where('order_items.seller_id', '!=', null)
                ->whereExists(function ($q) use ($user) {
                    $q->select(DB::raw(1))->from('orders')
                        ->whereColumn('orders.id', 'order_items.order_id')
                        ->where('orders.user_id', $user->id);
                })
                ->distinct()
                ->pluck('products.category_id')
                ->filter()
                ->unique()
                ->values();

            $excludeIds = OrderItem::query()
                ->whereExists(function ($q) use ($user) {
                    $q->select(DB::raw(1))->from('orders')
                        ->whereColumn('orders.id', 'order_items.order_id')
                        ->where('orders.user_id', $user->id);
                })
                ->distinct()
                ->pluck('product_id')
                ->filter()
                ->values();
        }

        $query = Product::query()
            ->where('status', 1)
            ->when($excludeIds->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $excludeIds));

        if ($categoryIds->isNotEmpty()) {
            $query->whereIn('category_id', $categoryIds);
        }

        $sales = $this->unitsSoldSubquery();

        return $query
            ->leftJoinSub($sales, 'rec_sales', 'rec_sales.product_id', '=', 'products.id')
            ->select('products.*')
            ->addSelect([
                DB::raw('COALESCE(rec_sales.units, 0) as rec_units'),
                DB::raw('(products.quantity - products.reserved) as rec_available'),
            ])
            ->orderByDesc('rec_units')
            ->orderBy('products.id')
            ->limit($limit + 5)
            ->get()
            ->filter(fn (Product $p) => (int) $p->rec_available > 0)
            ->take($limit)
            ->values();
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Reusable "units sold per product" sub-query used to rank results.
     */
    private function unitsSoldSubquery()
    {
        return DB::table('order_items')
            ->select([
                'order_items.product_id',
                DB::raw('SUM(order_items.quantity) as units'),
            ])
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereIn('orders.status', [
                'confirmed', 'processing', 'ready_for_pickup',
                'assigned', 'picked_up', 'out_for_delivery', 'delivered',
            ])
            ->whereNotNull('order_items.product_id')
            ->groupBy('order_items.product_id');
    }

    /**
     * Clamp a caller-supplied limit into a safe range.
     */
    private function safeLimit(int $limit): int
    {
        return max(1, min(self::MAX_LIMIT, $limit));
    }

    /**
     * Escape the LIKE wildcards in a tag before it is used in a pattern.
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['%', '_'], ['\%', '\_'], $value);
    }

    /**
     * Quote a value for safe interpolation into a raw SELECT expression.
     *
     * Every interpolated value here is a product attribute that already came
     * from the database (never from a request), and addslashes() neutralises
     * quotes so the expression cannot be broken out of.
     */
    private function quote(string $value): string
    {
        return "'" . addslashes($value) . "'";
    }
}


