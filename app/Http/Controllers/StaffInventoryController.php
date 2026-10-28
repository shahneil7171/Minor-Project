<?php

namespace App\Http\Controllers;

use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Staff inventory management.
 *
 * Staff (the dedicated "staff" account type) may VIEW inventory, search/filter
 * products, RESTOCK existing products (positive quantities only) and view the
 * stock movement history. They may NOT create/edit/delete products, change
 * prices, manage categories, users, roles, payment settings, or access
 * admin-only functionality. All authorization is enforced server-side.
 */
class StaffInventoryController extends Controller
{
    private const SCAN_LIMIT = 1000;
    private const PER_PAGE = 20;

    public function __construct(private InventoryService $inventory)
    {
    }

    /**
     * Staff inventory dashboard: KPI cards + filterable, searchable,
     * paginated inventory table.
     */
    public function index(Request $request): View
    {
        $this->authorizeStaff();

        $search = trim((string) $request->get('q', ''));

        $products = Product::query()
            ->with(['category'])
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
                || str_contains(mb_strtolower((string) $row['variant']), $needle)
                || str_contains(mb_strtolower((string) $row['title']), $needle));
        }

        $stats = [
            'total_items'   => $rows->count(),
            'total_units'   => (int) $rows->sum('stock'),
            'low_stock'     => $rows->where('status', InventoryService::STATUS_LOW_STOCK)->count(),
            'out_of_stock'  => $rows->where('status', InventoryService::STATUS_OUT_OF_STOCK)->count(),
        ];

        $page = max(1, (int) $request->get('page', 1));
        $paginated = new \Illuminate\Pagination\LengthAwarePaginator(
            $rows->forPage($page, self::PER_PAGE)->values(),
            $rows->count(),
            self::PER_PAGE,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('staff.inventory.index', [
            'rows'      => $paginated,
            'stats'     => $stats,
            'search'    => $search,
            'statuses'  => [
                InventoryService::STATUS_IN_STOCK,
                InventoryService::STATUS_LOW_STOCK,
                InventoryService::STATUS_OUT_OF_STOCK,
                InventoryService::STATUS_PRE_ORDER,
            ],
            'statusLabels' => InventoryService::STATUS_LABELS,
        ]);
    }

    /**
     * Restock an existing product (positive quantity only).
     */
    public function restock(Request $request, Product $product): RedirectResponse
    {
        $this->authorizeStaff();

        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'note'     => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $this->inventory->adjust(
                $product,
                null,
                (int) $data['quantity'],
                $data['note'] ?? 'Staff restock',
                $request->user(),
                ['reference' => 'Staff restock'],
            );
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->with(
                'error',
                collect($e->errors())->flatten()->first(),
            );
        }

        return back()->with('success', 'Stock restocked and recorded in the inventory history.');
    }

    /**
     * Stock movement history (paginated, read-only).
     */
    public function history(Request $request): View
    {
        $this->authorizeStaff();

        $search = trim((string) $request->get('q', ''));

        $transactions = InventoryTransaction::query()
            ->with(['product', 'actor'])
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

        return view('staff.inventory.history', [
            'transactions' => $transactions,
            'search'       => $search,
        ]);
    }

    private function authorizeStaff(): void
    {
        abort_unless(auth()->check() && auth()->user()->isStaffMember() && auth()->user()->isActive(), 403);
    }
}
