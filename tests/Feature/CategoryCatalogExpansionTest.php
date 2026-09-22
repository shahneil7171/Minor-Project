<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Multi-category marketplace expansion coverage (spec tests 1-18).
 *
 * Verifies that the EXISTING category system (categories table with
 * parent_id / slug / is_active, products.category_id) now holds the full
 * 20-main-category tree, that seeding is idempotent, that sellers pick
 * categories/subcategories through server-validated database relationships,
 * and that nothing pre-existing (IDs, products, routes, menus) breaks.
 */
class CategoryCatalogExpansionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * All 20 main categories: name => stable slug.
     */
    private const MAIN_CATEGORIES = [
        'Electronics' => 'electronics',
        'Fashion' => 'fashion',
        'Home & Kitchen' => 'home-kitchen',
        'Beauty & Personal Care' => 'beauty-personal-care',
        'Grocery & Food' => 'grocery-food',
        'Sports & Fitness' => 'sports-fitness',
        'Books & Education' => 'books-education',
        'Toys & Games' => 'toys-games',
        'Automotive' => 'automotive',
        'Health & Wellness' => 'health-wellness',
        'Furniture' => 'furniture',
        'Pet Supplies' => 'pet-supplies',
        'Tools & Hardware' => 'tools-hardware',
        'Outdoor & Travel' => 'outdoor-travel',
        'Baby Products' => 'baby-products',
        'Office & Business' => 'office-business',
        'Gaming' => 'gaming',
        'Garden & Outdoor Living' => 'garden-outdoor-living',
        'Religious & Spiritual' => 'religious-spiritual',
        'Gifts & Collectibles' => 'gifts-collectibles',
    ];

    /**
     * The complete expected tree: main category name => pipe-separated
     * subcategory names (as specified for KDP MART). Used to verify every
     * parent/child relationship after seeding.
     */
    private const FULL_TREE = [
        'Electronics' => 'Mobiles|Mobile Accessories|Laptops|Laptop Accessories|Tablets|Computers|Computer Accessories|Monitors|Printers & Scanners|Cameras|Camera Accessories|Headphones & Earphones|Speakers|Home Audio|Televisions|Streaming Devices|Smart Home|Smart Watches|Wearable Technology|Power Banks|Chargers & Cables|Networking Devices|Storage Devices|Computer Components|Gaming Accessories|Electronic Gadgets',
        'Fashion' => 'Men\'s Clothing|Women\'s Clothing|Kids Clothing|Boys Clothing|Girls Clothing|Men\'s Footwear|Women\'s Footwear|Kids Footwear|Watches|Handbags|Backpacks|Wallets|Belts|Sunglasses|Jewelry|Fashion Accessories|Ethnic Wear|Western Wear|Sportswear|Winter Wear|Innerwear|Luggage & Travel Bags',
        'Home & Kitchen' => 'Kitchen Appliances|Cookware|Bakeware|Kitchen Tools|Dining|Dinnerware|Glassware|Home Decor|Wall Decor|Clocks|Curtains|Rugs & Carpets|Bedding|Pillows|Blankets|Bathroom Accessories|Storage & Organization|Cleaning Supplies|Home Improvement|Home Appliances|Lighting|Lamps|Fans|Air Coolers',
        'Beauty & Personal Care' => 'Makeup|Skincare|Face Care|Hair Care|Hair Styling|Fragrances|Perfumes|Bath & Body|Grooming|Shaving & Hair Removal|Oral Care|Beauty Tools|Personal Care Appliances|Nail Care|Beauty Accessories',
        'Grocery & Food' => 'Snacks|Beverages|Tea & Coffee|Packaged Food|Cooking Essentials|Spices|Rice & Grains|Pulses|Flour|Breakfast Foods|Bakery Products|Chocolates & Sweets|Dry Fruits|Nuts & Seeds|Instant Food|Sauces & Condiments|Canned Food|Organic Food',
        'Sports & Fitness' => 'Fitness Equipment|Gym Accessories|Yoga|Running|Sportswear|Cricket|Football|Basketball|Badminton|Tennis|Table Tennis|Cycling|Swimming|Outdoor Sports|Team Sports|Sports Accessories|Fitness Trackers',
        'Books & Education' => 'Fiction|Non-Fiction|Academic Books|Engineering Books|Computer Science Books|Competitive Exam Books|School Books|Children\'s Books|Educational Materials|Stationery|Notebooks|Writing Instruments|Art Supplies|School Supplies|Office Stationery|Educational Toys',
        'Toys & Games' => 'Educational Toys|Board Games|Card Games|Puzzles|Action Figures|Dolls|Remote Control Toys|Building Sets|Outdoor Toys|Musical Toys|Baby Toys|Collectible Toys|Party Games|Video Games|Gaming Accessories',
        'Automotive' => 'Car Accessories|Bike Accessories|Car Electronics|Bike Electronics|Car Care|Cleaning & Detailing|Car Interior Accessories|Car Exterior Accessories|Motorcycle Accessories|Helmets|Vehicle Lighting|Tyres & Accessories|Tools|Safety Accessories|Travel Accessories',
        'Health & Wellness' => 'Healthcare Devices|Fitness & Wellness|First Aid|Personal Care|Health Monitoring|Massage & Relaxation|Sleep & Wellness|Medical Accessories|Mobility Accessories|Wellness Products',
        'Furniture' => 'Living Room Furniture|Sofas|Chairs|Tables|Coffee Tables|TV Units|Bedroom Furniture|Beds|Wardrobes|Dressers|Office Furniture|Office Chairs|Desks|Bookshelves|Storage Furniture|Outdoor Furniture|Kids Furniture',
        'Pet Supplies' => 'Dog Supplies|Cat Supplies|Pet Food|Pet Toys|Pet Beds|Pet Grooming|Pet Accessories|Pet Bowls & Feeders|Aquarium Supplies|Bird Supplies|Small Animal Supplies',
        'Tools & Hardware' => 'Hand Tools|Power Tools|Tool Sets|Hardware|Electrical Tools|Plumbing Supplies|Measuring Tools|Workshop Equipment|Safety Equipment|Fasteners|Adhesives|Locks & Security|Building Supplies',
        'Outdoor & Travel' => 'Luggage|Suitcases|Backpacks|Travel Bags|Travel Accessories|Camping|Hiking|Trekking|Outdoor Gear|Tents|Sleeping Bags|Travel Organizers|Travel Safety|Picnic Accessories',
        'Baby Products' => 'Baby Clothing|Baby Footwear|Diapers|Baby Feeding|Baby Bottles|Baby Care|Baby Bath|Baby Grooming|Baby Toys|Nursery|Baby Furniture|Strollers|Baby Safety|Maternity Products',
        'Office & Business' => 'Office Electronics|Printers|Scanners|Projectors|Office Furniture|Office Chairs|Desks|Stationery|Filing & Storage|Presentation Supplies|Business Supplies|Packaging Supplies|Computer Accessories|Office Organization',
        'Gaming' => 'Gaming PCs|Gaming Laptops|Gaming Consoles|Video Games|Controllers|Gaming Headsets|Gaming Keyboards|Gaming Mice|Gaming Monitors|Gaming Chairs|Gaming Accessories|Console Accessories|PC Gaming Accessories|Streaming Equipment',
        'Garden & Outdoor Living' => 'Gardening Tools|Garden Equipment|Plants|Seeds|Planters|Pots|Soil & Fertilizers|Garden Decor|Outdoor Lighting|Outdoor Furniture|Watering Equipment|Irrigation|Lawn Care|Garden Storage',
        'Religious & Spiritual' => 'Pooja Items|Religious Books|Idols & Statues|Incense|Diyas & Lamps|Prayer Accessories|Spiritual Decor|Meditation Accessories|Religious Accessories|Festival Items',
        'Gifts & Collectibles' => 'Gift Items|Personalized Gifts|Greeting Cards|Party Supplies|Collectibles|Hobby Items|Handmade Products|Souvenirs|Decorative Gifts|Celebration Accessories',
    ];

    private function seedCategories(): void
    {
        $this->seed(\Database\Seeders\CategorySeeder::class);
    }

    private function seller(): User
    {
        return User::factory()->create(['account_type' => 'seller']);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'title'        => 'Category Test Product',
            'description'  => 'A product used to verify category handling.',
            'price'        => 49,
            'quantity'     => 3,
            'stock_status' => 'in-stock',
            'status'       => 1,
            'brand'        => 'TestBrand',
        ], $overrides);
    }

    /* ------------------------------------------------- seeding / structure */

    /** TEST 1: Electronics exists. */
    public function test_electronics_category_exists(): void
    {
        $this->seedCategories();

        $electronics = Category::where('slug', 'electronics')->first();
        $this->assertNotNull($electronics, 'Electronics must exist with its stable slug.');
        $this->assertSame('Electronics', $electronics->name);
        $this->assertNull($electronics->parent_id, 'Electronics must be a main (top-level) category.');
    }

    /** TEST 2: Fashion exists. */
    public function test_fashion_category_exists(): void
    {
        $this->seedCategories();

        $fashion = Category::where('slug', 'fashion')->first();
        $this->assertNotNull($fashion, 'Fashion must exist with its stable slug.');
        $this->assertSame('Fashion', $fashion->name);
        $this->assertNull($fashion->parent_id, 'Fashion must be a main (top-level) category.');
    }

    /** TEST 3: All main categories exist. */
    public function test_all_main_categories_exist(): void
    {
        $this->seedCategories();

        foreach (self::MAIN_CATEGORIES as $name => $slug) {
            $category = Category::where('slug', $slug)->first();
            $this->assertNotNull($category, "Main category {$name} ({$slug}) is missing.");
            $this->assertSame($name, $category->name, "Unexpected name for slug {$slug}.");
            $this->assertNull($category->parent_id, "{$name} must not be nested under another category.");
        }

        // Exactly the 20 specified main categories (no duplicates, no extras).
        $this->assertSame(20, Category::query()->parent()->count());

        // And no specified SUBCATEGORY leaked in as an independent
        // top-level category — every one of them has a parent.
        foreach (self::FULL_TREE as $children) {
            foreach (explode('|', $children) as $childName) {
                $this->assertSame(
                    0,
                    Category::whereNull('parent_id')->where('name', $childName)->count(),
                    "Subcategory {$childName} must not exist as a top-level category."
                );
            }
        }
    }

    /** TEST 4: Electronics has the correct subcategories. */
    public function test_electronics_has_the_correct_subcategories(): void
    {
        $this->seedCategories();

        $electronics = Category::where('slug', 'electronics')->firstOrFail();

        foreach (explode('|', self::FULL_TREE['Electronics']) as $childName) {
            $child = Category::where('name', $childName)
                ->where('parent_id', $electronics->id)
                ->first();
            $this->assertNotNull($child, "Electronics subcategory {$childName} is missing.");
            $this->assertTrue($child->is_active);
        }

        $this->assertGreaterThanOrEqual(
            count(explode('|', self::FULL_TREE['Electronics'])),
            $electronics->children()->count()
        );
    }

    /** TEST 5: Fashion has the correct subcategories. */
    public function test_fashion_has_the_correct_subcategories(): void
    {
        $this->seedCategories();

        $fashion = Category::where('slug', 'fashion')->firstOrFail();

        foreach (explode('|', self::FULL_TREE['Fashion']) as $childName) {
            $child = Category::where('name', $childName)
                ->where('parent_id', $fashion->id)
                ->first();
            $this->assertNotNull($child, "Fashion subcategory {$childName} is missing.");
            $this->assertTrue($child->is_active);
        }

        $this->assertGreaterThanOrEqual(
            count(explode('|', self::FULL_TREE['Fashion'])),
            $fashion->children()->count()
        );
    }

    /** TEST 6: Every subcategory has the correct parent category. */
    public function test_every_subcategory_has_the_correct_parent_category(): void
    {
        $this->seedCategories();

        foreach (self::FULL_TREE as $parentName => $children) {
            $parent = Category::where('name', $parentName)->whereNull('parent_id')->first();
            $this->assertNotNull($parent, "Main category {$parentName} is missing.");

            foreach (explode('|', $children) as $childName) {
                // Look the child up INSIDE this parent: some names legally
                // exist under several parents (e.g. Sportswear under both
                // Fashion and Sports & Fitness).
                $child = Category::where('name', $childName)
                    ->where('parent_id', $parent->id)
                    ->first();
                $this->assertNotNull($child, "Subcategory {$childName} is missing under {$parentName}.");

                $this->assertSame(
                    (int) $parent->id,
                    (int) $child->parent_id,
                    "{$childName} must belong to {$parentName}."
                );

                // The architecture stays exactly two levels deep:
                // every subcategory's parent is itself top-level.
                $this->assertNull($child->parent->parent_id, "{$parentName} must be a main category.");
            }
        }
    }

    /** TEST 7: Duplicate category seeding does not create duplicates. */
    public function test_seeding_twice_does_not_create_duplicates(): void
    {
        $this->seedCategories();

        $totalAfterFirstRun = Category::count();
        $electronicsId = Category::where('slug', 'electronics')->value('id');
        $fashionId = Category::where('slug', 'fashion')->value('id');

        // Re-running is a no-op: rows are found by their stable slugs.
        $this->seedCategories();
        $this->seedCategories();

        $this->assertSame($totalAfterFirstRun, Category::count(), 'Re-seeding must not add duplicate rows.');
        $this->assertSame($electronicsId, Category::where('slug', 'electronics')->value('id'), 'Electronics ID must be preserved.');
        $this->assertSame($fashionId, Category::where('slug', 'fashion')->value('id'), 'Fashion ID must be preserved.');
        $this->assertSame(1, Category::where('slug', 'electronics')->count());
        $this->assertSame(1, Category::where('slug', 'fashion')->count());
        $this->assertSame(1, Category::where('slug', 'mobiles')->count());
    }

    /* --------------------------------------------------- seller selection */

    /** TEST 8: Seller can select a valid category. */
    public function test_seller_can_select_a_valid_category(): void
    {
        $this->seedCategories();
        $seller = $this->seller();

        // The add-product form lists every main category from the database.
        $form = $this->actingAs($seller)->get('/products/create');
        $form->assertOk();
        foreach (array_keys(self::MAIN_CATEGORIES) as $name) {
            $form->assertSee($name);
        }

        // A valid main category is accepted and stored as the relationship.
        $electronics = Category::where('slug', 'electronics')->firstOrFail();

        $this->actingAs($seller)
            ->post('/products', $this->payload(['category' => (string) $electronics->id]))
            ->assertRedirect('/products');

        $stored = Product::where('slug', 'category-test-product')->first();
        $this->assertNotNull($stored);
        $this->assertSame($electronics->id, $stored->category_id);
        $this->assertTrue($stored->category()->is($electronics));
    }

    /** TEST 9: Seller can select a valid subcategory belonging to that category. */
    public function test_seller_can_select_a_valid_subcategory(): void
    {
        $this->seedCategories();
        $seller = $this->seller();

        $electronics = Category::where('slug', 'electronics')->firstOrFail();
        $mobiles = Category::where('name', 'Mobiles')->where('parent_id', $electronics->id)->firstOrFail();

        $this->actingAs($seller)
            ->post('/products', $this->payload([
                'category'    => (string) $electronics->id,
                'subcategory' => (string) $mobiles->id,
            ]))
            ->assertRedirect('/products');

        $stored = Product::where('slug', 'category-test-product')->first();
        $this->assertNotNull($stored);
        $this->assertSame($mobiles->id, $stored->category_id, 'category_id must point at the chosen subcategory.');
        $this->assertSame('Mobiles', $stored->subcategory);
        $this->assertTrue($stored->category()->is($mobiles));
        // And the subcategory really lives under the selected main category.
        $this->assertSame($electronics->id, $stored->category->parent_id);
    }

    /** TEST 10: Invalid category/subcategory combination is rejected. */
    public function test_invalid_category_subcategory_combination_is_rejected(): void
    {
        $this->seedCategories();
        $seller = $this->seller();

        $electronics = Category::where('slug', 'electronics')->firstOrFail();
        $fashion = Category::where('slug', 'fashion')->firstOrFail();
        $mobiles = Category::where('name', 'Mobiles')->where('parent_id', $electronics->id)->firstOrFail();
        $mensClothing = Category::where('name', 'Men\'s Clothing')->where('parent_id', $fashion->id)->firstOrFail();

        // The spec's example: Electronics + Men's Clothing must be rejected.
        $this->actingAs($seller)
            ->post('/products', $this->payload([
                'title'       => 'Crossed Category Product',
                'category'    => (string) $electronics->id,
                'subcategory' => (string) $mensClothing->id,
            ]))
            ->assertSessionHasErrors('subcategory');
        $this->assertDatabaseMissing('products', ['slug' => 'crossed-category-product']);

        // The mirror case: Fashion + Mobiles must be rejected too.
        $this->actingAs($seller)
            ->post('/products', $this->payload([
                'title'       => 'Crossed Category Product',
                'category'    => (string) $fashion->id,
                'subcategory' => (string) $mobiles->id,
            ]))
            ->assertSessionHasErrors('subcategory');
        $this->assertDatabaseMissing('products', ['slug' => 'crossed-category-product']);

        // A subcategory submitted as the MAIN category is rejected as well.
        $this->actingAs($seller)
            ->post('/products', $this->payload([
                'title'    => 'Crossed Category Product',
                'category' => (string) $mobiles->id,
            ]))
            ->assertSessionHasErrors('category');
        $this->assertDatabaseMissing('products', ['slug' => 'crossed-category-product']);

        // Unknown categories are rejected server-side too.
        $this->actingAs($seller)
            ->post('/products', $this->payload([
                'title'    => 'Crossed Category Product',
                'category' => '999999',
            ]))
            ->assertSessionHasErrors('category');
        $this->assertDatabaseMissing('products', ['slug' => 'crossed-category-product']);
    }

    /* -------------------------------------------- catalog display / pages */

    /** TEST 11: Product is displayed under the selected category. */
    public function test_product_is_displayed_under_the_selected_category(): void
    {
        $this->seedCategories();
        $seller = $this->seller();

        $electronics = Category::where('slug', 'electronics')->firstOrFail();

        $this->actingAs($seller)
            ->post('/products', $this->payload(['title' => 'Nebula Phone X', 'category' => (string) $electronics->id]))
            ->assertRedirect('/products');

        // Catalog filter (?category=...) follows the stored relationship.
        $this->actingAs($seller)
            ->get('/products?category=electronics')
            ->assertOk()
            ->assertSee('Nebula Phone X');

        // The database-driven category page shows it as well.
        $this->get('/categories/electronics')
            ->assertOk()
            ->assertSee('Nebula Phone X');

        // And it does NOT leak into an unrelated category.
        $this->actingAs($seller)
            ->get('/products?category=fashion')
            ->assertOk()
            ->assertDontSee('Nebula Phone X');
        $this->get('/categories/fashion')
            ->assertOk()
            ->assertDontSee('Nebula Phone X');

        $this->assertSame($electronics->id, (int) Product::where('slug', 'nebula-phone-x')->value('category_id'));
    }

    /** TEST 12: Product is displayed under the selected subcategory. */
    public function test_product_is_displayed_under_the_selected_subcategory(): void
    {
        $this->seedCategories();
        $seller = $this->seller();

        $electronics = Category::where('slug', 'electronics')->firstOrFail();
        $mobiles = Category::where('name', 'Mobiles')->where('parent_id', $electronics->id)->firstOrFail();
        $laptops = Category::where('name', 'Laptops')->where('parent_id', $electronics->id)->firstOrFail();

        $this->actingAs($seller)
            ->post('/products', $this->payload([
                'title'       => 'Galaxy Note Phone',
                'category'    => (string) $electronics->id,
                'subcategory' => (string) $mobiles->id,
            ]))
            ->assertRedirect('/products');

        // Exact subcategory filter shows ONLY Electronics / Mobiles.
        $this->actingAs($seller)
            ->get('/products?category=mobiles')
            ->assertOk()
            ->assertSee('Galaxy Note Phone');
        $this->get('/categories/mobiles')
            ->assertOk()
            ->assertSee('Galaxy Note Phone');

        // The parent category includes its subcategory's products.
        $this->actingAs($seller)
            ->get('/products?category=electronics')
            ->assertOk()
            ->assertSee('Galaxy Note Phone');
        $this->get('/categories/electronics')
            ->assertOk()
            ->assertSee('Galaxy Note Phone');

        // Unrelated categories and sibling subcategories never see it.
        $this->actingAs($seller)
            ->get('/products?category=laptops')
            ->assertOk()
            ->assertDontSee('Galaxy Note Phone');
        $this->get('/categories/laptops')
            ->assertOk()
            ->assertDontSee('Galaxy Note Phone');
        $this->actingAs($seller)
            ->get('/products?category=fashion')
            ->assertOk()
            ->assertDontSee('Galaxy Note Phone');

        $this->assertSame($mobiles->id, (int) Product::where('slug', 'galaxy-note-phone')->value('category_id'));
    }

    /** TEST 13: Existing Electronics products still work. */
    public function test_existing_electronics_products_still_work(): void
    {
        // Baseline products are seeded by the test setup BEFORE the new
        // categories — reusing them must not disturb their relationship.
        $electronics = Category::where('slug', 'electronics')->firstOrFail();
        $idBeforeSeeding = $electronics->id;

        $this->seedCategories();

        $this->assertSame(
            $idBeforeSeeding,
            Category::where('slug', 'electronics')->value('id'),
            'The existing Electronics row must be reused, not recreated.'
        );

        $seedProduct = Product::where('category_id', $electronics->id)
            ->where('is_seed', true)
            ->first();
        $this->assertNotNull($seedProduct, 'Baseline Electronics products must remain categorised.');

        $this->actingAs($this->seller())
            ->get('/products?category=electronics')
            ->assertOk()
            ->assertSee($seedProduct->title);
        $this->get('/categories/electronics')
            ->assertOk()
            ->assertSee($seedProduct->title);
    }

    /** TEST 14: Existing Fashion products still work. */
    public function test_existing_fashion_products_still_work(): void
    {
        $fashion = Category::where('slug', 'fashion')->firstOrFail();
        $idBeforeSeeding = $fashion->id;

        $this->seedCategories();

        $this->assertSame(
            $idBeforeSeeding,
            Category::where('slug', 'fashion')->value('id'),
            'The existing Fashion row must be reused, not recreated.'
        );

        $seedProduct = Product::where('category_id', $fashion->id)
            ->where('is_seed', true)
            ->first();
        $this->assertNotNull($seedProduct, 'Baseline Fashion products must remain categorised.');

        $this->actingAs($this->seller())
            ->get('/products?category=fashion')
            ->assertOk()
            ->assertSee($seedProduct->title);
        $this->get('/categories/fashion')
            ->assertOk()
            ->assertSee($seedProduct->title);
    }

    /** TEST 15: Category URLs work (stable slug-based routes). */
    public function test_category_urls_work(): void
    {
        $this->seedCategories();

        $this->get('/categories/electronics')->assertOk()->assertSee('Electronics');
        $this->get('/categories/home-kitchen')->assertOk()->assertSee('Home & Kitchen');
        $this->get('/categories/beauty-personal-care')->assertOk()->assertSee('Beauty & Personal Care');
        $this->get('/categories/mobiles')->assertOk()->assertSee('Mobiles');

        // Subcategory pages use slugified names as specified:
        // "Men's Clothing" -> mens-clothing
        $mensSlug = Category::where('name', 'Men\'s Clothing')->value('slug');
        $this->assertSame('mens-clothing', $mensSlug);
        $this->get('/categories/' . $mensSlug)->assertOk()->assertSee('Men\'s Clothing');

        // The catalog filter accepts the same slugs.
        $this->actingAs($this->seller())
            ->get('/products?category=home-kitchen')
            ->assertOk()
            ->assertViewHas('category', 'home-kitchen');

        // Unknown categories stay a proper 404.
        $this->get('/categories/definitely-not-a-category')->assertNotFound();
    }

    /** TEST 16: Header category menu displays database categories. */
    public function test_header_category_menu_displays_database_categories(): void
    {
        $this->seedCategories();
        $seller = $this->seller();

        // The storefront header (home) lists every main category from the
        // database in its Categories dropdown…
        $home = $this->get('/');
        $home->assertOk();
        foreach (array_keys(self::MAIN_CATEGORIES) as $name) {
            $home->assertSee($name);
        }
        // …with links to the database-driven category pages.
        $home->assertSee('/categories/electronics', false);
        $home->assertSee('/categories/home-kitchen', false);
        $home->assertSee('/categories/garden-outdoor-living', false);

        // The shared layout header (products pages) does the same via the
        // navCategories view composer.
        $productsPage = $this->actingAs($seller)->get('/products');
        $productsPage->assertOk();
        foreach (array_keys(self::MAIN_CATEGORIES) as $name) {
            $productsPage->assertSee($name);
        }
    }

    /** TEST 17: Disabled categories cannot be selected for new products. */
    public function test_disabled_categories_cannot_be_selected_for_new_products(): void
    {
        $this->seedCategories();
        $seller = $this->seller();

        $toys = Category::where('slug', 'toys-games')->firstOrFail();

        // An existing product is already filed under the category…
        $this->actingAs($seller)
            ->post('/products', $this->payload([
                'title'    => 'Existing Toys Product',
                'category' => (string) $toys->id,
            ]))
            ->assertRedirect('/products');

        // …then an admin disables the category.
        $toys->update(['is_active' => false]);

        // It disappears from the NEW-product form…
        $this->actingAs($seller)
            ->get('/products/create')
            ->assertOk()
            ->assertDontSee('Toys & Games');

        // …and server-side selection is rejected for a new product.
        $this->actingAs($seller)
            ->post('/products', $this->payload([
                'title'    => 'Blocked Toys Product',
                'category' => (string) $toys->id,
            ]))
            ->assertSessionHasErrors('category');
        $this->assertDatabaseMissing('products', ['slug' => 'blocked-toys-product']);

        // Existing products are NOT deleted by the disable and remain
        // reachable through the catalog filter and their own detail page…
        $this->assertDatabaseHas('products', [
            'slug'        => 'existing-toys-product',
            'category_id' => $toys->id,
        ]);
        $this->actingAs($seller)
            ->get('/products?category=toys-games')
            ->assertOk()
            ->assertSee('Existing Toys Product');
        $this->get(route('product.show', ['product' => 'existing-toys-product']))
            ->assertOk()
            ->assertSee('Existing Toys Product');

        // …while the public category page follows the existing architecture:
        // disabled categories are no longer publicly listed (404), which is
        // exactly how the storefront communicates the disabled state.
        $this->get('/categories/toys-games')->assertNotFound();

        // The seller can still re-save the product unchanged (the current
        // category is simply kept), but cannot newly select it.
        $this->actingAs($seller)
            ->post('/products/existing-toys-product/update', $this->payload([
                'title'    => 'Existing Toys Product',
                'category' => (string) $toys->id,
            ]))
            ->assertRedirect('/products');
        $this->assertDatabaseHas('products', ['slug' => 'existing-toys-product']);
    }

    /** TEST 18: Existing products are not deleted when category data is updated. */
    public function test_existing_products_are_not_deleted_when_category_data_is_updated(): void
    {
        $this->seedCategories();
        $seller = $this->seller();

        $fashion = Category::where('slug', 'fashion')->firstOrFail();

        $this->actingAs($seller)
            ->post('/products', $this->payload([
                'title'    => 'Fashion Keepsake',
                'category' => (string) $fashion->id,
            ]))
            ->assertRedirect('/products');

        $productBefore = Product::where('slug', 'fashion-keepsake')->firstOrFail();
        $productsBefore = Product::count();
        $categoriesBefore = Category::count();

        // Category data changes: admin rename + disable + full re-seed.
        $fashion->update(['name' => 'Fashion & Lifestyle', 'is_active' => false]);
        $this->seedCategories();

        // Nothing was deleted and no duplicates appeared.
        $this->assertSame($productsBefore, Product::count(), 'No product may be deleted by category updates.');
        $this->assertSame($categoriesBefore, Category::count(), 'Re-seeding must not duplicate categories.');

        // The product keeps its identity and its relationship.
        $productAfter = Product::where('slug', 'fashion-keepsake')->firstOrFail();
        $this->assertSame($productBefore->id, $productAfter->id);
        $this->assertSame($fashion->id, $productAfter->category_id);
        $this->assertTrue($productAfter->category()->is($fashion));

        // The seeder never clobbers admin edits: the rename and the
        // disabled state survive re-seeding, and all subcategories remain.
        $fashion->refresh();
        $this->assertSame('Fashion & Lifestyle', $fashion->name);
        $this->assertFalse($fashion->is_active);
        $this->assertGreaterThanOrEqual(22, $fashion->children()->count());
    }
}