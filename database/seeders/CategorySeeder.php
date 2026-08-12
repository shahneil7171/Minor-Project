<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the category tree (parents + subcategories).
     *
     * Matches the OpenCart-style navigation structure:
     *  Electronics -> Mobiles, Laptops, Accessories
     *  Fashion     -> Men, Women
     *  Home & Kitchen
     */
    public function run(): void
    {
        $tree = [
            ['name' => 'Electronics', 'slug' => 'electronics', 'sort_order' => 1, 'children' => [
                ['name' => 'Mobiles', 'slug' => 'mobiles', 'sort_order' => 1],
                ['name' => 'Laptops', 'slug' => 'laptops', 'sort_order' => 2],
                ['name' => 'Accessories', 'slug' => 'accessories', 'sort_order' => 3],
            ]],
            ['name' => 'Fashion', 'slug' => 'fashion', 'sort_order' => 2, 'children' => [
                ['name' => 'Men', 'slug' => 'men', 'sort_order' => 1],
                ['name' => 'Women', 'slug' => 'women', 'sort_order' => 2],
            ]],
            ['name' => 'Home & Kitchen', 'slug' => 'home-kitchen', 'sort_order' => 3, 'children' => []],
        ];

        foreach ($tree as $parent) {
            $parentCategory = Category::firstOrCreate(
                ['slug' => $parent['slug']],
                [
                    'name' => $parent['name'],
                    'sort_order' => $parent['sort_order'],
                    'is_active' => true,
                ]
            );

            foreach ($parent['children'] as $child) {
                Category::firstOrCreate(
                    ['slug' => $child['slug']],
                    [
                        'parent_id' => $parentCategory->id,
                        'name' => $child['name'],
                        'sort_order' => $child['sort_order'],
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}