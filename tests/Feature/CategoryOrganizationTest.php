<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Automatic product category organisation.
 *
 * The database `categories` table is the single source of truth:
 * - Admin-created products store the real relationship (category_id).
 * - Home page category sections are generated from the database.
 * - Moving a product to another category automatically moves it between
 *   sections — never guessed from the product name/description/SKU/images.
 */
class CategoryOrganizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::disk('local')->delete('custom_products_test.json');
    }

    private function seller(): User
    {
        return User::factory()->create(['account_type' => 'seller']);
    }

    private function electronicsPayload(Category $category): array
    {
        return [
            'title'       => 'Mega Gadget X',
            'description' => 'A brand new electronic gadget.',
            'price'       => 99,
            'quantity'    => 5,
            'stock_status' => 'in-stock',
            'category'    => (string) $category->id,
            'status'      => 1,
            'brand'       => 'Acme',
        ];
    }

    public function test_new_product_appears_in_its_database_category_section(): void
    {
        $seller = $this->seller();

        // A new category created through the admin panel…
        $electronics = Category::create([
            'name' => 'Electronics', 'slug' => 'electronics', 'is_active' => true,
        ]);

        // …with a product assigned to it through the product form.
        $this->actingAs($seller)
            ->post('/products', $this->electronicsPayload($electronics))
            ->assertRedirect('/products');

        // The stored record carries the real relationship.
        Storage::disk('local')->assertExists('custom_products_test.json');
        $stored = json_decode(Storage::disk('local')->get('custom_products_test.json'), true);
        $this->assertSame($electronics->id, $stored['mega-gadget-x']['category_id']);
        $this->assertSame('Electronics', $stored['mega-gadget-x']['category']);

        // The home page generates an Electronics section containing it.
        $home = $this->get('/');
        $home->assertOk();
        $home->assertSee('Electronics');
        $home->assertSee('Mega Gadget X');

        // The category tile filters by the database slug, not a text search.
        $home->assertSee('category=electronics', false);

        // The products page filter follows the same relationship.
        $this->actingAs($seller)
            ->get('/products?category=electronics')
            ->assertOk()
            ->assertSee('Mega Gadget X');
    }

    public function test_moving_a_product_between_categories_updates_the_sections(): void
    {
        $seller = $this->seller();

        $electronics = Category::create(['name' => 'Electronics', 'slug' => 'electronics', 'is_active' => true]);
        $laptops = Category::create(['name' => 'Laptops', 'slug' => 'laptops', 'is_active' => true]);

        $this->actingAs($seller)->post('/products', $this->electronicsPayload($electronics));

        // Before: product sits in Electronics.
        $before = $this->get('/products?category=electronics');
        $before->assertOk()->assertSee('Mega Gadget X');

        // Move the product to Laptops through the edit form.
        $payload = $this->electronicsPayload($laptops);
        $payload['title'] = 'Mega Gadget X';
        $payload['description'] = 'A brand new electronic gadget.';
        $this->actingAs($seller)
            ->post('/products/mega-gadget-x/update', $payload)
            ->assertRedirect('/products');

        // After: gone from Electronics, present under Laptops.
        $this->actingAs($seller)
            ->get('/products?category=electronics')
            ->assertOk()
            ->assertDontSee('Mega Gadget X');

        $this->actingAs($seller)
            ->get('/products?category=laptops')
            ->assertOk()
            ->assertSee('Mega Gadget X');

        // The stored relationship was updated as well.
        $stored = json_decode(Storage::disk('local')->get('custom_products_test.json'), true);
        $this->assertSame($laptops->id, $stored['mega-gadget-x']['category_id']);

        // And the home page section moved with it.
        $home = $this->get('/');
        $home->assertSee('Laptops');
    }

    public function test_category_is_never_guessed_from_product_name(): void
    {
        $seller = $this->seller();

        $fashion = Category::create(['name' => 'Fashion', 'slug' => 'fashion', 'is_active' => true]);

        // The product NAME mentions Electronics, but the selected category
        // is Fashion — the relationship must win over the name.
        $payload = $this->electronicsPayload($fashion);
        $payload['title'] = 'Electronic Style Watch Strap';
        $payload['description'] = 'Electronics-inspired fashion accessory.';

        $this->actingAs($seller)->post('/products', $payload)->assertRedirect('/products');

        $this->actingAs($seller)
            ->get('/products?category=fashion')
            ->assertOk()
            ->assertSee('Electronic Style Watch Strap');

        $this->actingAs($seller)
            ->get('/products?category=electronics')
            ->assertOk()
            ->assertDontSee('Electronic Style Watch Strap');
    }
}
