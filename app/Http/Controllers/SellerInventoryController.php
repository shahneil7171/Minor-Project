<?php

namespace App\Http\Controllers;

use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Seller "Inventory" (PHASE 3).
 *
 * The database query itself is scoped to the authenticated seller, so another
 * seller's products can never appear here — this is not a frontend filter.
 * Every mutation goes through App\Services\InventoryService, which writes the
 * new stock level and one inventory_transactions row inside a single
 * transaction.
 */
class SellerInventoryController extends Controller
{
    /**
     * How many of the seller's products are scanned per request. Inventory
     * tables grow large, so the list is always paginated and never loads the
     * whole catalog into memory.
     */
    private const SCAN_LIMIT = 500;

    private const PER_PAGE = 15;

    public function __construct(private InventoryService $inventory)
    {
    }

    /**
     * Seller inventory list: Product / SKU / Variant / Stock / Reserved /
     * Available / Status / Last updated, with All / In Stock / Low Stock /
     * Out of Stock filters and a product/SKU search.
     */
    public function index(Request $request): View
    {
        $status = (string) $request->get('status', 'all');
        $search = trim((string) $request->get('q', ''));

        $products = Product::query()
            ->with(['category', 'seller'])
            ->where('seller_id', $request->user()->id)
            ->when($search !== '', function ($query) use ($search) {
                $like = '%' . $search . '%';

                $query->where(function ($inner) use ($like) {
                    $inner->where('title', 'like', $like)
                        ->orWhere('sku', 'like', $like);
                });
            })
            ->orderByDesc('id')
            ->limit(self::SCAN_LIMIT)
            ->get();

        // One row per inventory unit: a plain product, or one row per variant.
        $rows = $products->flatMap(fn (Product $product) => $this->inventory->rowsFor($product));

        // Variant SKUs / option text live inside the product's JSON, so they
        // are matched after the (already eager-loaded) rows are built.
        if ($search !== '') {
            $needle = mb_strtolower($search);

            $rows = $rows->filter(fn (array $row) => str_contains(mb_strtolower((string) $row['sku']), $needle)
                || str_contains(mb_strtolower((string) $row['variant']), $needle));
        }

        $counts = [
            'all'          => $rows->count(),
            'in_stock'     => $rows->where('status', InventoryService::STATUS_IN_STOCK)->count(),
            'low_stock'    => $rows->where('status', InventoryService::STATUS_LOW_STOCK)->count(),
            'out_of_stock' => $rows->where('status', InventoryService::STATUS_OUT_OF_STOCK)->count(),
        ];

        if ($status !== 'all' && isset($counts[$status])) {
            $rows = $rows->where('status', $status);
        } else {
            $status = 'all';
        }

        $page = max(1, (int) $request->get('page', 1));

        $inventory = new LengthAwarePaginator(
            $rows->forPage($page, self::PER_PAGE)->values(),
            $rows->count(),
            self::PER_PAGE,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return view('seller.inventory', [
            'inventory'        => $inventory,
            'rows'             => $inventory->items(),
            'status'           => $status,
            'search'           => $search,
            'counts'           => $counts,
            'statusLabels'     => InventoryService::STATUS_LABELS,
            'defaultThreshold' => $this->inventory->thresholdFor(),
        ]);
    }

    /**
     * Adjust the stock of a product (or one of its variants) the seller owns.
     *
     * Server-side ownership check: Seller A can never change Seller B's stock,
     * even with a forged request. The quantity must be a whole number and the
     * reason is mandatory — every change is auditable.
     */
    public function adjust(Request $request, Product $product): RedirectResponse
    {
        $this->authorizeOwnership($request, $product);

        $data = $request->validate([
            'quantity'  => ['required', 'integer', 'min:-1000000', 'max:1000000', 'not_in:0'],
            'reason'    => ['required', 'string', 'max:255'],
            'variant_id' => ['nullable', 'string', 'max:64'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        // Nullable inputs are simply absent from the validated array.
        $variantId = $data['variant_id'] ?? null;

        if ($variantId !== null && $this->inventory->findVariant($product, $variantId) === null) {
            return back()->with('error', 'That option combination does not belong to this product.');
        }

        try {
            $this->inventory->adjust(
                $product,
                $variantId,
                (int) $data['quantity'],
                $data['reason'],
                $request->user(),
                ['reference' => 'Seller adjustment'],
            );

            if ($request->has('low_stock_threshold')) {
                $this->inventory->setLowStockThreshold($product, (int) $data['low_stock_threshold']);
            }
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->with(
                'error',
                collect($e->errors())->flatten()->first(),
            );
        }

        return back()->with('success', 'Stock updated and recorded in the inventory history.');
    }

    /**
     * The seller's own inventory history (newest first).
     */
    public function transactions(Request $request): View
    {
        $type = (string) $request->get('type', 'all');

        $transactions = InventoryTransaction::query()
            ->with(['product', 'actor'])
            ->forSeller((int) $request->user()->id)
            ->type($type === 'all' ? null : $type)
            ->latest('id')
            ->paginate(20);

        $transactions->appends($request->query());

        return view('seller.inventory-history', [
            'transactions' => $transactions,
            'type'         => $type,
            'types'        => InventoryTransaction::TYPES,
            'typeLabels'   => InventoryTransaction::TYPE_LABELS,
        ]);
    }

    /**
     * A seller may only ever touch a product they own.
     */
    private function authorizeOwnership(Request $request, Product $product): void
    {
        abort_unless(
            auth()->check() && auth()->user()->isSeller(),
            403,
            'Only sellers can adjust inventory.'
        );

        abort_unless(
            (int) $product->seller_id === (int) $request->user()->id,
            403,
            'You can only manage products you own.'
        );
    }
}
