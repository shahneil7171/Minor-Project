<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminSystemUsersController extends Controller
{
    /**
     * Staff accounts (admins & managers) managed from System > Users.
     */
    public function index()
    {
        $this->authorizeAdmin();

        $users = User::query()
            ->whereIn('account_type', ['admin', 'manager', 'staff'])
            ->with('group')
            ->orderBy('name')
            ->paginate(15);

        return view('admin.system.users.index', compact('users'));
    }

    public function create()
    {
        $this->authorizeAdmin();
        $this->requirePermission('system.create');

        return view('admin.system.users.form', [
            'user'   => new User(),
            'groups' => UserGroup::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorizeAdmin();
        $this->requirePermission('system.create');

        $data = $this->validated($request);
        $this->authorizeRoleAssignment($data['account_type']);

        $user = User::create([
            ...$data,
            'password' => Hash::make($request->input('password')),
            'status' => 'active',
        ]);

        return redirect()->route('admin.system.users.index')
            ->with('success', 'Staff account ' . $user->name . ' created successfully.');
    }

    public function edit(User $user)
    {
        $this->authorizeAdmin();
        $this->requirePermission('system.edit');
        $this->abortIfShopper($user);
        $this->abortIfAdminAccount($user);

        return view('admin.system.users.form', [
            'user'   => $user,
            'groups' => UserGroup::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        $this->authorizeAdmin();
        $this->requirePermission('system.edit');
        $this->abortIfShopper($user);
        $this->abortIfAdminAccount($user);

        $data = $this->validated($request, $user->id);
        $this->authorizeRoleAssignment($data['account_type']);

        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->input('password'));
        }

        // The acting super admin may not demote or lock themselves out.
        if ($user->id === auth()->id()) {
            unset($data['account_type'], $data['status']);
        }

        $user->update($data);

        // Deactivated or blocked staff must not keep live sessions.
        if (! $user->isActive()) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }

        return redirect()->route('admin.system.users.index')
            ->with('success', 'Staff account updated successfully.');
    }

    public function destroy(User $user)
    {
        $this->authorizeAdmin();
        $this->requirePermission('system.delete');

        if ($user->id === auth()->id()) {
            return redirect()->route('admin.system.users.index')
                ->with('error', 'You cannot delete your own account.');
        }

        $this->abortIfAdminAccount($user);

        if (! in_array($user->account_type, ['admin', 'manager', 'staff'], true)) {
            abort(404);
        }

        $user->delete();

        return redirect()->route('admin.system.users.index')
            ->with('success', 'Staff account deleted successfully.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $rules = [
            'name'          => ['required', 'string', 'max:255'],
            'email'         => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($ignoreId)],
            'account_type'  => ['required', 'in:admin,manager,staff'],
            'user_group_id' => ['nullable', 'integer', 'exists:user_groups,id'],
        ];

        if ($ignoreId === null) {
            $rules['password'] = ['required', 'string', 'min:8'];
        } else {
            $rules['password'] = ['nullable', 'string', 'min:8'];
            // Enable/disable (and block) staff accounts from the edit form.
            $rules['status'] = ['required', Rule::in(User::STATUSES)];
        }

        $data = $request->validate($rules);

        if (array_key_exists('user_group_id', $data) && $data['user_group_id'] !== null && (int) $data['user_group_id'] === 0) {
            $data['user_group_id'] = null;
        }

        return $data;
    }

    private function abortIfShopper(User $user): void
    {
        if (! in_array($user->account_type, ['admin', 'manager', 'staff'], true)) {
            abort(404);
        }
    }

    /**
     * Administrator accounts can only ever be managed by administrators —
     * staff managers can manage manager/staff accounts but never touch an
     * admin account (create, edit, demote or delete).
     */
    private function abortIfAdminAccount(User $user): void
    {
        if ($user->account_type === 'admin' && ! auth()->user()->isAdmin()) {
            abort(403, 'Administrator accounts can only be managed by administrators.');
        }
    }

    /**
     * ONLY a true administrator may assign the "admin" role. Managers with
     * system permissions can create/edit manager/staff accounts, but can
     * never mint or promote anything into an administrator.
     */
    private function authorizeRoleAssignment(string $accountType): void
    {
        if ($accountType === 'admin' && ! auth()->user()->isAdmin()) {
            abort(403, 'Only administrators can assign the admin role.');
        }
    }

    private function requirePermission(string $permission): void
    {
        abort_unless(auth()->user()->hasPermission($permission), 403, 'You do not have permission for this action.');
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->check() && auth()->user()->isStaff(), 403);
    }
}
