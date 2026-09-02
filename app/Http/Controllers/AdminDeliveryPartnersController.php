<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Admin > Delivery Partners: dedicated account management for the
 * delivery_partner role (create / edit / enable-disable / details /
 * assigned deliveries + history).
 *
 * Account type is always forced server-side to "delivery_partner" — it is
 * never accepted from user input.
 */
class AdminDeliveryPartnersController extends Controller
{
    /**
     * All delivery partner accounts with stats, search and status filter.
     */
    public function index(Request $request)
    {
        $this->authorizeAdmin();

        $status = $request->get('status', 'all');
        $search = trim((string) $request->get('search', ''));

        $query = User::query()
            ->where('account_type', 'delivery_partner')
            ->withCount('deliveryAssignments');

        if ($status !== 'all' && in_array($status, User::STATUSES, true)) {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->search($search);
        }

        $partners = $query->orderBy('name')->paginate(15);
        $partners->appends($request->query());

        $base = User::query()->where('account_type', 'delivery_partner');

        $stats = [
            'total'           => (clone $base)->count(),
            'active'          => (clone $base)->where('status', 'active')->count(),
            'with_deliveries' => (clone $base)->has('deliveryAssignments')->count(),
        ];

        return view('admin.delivery-partners.index', compact('partners', 'stats', 'status', 'search'));
    }

    /**
     * Create form.
     */
    public function create()
    {
        $this->authorizeAdmin();

        return view('admin.delivery-partners.form', ['partner' => new User()]);
    }

    /**
     * Store a new delivery partner (account_type forced server-side).
     */
    public function store(Request $request)
    {
        $this->authorizeAdmin();

        $data = $this->validated($request);

        $partner = User::create([
            ...$data,
            'account_type' => 'delivery_partner',
            'password' => bcrypt($data['password']),
            'status' => 'active',
        ]);

        return redirect()->route('admin.delivery-partners.show', $partner)
            ->with('success', 'Delivery partner ' . $partner->name . ' created successfully.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $rules = [
            'name'  => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($ignoreId)],
            'phone' => ['required', 'string', 'max:20'],
        ];

        if ($ignoreId === null) {
            $rules['password'] = ['required', 'string', 'min:8'];
        } else {
            $rules['password'] = ['nullable', 'string', 'min:8'];
        }

        return $request->validate($rules);
    }

    /**
     * Partner details + assigned deliveries + delivery history.
     */
    public function show(Request $request, User $partner)
    {
        $this->authorizeAdmin();
        $this->abortUnlessDeliveryPartner($partner);

        $assignments = $partner->deliveryAssignments()
            ->with(['order.user', 'order.items'])
            ->latest('assigned_at')
            ->paginate(10);
        $assignments->appends($request->query());

        $stats = [
            'total'     => $partner->deliveryAssignments()->count(),
            'active'    => $partner->deliveryAssignments()->whereIn('status', ['assigned', 'ready_for_pickup', 'picked_up', 'out_for_delivery'])->count(),
            'delivered' => $partner->deliveryAssignments()->where('status', 'delivered')->count(),
            'failed'    => $partner->deliveryAssignments()->where('status', 'failed')->count(),
        ];

        return view('admin.delivery-partners.show', compact('partner', 'assignments', 'stats'));
    }

    /**
     * Edit form.
     */
    public function edit(User $partner)
    {
        $this->authorizeAdmin();
        $this->abortUnlessDeliveryPartner($partner);

        return view('admin.delivery-partners.form', ['partner' => $partner]);
    }

    /**
     * Update partner information / credentials.
     */
    public function update(Request $request, User $partner)
    {
        $this->authorizeAdmin();
        $this->abortUnlessDeliveryPartner($partner);

        $data = $this->validated($request, $partner->id);

        if ($request->filled('password')) {
            $data['password'] = bcrypt($request->input('password'));
        } else {
            unset($data['password']);
        }

        // The role can never be changed here — only the dedicated fields.
        unset($data['account_type']);

        $partner->update($data);

        return redirect()->route('admin.delivery-partners.show', $partner)
            ->with('success', 'Delivery partner updated successfully.');
    }

    /**
     * Enable/disable a delivery partner.
     *
     * Disabled partners cannot sign in (existing login status check) and are
     * excluded from assignment dropdowns.
     */
    public function status(Request $request, User $partner)
    {
        $this->authorizeAdmin();
        $this->abortUnlessDeliveryPartner($partner);

        if ($partner->id === $request->user()->id) {
            return back()->with('error', 'You cannot change the status of your own account.');
        }

        $data = $request->validate([
            'status' => ['required', Rule::in(User::STATUSES)],
        ]);

        $partner->update(['status' => $data['status']]);

        if (! $partner->isActive()) {
            DB::table('sessions')->where('user_id', $partner->id)->delete();
        }

        return back()->with('success', 'Delivery partner ' . $partner->name . ' marked as ' . $partner->statusLabel() . '.');
    }

    private function abortUnlessDeliveryPartner(User $partner): void
    {
        abort_unless($partner->isDeliveryPartner(), 404);
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->check() && auth()->user()->isAdmin(), 403);
    }
}
