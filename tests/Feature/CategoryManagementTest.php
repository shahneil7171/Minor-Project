<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * End-to-end category management coverage (spec §21 / §22).
 *
 * Verifies the exact manual scenario:
 * create "Gaming Mechanical Keyboard" under Accessories → appears on the
 * products page with a Category badge, in the Accessories home section and
 * on /categories/accessories → move it to Electronics → it automatically
 * leaves Accessories and appears under Electronics everywhere → category
 * pages, filters, search, navigation and admin category counts all follow
 * the database relationship (products.category_id).
 */
class CategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['account_type' => 'admin', 'status' => 'active']);
    }

    private function category(string $name, string $slug): Category
    {
        return Category::updateOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'is_active' => true]
        );
    }

    private function keyboardPayload(Category $category): array
    {
        return [
            'title'        => 'Gaming Mechanical Keyboard',
            'subtitle'     => 'RGB mechanical switch keyboard',
            'description'  => 'A premium mechanical keyboard with RGB lighting.',
            'price'        => 2999,
            'quantity'     => 20,
            'stock_status' => 'in-stock',
            'category'     => (string) $category->id,
            'brand'        => 'Example Brand',
            'status'       => 1,
        ];
    }

    public function test_full_category_lifecycle_scenario(): void
    {
        $admin = $this->admin();
        $accessories = $this->category('Accessories', 'accessories');
        $electronics = $this->category('Electronics', 'electronics');
        $mobiles = $this->category('Mobiles', 'mobiles');

        // --- STEP 2-3: create the product under Accessories ---
        $this->actingAs($admin)
            ->post('/products', $this->keyboardPayload($accessories))
            ->assertRedirect('/products');

        $product = Product::where('slug', 'gaming-mechanical-keyboard')->first();
        $this->assertNotNull($product);
        $this->assertSame($accessories->id, $product->category_id);
        $this->assertSame('Accessories', $product->fresh()->category->name);

        // --- STEP 4: products page shows the product + category badge ---
        $productsPage = $this->actingAs($admin)->get('/products');
        $productsPage->assertOk();
        $productsPage->assertSee('Gaming Mechanical Keyboard');
        $productsPage->assertSee('Category: Accessories', false);

        // --- STEP 5: home page Accessories section contains it automatically ---
        $home = $this->get('/');
        $home->assertOk();
        $home->assertSee('Gaming Mechanical Keyboard');

        // --- STEP 6: category page works via the DB relationship ---
        $this->get(route('categories.show', 'accessories'))
            ->assertOk()
            ->assertSee('Gaming Mechanical Keyboard');

        // --- STEP 6-8: change category to Electronics, save ---
        $this->actingAs($admin)
            ->post('/products/gaming-mechanical-keyboard/update', $this->keyboardPayload($electronics))
            ->assertRedirect('/products');

        $product->refresh();
        $this->assertSame($electronics->id, $product->category_id);
        $this->assertSame('Electronics', $product->fresh()->category->name);

        // 8. Reload home: gone from Accessories, present under Electronics.
        $homeAfter = $this->get('/');
        $homeAfter->assertOk();
        $homeAfter->assertSee('Gaming Mechanical Keyboard');

        // Accessories section/products no longer contain it.
        $accessoriesPage = $this->get(route('categories.show', 'accessories'));
        $accessoriesPage->assertOk();
        $accessoriesPage->assertDontSee('Gaming Mechanical Keyboard');

        // --- STEP 9: Electronics category page shows it ---
        $this->get(route('categories.show', 'electronics'))
            ->assertOk()
            ->assertSee('Gaming Mechanical Keyboard');

        // --- STEP 10: products filter by Electronics category ---
        $this->actingAs($admin)->get('/products?category=electronics')
            ->assertOk()
            ->assertSee('Gaming Mechanical Keyboard');

        $this->actingAs($admin)->get('/products?category=accessories')
            ->assertOk()
            ->assertDontSee('Gaming Mechanical Keyboard');

        // --- STEP 11: search still shows its Electronics category ---
        $search = $this->actingAs($admin)->get('/products?search=' . urlencode('Gaming Mechanical Keyboard'));
        $search->assertOk();
        $search->assertSee('Gaming Mechanical Keyboard');
        $search->assertSee('Category: Electronics', false);

        // --- STEP 12: change again to Mobiles and verify it moves ---
        $this->actingAs($admin)
            ->post('/products/gaming-mechanical-keyboard/update', $this->keyboardPayload($mobiles))
            ->assertRedirect('/products');

        $product->refresh();
        $this->assertSame($mobiles->id, $product->category_id);

        $this->get(route('categories.show', 'mobiles'))->assertSee('Gaming Mechanical Keyboard');
        $this->get(route('categories.show', 'electronics'))->assertDontSee('Gaming Mechanical Keyboard');
    }

    public function test_category_navigation_and_404(): void
    {
        $this->category('Shoes', 'shoes');

        // Valid category → works.
        $this->get(route('categories.show', 'shoes'))->assertOk()->assertSee('Shoes');

        // Unknown category → proper 404 (no text-search fallback page).
        $this->get(route('categories.show', 'nonexistent-category'))->assertNotFound();
    }

    public function test_admin_categories_index_shows_product_counts_and_blocks_delete_while_in_use(): void
    {
        $admin = $this->admin();
        $electronics = $this->category('Electronics', 'electronics');

        // The baseline seeder already assigns seed products to Electronics.
        $count = Product::where('category_id', $electronics->id)->count();
        $this->assertGreaterThan(0, $count);

        // Index shows the DB-driven product count.
        $this->actingAs($admin)
            ->get(route('admin.categories.index'))
            ->assertOk()
            ->assertSee((string) $count, false);

        // Deleting a category with products is blocked with a message.
        $response = $this->actingAs($admin)
            ->delete(route('admin.categories.destroy', $electronics->id));
        $response->assertRedirect(route('admin.categories.index'));
        $this->assertTrue(Category::whereKey($electronics->id)->exists(), 'Category must not be deleted while products depend on it.');

        // An empty category is deletable (parent check passes).
        $empty = $this->category('Empty Cats', 'empty-cats');
        $this->actingAs($admin)
            ->delete(route('admin.categories.destroy', $empty->id))
            ->assertRedirect(route('admin.categories.index'));
        $this->assertFalse(Category::whereKey($empty->id)->exists());
    }

    public function test_category_filter_composes_with_pagination_and_sorting(): void
    {
        $admin = $this->admin();
        $electronics = $this->category('Electronics', 'electronics');

        // Seed seven Electronics products so pagination (per page = 6) matters.
        for ($i = 1; $i <= 7; $i++) {
            Product::create([
                'slug'        => "el-prod-{$i}",
                'title'       => "Electronics Product {$i}",
                'description' => 'Description',
                'price'       => $i * 10,
                'quantity'    => 5,
                'stock_status'=> 'in-stock',
                'category_id' => $electronics->id,
                'category_name' => 'Electronics',
                'status'      => 1,
                'is_seed'     => false,
            ]);
        }

        // Page 1 (first 6 of the 7).
        $p1 = $this->actingAs($admin)->get('/products?category=electronics&page=1');
        $p1->assertOk();
        $p1->assertSee('Electronics Product 1');
        $p1->assertSee('Electronics Product 4');
        $p1->assertDontSee('Electronics Product 5');
        $p1->assertSee('Category: Electronics', false);

        // Page 2 keeps the category filter active and shows the later items.
        $p2 = $this->actingAs($admin)->get('/products?category=electronics&page=2');
        $p2->assertOk();
        $p2->assertSee('Electronics Product 7');
        $p2->assertDontSee('Electronics Product 1');

        // Sorting composes with the category filter (price-asc).
        $sorted = $this->actingAs($admin)->get('/products?category=electronics&sort=price-asc');
        $sorted->assertViewHas('category', 'electronics');
    }
}