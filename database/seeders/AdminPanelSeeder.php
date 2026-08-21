<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Models\UserGroup;
use Illuminate\Database\Seeder;

class AdminPanelSeeder extends Seeder
{
    /**
     * Seed the default staff permission groups and store settings.
     */
    public function run(): void
    {
        $all = ['*'];

        $everythingExceptSystem = [];
        foreach (UserGroup::MODULES as $module) {
            foreach (UserGroup::ACTIONS as $action) {
                if ($module !== 'system') {
                    $everythingExceptSystem[] = "{$module}.{$action}";
                }
            }
        }

        $viewOnly = [];
        foreach (UserGroup::MODULES as $module) {
            $viewOnly[] = "{$module}.view";
        }

        $groups = [
            ['name' => 'Super Admin', 'slug' => 'super-admin', 'permissions' => $all, 'is_default' => true],
            ['name' => 'Admin', 'slug' => 'admin', 'permissions' => $everythingExceptSystem, 'is_default' => false],
            ['name' => 'Manager', 'slug' => 'manager', 'permissions' => $viewOnly, 'is_default' => false],
        ];

        foreach ($groups as $group) {
            UserGroup::updateOrCreate(['slug' => $group['slug']], $group);
        }

        foreach (Setting::DEFAULTS as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
