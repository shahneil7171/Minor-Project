<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserGroup extends Model
{
    protected $fillable = ['name', 'slug', 'permissions', 'is_default'];

    protected $casts = [
        'permissions' => 'array',
        'is_default' => 'boolean',
    ];

    /** Modules managed from Catalog → System. */
    public const MODULES = ['catalog', 'sales', 'marketing', 'reports', 'system'];

    /** Actions per module. */
    public const ACTIONS = ['view', 'create', 'edit', 'delete'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'user_group_id');
    }

    /**
     * All selectable permission strings ("module.action" plus wildcards).
     */
    public static function permissionList(): array
    {
        $list = ['*'];

        foreach (self::MODULES as $module) {
            foreach (self::ACTIONS as $action) {
                $list[] = "{$module}.{$action}";
            }
        }

        return $list;
    }

    /**
     * Whether this group grants the given permission.
     */
    public function grants(string $permission): bool
    {
        $permissions = $this->permissions ?? [];

        if (in_array('*', $permissions, true)) {
            return true;
        }

        return in_array($permission, $permissions, true);
    }
}
