<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * KDP MART — Seller Product Ownership.
 *
 * Every product has exactly one owner (products.seller_id). The seller who
 * creates a product owns it and is the only seller who can manage it.
 * Ownership NEVER affects storefront visibility: any visitor (buyer, other
 * seller, guest) can still discover, view, cart, wishlist and buy the
 * product — they simply cannot manage it.
 *
 * All ownership rules are enforced server-side (route closures), so they
 * hold even for direct URL access or forged POSTs.
 */
class SellerProductOwnershipTest extends TestCase
{
    use RefreshDatabase;

    private array $productPayload = [
        'title'         => 'Samsung Galaxy S26 Ultra',
        'description'   => 'A flagship smartphone.',
        'price'         => '999.99',
        'quantity'      => '5',
        'stock_status'  => 'in-stock',
        'category'      => 'Electronics',
    ];

    private function seller(?string $email = null): User
    {
        return User::factory()->create([
            'account_type' => 'seller',
            'email' => $email,
        ]);
    }

    // -----------------------------------------------------------------------
    // EDIT / DELETE AUTHORIZATION
    // -----------------------------------------------------------------------

    public function test_seller_can_edit_own_product(): void
    {
        $sellerA = $this->seller('seller-a@example.com');
        $product = $this->createProduct($sellerA);

        $this->actingAs($sellerA)
            ->get("/products/{$product->slug}/edit")
            ->assertOk();

        $this->actingAs($sellerA)
            ->post("/products/{$product->slug}/update", array_merge($this->productPayload, ['price' => '1099.99']))
            ->assertRedirect(route('products'));

        $this->assertSame(1099.99, $product->fresh()->price);
    }

    public function test_seller_cannot_edit_another_sellers_product(): void
    {
        $sellerA = $this->seller('seller-a@example.com');
        $sellerB = $this->seller('seller-b@example.com');
        $product = $this->createProduct($sellerA);

        $this->actingAs($sellerB)
            ->get("/products/{$product->slug}/edit")
            ->assertForbidden();

        $this->actingAs($sellerB)
            ->post("/products/{$product->slug}/update", array_merge($this->productPayload, [
                'title' => 'Hijacked Title',
                'price' => '1.00',
            ]))
            ->assertForbidden();

        $product->refresh();
        $this->assertSame('Samsung Galaxy S26 Ultra', $product->title);
        $this->assertSame(999.99, $product->price);
    }

    public function test_seller_cannot_delete_another_sellers_product(): void
    {
        $sellerA = $this->seller('seller-a@example.com');
        $sellerB = $this->seller('seller-b@example.com');
        $product = $this->createProduct($sellerA);

        $this->actingAs($sellerB)
            ->post("/products/{$product->slug}/delete")
            ->assertForbidden();

        $this->assertDatabaseHas('products', ['id' => $product->id]);
    }

    public function test_seller_can_delete_own_product(): void
    {
        $sellerA = $this->seller('seller-a@example.com');
        $product = $this->createProduct($sellerA);

        $this->actingAs($sellerA)
            ->post("/products/{$product->slug}/delete")
            ->assertRedirect(route('products'));

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    private function createProduct(User $owner, array $overrides = []): Product
    {
        $this->actingAs($owner)
            ->post('/products', array_merge($this->productPayload, $overrides));

        return Product::where('title', array_merge($this->productPayload, $overrides)['title'])->firstOrFail();
    }

    private function checkoutFields(): array
    {
        return [
            'address_option'    => 'new',
            'shipping_method'   => 'standard',
            'payment_method'    => 'cod',
            'new_address'       => [
                'full_name'      => 'Jane Doe',
                'phone'          => '1234567890',
                'house_number'   => '123',
                'street_address' => 'Main Street',
                'city'           => 'New York',
                'state'          => 'NY',
                'pincode'        => '10001',
                'country'        => 'India',
            ],
        ];
    }

    // -----------------------------------------------------------------------
    // CREATION & OWNERSHIP
    // -----------------------------------------------------------------------

    public function test_seller_can_create_a_product(): void
    {
        $sellerA = $this->seller('seller-a@example.com');

        $response = $this->actingAs($sellerA)->post('/products', $this->productPayload);

        $response->assertRedirect(route('products'));
        $this->assertDatabaseHas('products', ['title' => 'Samsung Galaxy S26 Ultra']);
    }

    public function test_created_product_automatically_belongs_to_the_authenticated_seller(): void
    {
        $sellerA = $this->seller('seller-a@example.com');

        $product = $this->createProduct($sellerA);

        $this->assertSame($sellerA->id, $product->seller_id);
        $this->assertSame($sellerA->id, $product->fresh()->seller_id);
    }

    public function test_seller_cannot_assign_product_ownership_to_another_seller(): void
    {
        $sellerA = $this->seller('seller-a@example.com');
        $sellerB = $this->seller('seller-b@example.com');

        // Malicious request: forged seller_id must be ignored outright.
        $product = $this->createProduct($sellerA, ['seller_id' => (string) $sellerB->id]);

        $this->assertSame($sellerA->id, $product->seller_id);
        $this->assertNotSame($sellerB->id, $product->seller_id);
    }

    public function test_seller_cannot_take_ownership_of_a_product_via_update(): void
    {
        $sellerA = $this->seller('seller-a@example.com');
        $sellerB = $this->seller('seller-b@example.com');

        // Seed products are unowned (marketplace items): a seller cannot
        // claim them by POSTing a forged update.
        $product = Product::where('slug', 'smart-watch-pro')->firstOrFail();

        $this->actingAs($sellerB)
            ->post("/products/{$product->slug}/update", array_merge($this->productPayload, [
                'title' => 'Smart Watch Pro (hijacked)',
                'seller_id' => (string) $sellerB->id,
            ]))
            ->assertForbidden();

        $product->refresh();
        $this->assertNull($product->seller_id);
        $this->assertSame('Smart Watch Pro', $product->title);
    }

    // -----------------------------------------------------------------------
    // OTHER ROLES
    // -----------------------------------------------------------------------

    public function test_buyer_cannot_edit_seller_products(): void
    {
        $sellerA = $this->seller('seller-a@example.com');
        $product = $this->createProduct($sellerA);
        $buyer = User::factory()->create(['account_type' => 'buyer']);

        $this->actingAs($buyer)
            ->get("/products/{$product->slug}/edit")
            ->assertRedirect(route('products'))
            ->assertSessionHas('error', 'Only sellers or admins can edit products.');
    }

    public function test_delivery_partner_cannot_edit_seller_products(): void
    {
        $sellerA = $this->seller('seller-a@example.com');
        $product = $this->createProduct($sellerA);
        $partner = User::factory()->create(['account_type' => 'delivery_partner']);

        $this->actingAs($partner)
            ->get("/products/{$product->slug}/edit")
            ->assertRedirect(route('products'))
            ->assertSessionHas('error', 'Only sellers or admins can edit products.');
    }

    public function test_staff_cannot_edit_seller_products_without_explicit_permission(): void
    {
        $sellerA = $this->seller('seller-a@example.com');
        $product = $this->createProduct($sellerA);
        $staff = User::factory()->create(['account_type' => 'staff']);

        $this->actingAs($staff)
            ->get("/products/{$product->slug}/edit")
            ->assertRedirect(route('products'))
            ->assertSessionHas('error', 'Only sellers or admins can edit products.');
    }

    public function test_admin_retains_existing_product_permissions(): void
    {
        $sellerA = $this->seller('seller-a@example.com');
        $product = $this->createProduct($sellerA);
        $admin = User::factory()->create(['account_type' => 'admin']);

        // Admin can open the edit form and update any product...
        $this->actingAs($admin)
            ->get("/products/{$product->slug}/edit")
            ->assertOk();

        $this->actingAs($admin)
            ->post("/products/{$product->slug}/update", array_merge($this->productPayload, ['price' => '1199.99']))
            ->assertRedirect(route('products'));

        // ...without accidentally changing the seller owner.
        $product->refresh();
        $this->assertSame(1199.99, $product->price);
        $this->assertSame($sellerA->id, $product->seller_id);

        // Admin can also delete products (existing admin behaviour).
        $this->actingAs($admin)
            ->post("/products/{$product->slug}/delete")
            ->assertRedirect(route('products'));

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    // -----------------------------------------------------------------------
    // SELLER PRODUCT LIST & STOREFRONT VISIBILITY
    // -----------------------------------------------------------------------

    public function test_seller_product_list_contains_only_own_products(): void
    {
        $sellerA = $this->seller('seller-a@example.com');
        $sellerB = $this->seller('seller-b@example.com');

        $this->createProduct($sellerA, ['title' => 'Product A Own Item']);
        $this->createProduct($sellerB, ['title' => 'Product B Foreign Item']);

        $response = $this->actingAs($sellerA)->get(route('seller.products.index'));

        $response->assertOk();
        $response->assertSee('Product A Own Item');
        $response->assertDontSee('Product B Foreign Item');
    }

    public function test_storefront_contains_products_from_all_sellers(): void
    {
        $sellerA = $this->seller('seller-a@example.com');
        $sellerB = $this->seller('seller-b@example.com');
        $this->createProduct($sellerA, ['title' => 'Seller A Storefront Item']);
        $this->createProduct($sellerB, ['title' => 'Seller B Storefront Item']);

        $this->get(route('products'))
            ->assertOk()
            ->assertSee('Seller A Storefront Item')
            ->assertSee('Seller B Storefront Item');
    }

    // -----------------------------------------------------------------------
    // OTHER SELLER = NORMAL SHOPPER
    // -----------------------------------------------------------------------

    public function test_seller_can_view_another_sellers_product(): void
    {
        $sellerA = $this->seller('seller-a@example.com');
        $sellerB = $this->seller('seller-b@example.com');
        $product = $this->createProduct($sellerA);

        $this->actingAs($sellerB)
            ->get(route('product.show', ['product' => $product->slug]))
            ->assertOk()
            ->assertViewHas('product', fn ($p) => ($p['seller_id'] ?? null) === $sellerA->id);
    }

    public function test_seller_can_add_another_sellers_product_to_cart(): void
    {
        $sellerA = $this->seller('seller-a@example.com');
        $sellerB = $this->seller('seller-b@example.com');
        $product = $this->createProduct($sellerA);

        $this->actingAs($sellerB)
            ->post(route('cart.add', ['product' => $product->slug]), ['quantity' => 1])
            ->assertRedirect(route('cart.index'))
            ->assertSessionHas('success', 'Product added to cart.');

        $cart = app(\App\Services\CartService::class)->lines();
        $this->assertArrayHasKey($product->slug, $cart);
    }

    public function test_seller_can_wishlist_another_sellers_product(): void
    {
        $sellerA = $this->seller('seller-a@example.com');
        $sellerB = $this->seller('seller-b@example.com');
        $product = $this->createProduct($sellerA);

        $this->actingAs($sellerB)
            ->withHeaders(['Referer' => route('product.show', ['product' => $product->slug])])
            ->post(route('wishlist.toggle', ['product' => $product->slug]))
            ->assertRedirect()
            ->assertSessionHas('status', 'Added to wishlist.');

        $this->assertDatabaseHas('wishlist_items', [
            'user_id' => $sellerB->id,
            'product_slug' => $product->slug,
        ]);
    }

    public function test_product_card_shows_edit_only_to_the_owner(): void
    {
        $sellerA = $this->seller('seller-a@example.com');
        $sellerB = $this->seller('seller-b@example.com');
        $product = $this->createProduct($sellerA);

        $editUrl = "/products/{$product->slug}/edit";

        // Owner sees the management controls.
        $this->actingAs($sellerA)
            ->get(route('products'))
            ->assertOk()
            ->assertSee($editUrl);

        // Another seller sees normal shopping controls — never Edit/Remove.
        $this->actingAs($sellerB)
            ->get(route('products'))
            ->assertOk()
            ->assertDontSee($editUrl)
            ->assertSee('Add to cart');
    }

    public function test_other_sellers_see_normal_shopping_controls_on_the_detail_page(): void
    {
        $sellerA = $this->seller('seller-a@example.com');
        $sellerB = $this->seller('seller-b@example.com');
        $product = $this->createProduct($sellerA);

        $this->actingAs($sellerB)
            ->get(route('product.show', ['product' => $product->slug]))
            ->assertOk()
            ->assertSee('Add to cart')
            ->assertDontSee('Edit Product')
            ->assertDontSee('Remove Product');
    }

    // -----------------------------------------------------------------------
    // ORDER ITEMS SNAPSHOT THE SELLER AT PURCHASE TIME
    // -----------------------------------------------------------------------

    public function test_order_item_correctly_identifies_the_seller(): void
    {
        $sellerA = $this->seller('seller-a@example.com');
        $product = $this->createProduct($sellerA);
        $buyer = User::factory()->create(['account_type' => 'buyer']);

        $this->actingAs($buyer);
        app(\App\Services\CartService::class)->save([
            $product->slug => [
                'product'  => $product->slug,
                'title'    => $product->title,
                'price'    => (float) $product->price,
                'quantity' => 1,
                'sku'      => $product->sku,
                'image'    => $product->image,
            ],
        ]);

        $this->post('/checkout', $this->checkoutFields())->assertRedirect('/checkout/complete');

        $order = Order::where('user_id', $buyer->id)->firstOrFail();
        $item = $order->items()->firstOrFail();

        // Historical snapshot: the line points at the seller who owned the
        // product at purchase time (not resolved live from the product).
        $this->assertSame($sellerA->id, $item->seller_id);
        $this->assertTrue($item->seller->is($sellerA));
    }

    public function test_multi_seller_order_keeps_seller_ownership_per_item(): void
    {
        $sellerA = $this->seller('seller-a@example.com');
        $sellerB = $this->seller('seller-b@example.com');
        $productA = $this->createProduct($sellerA, ['title' => 'Order Phone', 'price' => '50000']);
        $productB = $this->createProduct($sellerB, ['title' => 'Order Phone Case', 'price' => '1000']);
        $buyer = User::factory()->create(['account_type' => 'buyer']);

        // One order, two sellers: item 1 -> Seller A, item 2 -> Seller B.
        $this->actingAs($buyer);
        app(\App\Services\CartService::class)->save([
            $productA->slug => [
                'product'  => $productA->slug,
                'title'    => $productA->title,
                'price'    => 50000.0,
                'quantity' => 1,
                'sku'      => $productA->sku,
                'image'    => $productA->image,
            ],
            $productB->slug => [
                'product'  => $productB->slug,
                'title'    => $productB->title,
                'price'    => 1000.0,
                'quantity' => 1,
                'sku'      => $productB->sku,
                'image'    => $productB->image,
            ],
        ]);

        $this->post('/checkout', $this->checkoutFields())->assertRedirect('/checkout/complete');

        $order = Order::where('user_id', $buyer->id)->firstOrFail();

        $this->assertSame(2, $order->items()->count());

        $itemA = $order->items()->where('product_slug', $productA->slug)->firstOrFail();
        $itemB = $order->items()->where('product_slug', $productB->slug)->firstOrFail();

        // The ₹51,000 order is NEVER attributed to one seller: each line
        // belongs to its own seller.
        $this->assertSame($sellerA->id, $itemA->seller_id);
        $this->assertSame($sellerB->id, $itemB->seller_id);

        // Each seller owns exactly one line of this order.
        $this->assertSame(1, $order->items()->where('seller_id', $sellerA->id)->count());
        $this->assertSame(1, $order->items()->where('seller_id', $sellerB->id)->count());
    }
}
