<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Admin "Inventory" (PHASE 3).
 *
 * Gives the admin the full picture: KPI cards, the same inventory table as
 * the seller page (with seller / category / status / product / SKU filters),
 * the complete transaction history and a manual adjustment action that is
 * always recorded as a manual_adjustment transaction with a reason and actor.
 */
class AdminInventoryController extends Controller
{
    private const SCAN_LIMIT = 1000;

    private const PER_PAGE = 20;

    public function __construct(private InventoryService $inventory)
    {
    }

    /**
     * Admin inventory dashboard: cards + filterable inventory table.
     */
    public function index(Request $request): View
    {
        $this->authorizeAdmin();

        $status = (string) $request->get('status', 'all');
        $search = trim((string) $request->get('q', ''));
        $sellerId = (int) $request->get('seller', 0);
        $categoryId = (int) $request->get('category', 0);

        $products = Product::query()
            ->with(['category', 'seller'])
            ->when($sellerId > 0, fn ($query) => $query->where('seller_id', $sellerId))
            ->when($categoryId > 0, fn ($query) => $query->where('category_id', $categoryId))
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

        $rows = $products->flatMap(fn (Product $product) => $this->inventory->rowsFor($product));

        if ($search !== '') {
            $needle = mb_strtolower($search);

            $rows = $rows->filter(fn (array $row) => str_contains(mb_strtolower((string) $row['sku']), $needle)
                || str_contains(mb_strtolower((string) $row['variant']), $needle));
        }

        // KPI cards describe the WHOLE store, not the filtered view, so the
        // dashboard never lies. The second scan only runs when the view is
        // actually filtered — an unfiltered page reuses the rows it already
        // built (one set of eager-loaded queries, no duplicate work).
        $hasFilters = $search !== '' || $sellerId > 0 || $categoryId > 0;

        $allRows = $hasFilters
            ? Product::query()
                ->with(['category', 'seller'])
                ->orderByDesc('id')
                ->limit(self::SCAN_LIMIT)
                ->get()
                ->flatMap(fn (Product $product) => $this->inventory->rowsFor($product))
            : $rows;

        $stats = [
            'total_items'   => $allRows->count(),
            'total_units'   => (int) $allRows->sum('stock'),
            'low_stock'     => $allRows->where('status', InventoryService::STATUS_LOW_STOCK)->count(),
            'out_of_stock'  => $allRows->where('status', InventoryService::STATUS_OUT_OF_STOCK)->count(),
            'transactions'  => InventoryTransaction::count(),
        ];

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

        return view('admin.inventory.index', [
            'stats'         => $stats,
            'inventory'     => $inventory,
            'rows'          => $inventory->items(),
            'status'        => $status,
            'search'        => $search,
            'sellerId'      => $sellerId,
            'categoryId'    => $categoryId,
            'counts'        => $counts,
            'statusLabels'  => InventoryService::STATUS_LABELS,
            'sellers'       => User::query()->where('account_type', 'seller')->orderBy('name')->get(['id', 'name']),
            'categories'    => Category::query()->ordered()->get(['id', 'name']),
            'recent'        => InventoryTransaction::query()->with(['product', 'actor', 'seller'])->latest('id')->limit(8)->get(),
            'defaultThreshold' => $this->inventory->thresholdFor(),
        ]);
    }

    /**
     * Manual stock correction (admin only).
     *
     * Requires a quantity AND a reason; the variant, if given, must belong to
     * the product. Every change writes exactly one manual_adjustment
     * inventory transaction with the actor and the before/after level.
     */
    public function adjust(Request $request, Product $product): RedirectResponse
    {
        $this->authorizeAdmin();

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
                ['reference' => 'Admin adjustment'],
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

        return back()->with('success', 'Stock adjusted and recorded in the inventory history.');
    }

    /**
     * The complete inventory transaction history (paginated + filterable).
     */
    public function transactions(Request $request): View
    {
        $this->authorizeAdmin();

        $type = (string) $request->get('type', 'all');
        $sellerId = (int) $request->get('seller', 0);
        $search = trim((string) $request->get('q', ''));

        $transactions = InventoryTransaction::query()
            ->with(['product', 'actor', 'seller', 'order'])
            ->type($type === 'all' ? null : $type)
            ->when($sellerId > 0, fn ($query) => $query->where('seller_id', $sellerId))
            ->when($search !== '', function ($query) use ($search) {
                $like = '%' . $search . '%';

                $query->where(function ($inner) use ($like) {
                    $inner->where('reference', 'like', $like)
                        ->orWhere('reason', 'like', $like);
                });
            })
            ->latest('id')
            ->paginate(25);

        $transactions->appends($request->query());

        return view('admin.inventory.transactions', [
            'transactions' => $transactions,
            'type'         => $type,
            'sellerId'     => $sellerId,
            'search'       => $search,
            'types'        => InventoryTransaction::TYPES,
            'typeLabels'   => InventoryTransaction::TYPE_LABELS,
            'sellers'      => User::query()->where('account_type', 'seller')->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Only staff (admin / manager) may manage inventory.
     */
    private function authorizeAdmin(): void
    {
        abort_unless(auth()->check() && auth()->user()->isStaff(), 403);
    }
}
