<?php

namespace App\Http\Controllers;

use App\Models\UserGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminUserGroupsController extends Controller
{
    /**
     * Staff permission groups (System > User Groups).
     */
    public function index()
    {
        $this->authorizeAdmin();

        $groups = UserGroup::withCount('users')->orderBy('name')->get();

        return view('admin.system.groups.index', compact('groups'));
    }

    public function create()
    {
        $this->authorizeAdmin();
        $this->requirePermission('system.create');

        return view('admin.system.groups.form', ['group' => new UserGroup()]);
    }

    public function store(Request $request)
    {
        $this->authorizeAdmin();
        $this->requirePermission('system.create');

        $data = $this->validated($request);

        UserGroup::create([
            'name' => $data['name'],
            'slug' => $this->uniqueSlug(Str::slug($data['name'])),
            'permissions' => $request->input('permissions', []),
            'is_default' => false,
        ]);

        return redirect()->route('admin.system.groups.index')->with('success', 'User group created successfully.');
    }

    public function edit(UserGroup $group)
    {
        $this->authorizeAdmin();
        $this->requirePermission('system.edit');

        return view('admin.system.groups.form', compact('group'));
    }

    public function update(Request $request, UserGroup $group)
    {
        $this->authorizeAdmin();
        $this->requirePermission('system.edit');

        $data = $this->validated($request, $group->id);

        $group->update([
            'name' => $data['name'],
            'permissions' => $request->input('permissions', []),
        ]);

        return redirect()->route('admin.system.groups.index')->with('success', 'User group updated successfully.');
    }

    public function destroy(UserGroup $group)
    {
        $this->authorizeAdmin();
        $this->requirePermission('system.delete');

        if ($group->users()->exists()) {
            $group->users()->update(['user_group_id' => null]);
        }

        $group->delete();

        return redirect()->route('admin.system.groups.index')->with('success', 'User group deleted successfully.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $allowed = UserGroup::permissionList();

        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('user_groups', 'name')->ignore($ignoreId)],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => [Rule::in($allowed)],
        ]);
    }

    private function uniqueSlug(string $base): string
    {
        $slug = $base !== '' ? $base : 'group';
        $counter = 1;

        while (UserGroup::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $counter++;
        }

        return $slug;
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
