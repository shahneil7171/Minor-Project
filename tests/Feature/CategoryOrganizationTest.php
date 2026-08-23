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

    private function category(string $name, string $slug): Category
    {
        // The baseline seeder already creates some categories; reuse instead
        // of re-inserting (slug is unique).
        return Category::updateOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'is_active' => true]
        );
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
        $electronics = $this->category('Electronics', 'electronics');

        // …with a product assigned to it through the product form.
        $this->actingAs($seller)
            ->post('/products', $this->electronicsPayload($electronics))
            ->assertRedirect('/products');

        // The stored row carries the real relationship.
        $stored = \App\Models\Product::where('slug', 'mega-gadget-x')->first();
        $this->assertNotNull($stored);
        $this->assertSame($electronics->id, $stored->category_id);

        // The Eloquent relationship works: Product → Category → Products.
        $this->assertTrue($stored->category()->is($electronics));
        $this->assertTrue($electronics->products()->whereKey($stored->id)->exists());
        $this->assertSame('Electronics', $stored->fresh()->category->name);

        // The home page generates an Electronics section containing it.
        $home = $this->get('/');
        $home->assertOk();
        $home->assertSee('Electronics');
        $home->assertSee('Mega Gadget X');

        // The category tile links to the real database-driven category page.
        $home->assertSee('/categories/electronics', false);

        // The products page filter follows the same relationship.
        $this->actingAs($seller)
            ->get('/products?category=electronics')
            ->assertOk()
            ->assertSee('Mega Gadget X');
    }

    public function test_moving_a_product_between_categories_updates_the_sections(): void
    {
        $seller = $this->seller();

        $electronics = $this->category('Electronics', 'electronics');
        $laptops = $this->category('Laptops', 'laptops');

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
        $stored = \App\Models\Product::where('slug', 'mega-gadget-x')->first();
        $this->assertSame($laptops->id, $stored->category_id);
        $this->assertTrue($stored->category()->is($laptops));

        // And the home page section moved with it.
        $home = $this->get('/');
        $home->assertSee('Laptops');
    }

    public function test_category_is_never_guessed_from_product_name(): void
    {
        $seller = $this->seller();

        $fashion = $this->category('Fashion', 'fashion');

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
