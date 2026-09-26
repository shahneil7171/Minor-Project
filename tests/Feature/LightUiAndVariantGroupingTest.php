<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Notifications\StoreAlert;
use App\Services\CartService;
use App\Services\ProductCatalogService;
use App\Services\ProductVariantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Regression coverage for the three reported problems:
 *
 *   1. seller order / notification cards must be a LIGHT ui
 *   2. product options are grouped by name and generate the Cartesian product
 *   3. checkout must be able to write every OrderItem column (SQLSTATE 42S22)
 */
class LightUiAndVariantGroupingTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------
    // Issue 3 - checkout SQLSTATE 42S22
    // -----------------------------------------------------------------

    /**
     * CheckoutController::submit() writes these columns on OrderItem::create().
     * If the database is missing any one of them MySQL raises
     * "SQLSTATE[42S22] ... Unknown column", which is exactly the reported crash.
     */
    public function test_order_items_table_has_every_column_checkout_writes(): void
    {
        $written = ['product_id', 'variant_id', 'options_text', 'product_slug',
            'product_title', 'product_image', 'sku', 'price', 'quantity', 'subtotal'];

        $this->assertSame([], array_diff($written, Schema::getColumnListing('order_items')),
            'order_items is missing a column OrderItem::create() writes.');

        $model = new OrderItem;

        foreach (['product_id', 'variant_id', 'options_text', 'inventory_released_at'] as $column) {
            $this->assertContains($column, $model->getFillable(),
                "OrderItem::\$fillable must allow {$column} or it is silently dropped.");
        }
    }

    public function test_checkout_stores_product_and_variant_identity_on_the_order_item(): void
    {
        $product = $this->seedVariantProduct();
        $variant = $product['variants'][2]; // Size: M, Color: Black

        $buyer = $this->buyer();
        $this->actingAs($buyer)->withSession(['role' => 'buyer'])
            ->post('/cart/add/wireless-t-shirt', ['quantity' => 1, 'variant_id' => $variant['id']])
            ->assertRedirect('/cart');

        $this->actingAs($buyer)->post('/checkout', $this->checkoutPayload())
            ->assertRedirect('/checkout/complete');

        $item = OrderItem::latest('id')->first();

        $this->assertNotNull($item);
        $this->assertSame($product['model']->id, (int) $item->product_id);
        $this->assertSame($variant['id'], $item->variant_id);
        $this->assertSame('Size: M | Color: Black', $item->options_text);
        $this->assertSame('TS-M-Bl', $item->sku);
        $this->assertSame(549.0, (float) $item->price);
    }

    public function test_a_product_without_variants_checks_out_with_a_null_variant_id(): void
    {
        $buyer = $this->buyer();
        $this->actingAs($buyer)->withSession(['role' => 'buyer'])
            ->post('/cart/add/signature-headphones', ['quantity' => 2])
            ->assertRedirect('/cart');

        $this->actingAs($buyer)->post('/checkout', $this->checkoutPayload())
            ->assertRedirect('/checkout/complete');

        $item = OrderItem::latest('id')->first();

        $this->assertNotNull($item);
        $this->assertNull($item->variant_id);
        $this->assertNotNull($item->product_id);
    }

    /**
     * The variant identity must survive the whole flow and be visible to the
     * buyer, the seller and the admin. The delivery partner view must never
     * expose seller payment information.
     */
    public function test_variant_details_survive_from_cart_to_every_order_view(): void
    {
        $product = $this->seedVariantProduct();
        $seller = $this->seller();
        $product['model']->update(['seller_id' => $seller->id]);
        $variant = $product['variants'][2];

        $buyer = $this->buyer();
        $this->actingAs($buyer)->withSession(['role' => 'buyer'])
            ->post('/cart/add/wireless-t-shirt', ['quantity' => 2, 'variant_id' => $variant['id']]);
        $this->actingAs($buyer)->post('/checkout', $this->checkoutPayload())
            ->assertRedirect('/checkout/complete');

        $order = Order::latest('id')->firstOrFail();
        $item = $order->items->first();

        $this->assertSame('Size: M | Color: Black', $item->options_text);
        $this->assertSame('TS-M-Bl', $item->sku);
        $this->assertSame(2, $item->quantity);
        $this->assertSame(549.0, (float) $item->price);
        $this->assertSame(1098.0, (float) $item->subtotal);

        $buyerView = $this->actingAs($buyer)->get('/orders/' . $order->id)->assertOk()->getContent();
        $this->assertStringContainsString('Size: M | Color: Black', $buyerView);

        $sellerView = $this->actingAs($seller)->withSession(['role' => 'seller'])
            ->get('/seller/orders/' . $order->id)->assertOk()->getContent();
        $this->assertStringContainsString('Size: M | Color: Black', $sellerView);
        $this->assertStringContainsString('TS-M-Bl', $sellerView);

        $admin = User::factory()->create(['account_type' => 'admin']);
        $adminView = $this->actingAs($admin)->get('/admin/orders/' . $order->id)->assertOk()->getContent();
        $this->assertStringContainsString('Size: M | Color: Black', $adminView);
        $this->assertStringContainsString('TS-M-Bl', $adminView);
    }

    // -----------------------------------------------------------------
    // Issue 2 - option grouping + Cartesian variant generation
    // -----------------------------------------------------------------

    public function test_flat_one_value_per_row_options_are_grouped_by_name(): void
    {
        $grouped = ProductVariantService::groupOptions([
            ['name' => 'Storage', 'values' => ['512GB']],
            ['name' => 'Colour', 'values' => ['Silver']],
            ['name' => 'Storage', 'values' => ['1TB']],
            ['name' => 'Colour', 'values' => ['Orange']],
        ]);

        $this->assertCount(2, $grouped, 'Storage and Colour must be two options, not four.');
        $this->assertSame('Storage', $grouped[0]['name']);
        $this->assertSame(['512GB', '1TB'], $grouped[0]['values']);
        $this->assertSame('Colour', $grouped[1]['name']);
        $this->assertSame(['Silver', 'Orange'], $grouped[1]['values']);
    }

    public function test_flat_one_value_per_row_options_generate_four_variants(): void
    {
        $options = ProductVariantService::normalizeOptions([
            'name'   => ['Storage', 'Colour', 'Storage', 'Colour'],
            'values' => ['512GB', 'Silver', '1TB', 'Orange'],
        ]);

        $this->assertCount(2, $options);

        $combinations = ProductVariantService::generateVariantSelections($options);

        $this->assertCount(4, $combinations, '2 x 2 must be 4 combinations, not 1.');

        $labels = array_map(fn (array $c) => $c['Storage'] . '/' . $c['Colour'], $combinations);
        sort($labels);

        $this->assertSame(['1TB/Orange', '1TB/Silver', '512GB/Orange', '512GB/Silver'], $labels);
    }

    public function test_three_options_generate_eight_variants(): void
    {
        $options = ProductVariantService::normalizeOptions([
            'name'   => ['Storage', 'Colour', 'RAM'],
            'values' => ['512GB, 1TB', 'Silver, Orange', '8GB, 12GB'],
        ]);

        $this->assertCount(3, $options);
        $this->assertCount(8, ProductVariantService::generateVariantSelections($options));
    }

    public function test_selling_two_options_stores_four_distinct_variants(): void
    {
        $seller = $this->seller();
        $variants = [];

        foreach (ProductVariantService::generateVariantSelections([
            ['name' => 'Storage', 'values' => ['512GB', '1TB']],
            ['name' => 'Colour', 'values' => ['Silver', 'Orange']],
        ]) as $i => $selection) {
            $variants['data'][] = json_encode($selection);
            $variants['price'][] = (string) (1399 + $i * 100);
            $variants['stock'][] = '7';
            $variants['sku'][] = 'IP17-' . strtoupper(str_replace(['GB', ' '], '', $selection['Storage'] . $selection['Colour']));
        }

        $this->actingAs($seller)->withSession(['role' => 'seller'])->post('/products', [
            'title'        => 'Grouped Options Phone',
            'sku'          => 'IP17',
            'description'  => 'Two grouped options',
            'price'        => 1399,
            'quantity'     => 20,
            'stock_status' => 'in-stock',
            'category'     => 'Electronics',
            'status'       => 1,
            'options'      => [
                'name'   => ['Storage', 'Colour'],
                'values' => ['512GB, 1TB', 'Silver, Orange'],
            ],
            'variants' => $variants,
        ])->assertRedirect('/products')->assertSessionHas('success');

        $product = Product::where('slug', 'grouped-options-phone')->firstOrFail();

        $this->assertCount(2, $product->options);
        $this->assertCount(4, $product->variants);
        $this->assertCount(4, array_unique(array_column($product->variants, 'id')));

        $labels = array_map(fn (array $v) => $v['values']['Storage'] . '/' . $v['values']['Colour'], $product->variants);
        sort($labels);
        $this->assertSame(['1TB/Orange', '1TB/Silver', '512GB/Orange', '512GB/Silver'], $labels);
    }

    public function test_saving_twice_does_not_duplicate_variants(): void
    {
        $seller = $this->seller();
        $this->seedProductOwnedBy($seller);

        $payload = [
            'title'        => 'Wireless T-Shirt',
            'description'  => 'Premium cotton',
            'price'        => 499,
            'quantity'     => 10,
            'stock_status' => 'in-stock',
            'category'     => 'Electronics',
            'status'       => 1,
            'options'      => ['name' => ['Size', 'Color'], 'values' => ['S, M, L', 'Black, White']],
            'variants'     => [
                'data'  => [
                    '{"Size":"S","Color":"Black"}', '{"Size":"S","Color":"White"}',
                    '{"Size":"M","Color":"Black"}', '{"Size":"M","Color":"White"}',
                    '{"Size":"L","Color":"Black"}', '{"Size":"L","Color":"White"}',
                ],
                'price' => ['499', '509', '549', '559', '599', '609'],
                'stock' => ['10', '8', '5', '4', '2', '3'],
                'sku'   => ['A', 'B', 'C', 'D', 'E', 'F'],
            ],
        ];

        foreach ([1, 2] as $attempt) {
            $this->actingAs($seller)->withSession(['role' => 'seller'])
                ->post('/products/wireless-t-shirt/update', $payload)
                ->assertRedirect('/products');

            $product = Product::where('slug', 'wireless-t-shirt')->firstOrFail();
            $this->assertCount(6, $product->variants, "Save Changes must be idempotent (attempt {$attempt}).");
            $this->assertCount(2, $product->options);
        }
    }

    public function test_editing_a_product_keeps_variants_when_the_options_section_is_absent(): void
    {
        $seller = $this->seller();
        $seeded = $this->seedProductOwnedBy($seller);

        $this->actingAs($seller)->withSession(['role' => 'seller'])
            ->post('/products/wireless-t-shirt/update', [
                'title'        => 'Wireless T-Shirt',
                'description'  => 'Description only edit',
                'price'        => 549,
                'quantity'     => 10,
                'stock_status' => 'in-stock',
                'category'     => 'Electronics',
                'status'       => 1,
            ])->assertRedirect('/products');

        $product = Product::where('slug', 'wireless-t-shirt')->firstOrFail();

        $this->assertCount(6, $product->variants, 'A partial update must never wipe stored variants.');
        $this->assertCount(2, $product->options);
        $this->assertSame(
            array_column($seeded['variants'], 'id'),
            array_column($product->variants, 'id')
        );
    }

    public function test_regenerating_variants_on_an_existing_product_replaces_the_old_set(): void
    {
        $seller = $this->seller();
        $product = $this->seedVariantProduct();

        // Start from a product that only has ONE combination stored (the
        // state this bug report describes: 2 x 2 collapsing to a single variant).
        $single = $product['variants'][0];
        $product['model']->update([
            'seller_id' => $seller->id,
            'options'   => [
                ['name' => 'Storage', 'values' => ['512GB']],
                ['name' => 'Colour', 'values' => ['Silver']],
            ],
            'variants'  => [$single],
        ]);

        $data = [];
        $price = [];
        $stock = [];
        $sku = [];

        foreach (ProductVariantService::generateVariantSelections([
            ['name' => 'Storage', 'values' => ['512GB', '1TB']],
            ['name' => 'Colour', 'values' => ['Silver', 'Orange']],
        ]) as $i => $selection) {
            $data[] = json_encode($selection);
            $price[] = (string) (1399 + $i * 100);
            $stock[] = (string) (4 + $i);
            $sku[] = 'IP17-' . $i;
        }

        $this->actingAs($seller)->withSession(['role' => 'seller'])
            ->post('/products/wireless-t-shirt/update', [
                'title'        => 'Wireless T-Shirt',
                'sku'          => 'IP17',
                'description'  => 'Now with two grouped options',
                'price'        => 1399,
                'quantity'     => 20,
                'stock_status' => 'in-stock',
                'category'     => 'Electronics',
                'status'       => 1,
                'options'      => [
                    'name'   => ['Storage', 'Colour'],
                    'values' => ['512GB, 1TB', 'Silver, Orange'],
                ],
                'variants' => [
                    'data' => $data, 'price' => $price, 'stock' => $stock, 'sku' => $sku,
                ],
            ])->assertRedirect('/products')->assertSessionHas('success');

        $product['model']->refresh();

        $this->assertCount(2, $product['model']->options);
        $this->assertCount(4, $product['model']->variants, 'The regenerated set must be stored.');

        $byLabel = [];

        foreach ($product['model']->variants as $variant) {
            $byLabel[$variant['values']['Storage'] . '/' . $variant['values']['Colour']] = $variant;
        }

        ksort($byLabel);
        $this->assertSame(
            ['1TB/Orange', '1TB/Silver', '512GB/Orange', '512GB/Silver'],
            array_keys($byLabel)
        );

        // Every combination keeps its OWN stock, the one typed for it: no
        // variant is overwritten by another.
        $this->assertSame(4, $byLabel['512GB/Silver']['stock']);
        $this->assertSame(5, $byLabel['512GB/Orange']['stock']);
        $this->assertSame(6, $byLabel['1TB/Silver']['stock']);
        $this->assertSame(7, $byLabel['1TB/Orange']['stock']);

        // Prices and SKUs are per combination, never overwritten by one another.
        $this->assertSame(1399, $byLabel['512GB/Silver']['price']);
        $this->assertSame(1699, $byLabel['1TB/Orange']['price']);
        $this->assertSame(
            ['IP17-0', 'IP17-1', 'IP17-2', 'IP17-3'],
            array_values(array_column($product['model']->variants, 'sku'))
        );
    }

    public function test_clearing_every_option_group_turns_the_product_back_into_a_plain_product(): void
    {
        $seller = $this->seller();
        $this->seedProductOwnedBy($seller);

        // Exactly what the form posts when the seller removed every option
        // group: the marker is present but no name/values inputs remain.
        $this->actingAs($seller)->withSession(['role' => 'seller'])
            ->post('/products/wireless-t-shirt/update', [
                'title'            => 'Wireless T-Shirt',
                'description'      => 'Now a plain product',
                'price'            => 499,
                'quantity'         => 10,
                'stock_status'     => 'in-stock',
                'category'         => 'Electronics',
                'status'           => 1,
                'options'          => ['__present' => '1'],
            ])->assertRedirect('/products');

        $product = Product::where('slug', 'wireless-t-shirt')->firstOrFail();

        $this->assertSame([], $product->options);
        $this->assertSame([], $product->variants);

        // ... and it can now be bought without selecting anything.
        $buyer = $this->buyer();
        $this->actingAs($buyer)->withSession(['role' => 'buyer'])
            ->post('/cart/add/wireless-t-shirt', ['quantity' => 1])
            ->assertRedirect('/cart');
    }

    public function test_the_edit_form_reconstructs_grouped_options_for_a_legacy_product(): void
    {
        $seller = $this->seller();
        Product::create([
            'slug'         => 'legacy-flat-phone',
            'title'        => 'Legacy Flat Phone',
            'description'  => 'Stored one value per option',
            'price'        => 1399,
            'quantity'     => 10,
            'stock_status' => 'in-stock',
            'category'     => 'Electronics',
            'status'       => 1,
            'is_seed'      => false,
            'seller_id'    => $seller->id,
            'options'      => [
                ['name' => 'Storage', 'values' => ['512GB']],
                ['name' => 'Colour', 'values' => ['Silver']],
                ['name' => 'Storage', 'values' => ['1TB']],
                ['name' => 'Colour', 'values' => ['Orange']],
            ],
            'variants'     => [],
        ]);

        $html = $this->actingAs($seller)->withSession(['role' => 'seller'])
            ->get('/products/legacy-flat-phone/edit')->assertOk()->getContent();

        $this->assertStringContainsString('"name":"Storage","values":["512GB","1TB"]', $html);
        $this->assertStringContainsString('"name":"Colour","values":["Silver","Orange"]', $html);
    }

    public function test_the_option_editor_exposes_add_value_controls_on_both_product_forms(): void
    {
        $seller = $this->seller();
        $this->seedProductOwnedBy($seller);

        foreach (['/products/create', '/products/wireless-t-shirt/edit'] as $url) {
            $html = $this->actingAs($seller)->withSession(['role' => 'seller'])
                ->get($url)->assertOk()->getContent();

            $this->assertStringContainsString('function addOption(', $html, "Missing addOption() on {$url}");
            $this->assertStringContainsString('onclick="addOption()"', $html);
            $this->assertStringContainsString('function addOptionValue(', $html, "Missing addOptionValue() on {$url}");
            $this->assertStringContainsString('onclick="addOptionValue(this)"', $html);
            $this->assertStringContainsString('function generateVariants(', $html);
            $this->assertStringContainsString('onclick="generateVariants()"', $html);
            $this->assertStringContainsString('name="options[__present]"', $html);
            $this->assertStringContainsString("class=\"opt-values\" name=\"options[values][]\"", $html);
            $this->assertSame(
                substr_count($html, '<script'),
                substr_count($html, '</script>'),
                "Every <script> on {$url} must be closed."
            );
        }
    }

    // -----------------------------------------------------------------
    // Issue 2 - storefront behaviour
    // -----------------------------------------------------------------

    public function test_customer_must_pick_a_value_for_every_option(): void
    {
        $this->seedVariantProduct();
        $buyer = $this->buyer();

        $this->actingAs($buyer)->withSession(['role' => 'buyer'])
            ->post('/cart/add/wireless-t-shirt', ['quantity' => 1, 'variant_id' => 'v-forged'])
            ->assertRedirect('/products/wireless-t-shirt')
            ->assertSessionHas('error');

        $this->assertEmpty(app(CartService::class)->lines());
    }

    public function test_a_single_value_option_is_auto_selected_on_the_product_page(): void
    {
        $product = $this->seedVariantProduct();
        $only = $product['variants'][0];

        $html = $this->actingAs($this->buyer())->get('/products/wireless-t-shirt')->assertOk()->getContent();

        $this->assertStringContainsString('"name":"Size"', $html);
        $this->assertStringContainsString('"name":"Color"', $html);
        $this->assertStringContainsString($only['id'], $html);
        $this->assertStringContainsString('if ((opt.values || []).length === 1)', $html);
        $this->assertStringContainsString('updateVariant();', $html);
    }

    public function test_a_product_with_no_variants_needs_no_selection_to_be_bought_or_wishlisted(): void
    {
        $buyer = $this->buyer();

        $html = $this->actingAs($buyer)->get('/products/signature-headphones')->assertOk()->getContent();
        $this->assertStringNotContainsString('id="variantIdInput"', $html);

        $this->actingAs($buyer)->withSession(['role' => 'buyer'])
            ->post('/cart/add/signature-headphones', ['quantity' => 1])
            ->assertRedirect('/cart');

        $this->actingAs($buyer)->withSession(['role' => 'buyer'])
            ->post('/wishlist/toggle/signature-headphones')
            ->assertRedirect();

        $this->assertDatabaseHas('wishlist_items', [
            'user_id'      => $buyer->id,
            'product_slug' => 'signature-headphones',
        ]);
    }

    public function test_an_out_of_stock_variant_blocks_purchase(): void
    {
        $product = $this->seedVariantProduct();
        $soldOut = collect($product['variants'])->firstWhere(
            fn (array $v) => $v['values']['Size'] === 'M' && $v['values']['Color'] === 'Black'
        );

        $product['model']->update([
            'variants' => array_map(
                fn (array $v) => $v['id'] === $soldOut['id'] ? array_merge($v, ['stock' => 0]) : $v,
                $product['variants']
            ),
        ]);

        $response = $this->actingAs($this->buyer())->withSession(['role' => 'buyer'])
            ->post('/cart/add/wireless-t-shirt', ['quantity' => 1, 'variant_id' => $soldOut['id']]);

        $response->assertRedirect('/cart');
        $response->assertSessionHas('error');
        $this->assertEmpty(app(CartService::class)->lines());
    }

    // -----------------------------------------------------------------
    // Issue 1 - light UI
    // -----------------------------------------------------------------

    public function test_seller_order_page_uses_light_cards_and_no_dark_surfaces(): void
    {
        [$seller, $order] = $this->sellerWithOrder();

        $html = $this->actingAs($seller)->withSession(['role' => 'seller'])
            ->get('/seller/orders/' . $order->id)->assertOk()->getContent();

        foreach (['Customer Information', 'Order Information', 'Order Progress', 'Your Products in This Order'] as $heading) {
            $this->assertStringContainsString($heading, $html);
        }

        $this->assertStringContainsString('background: #FFFFFF;', $html, 'Cards must be white.');
        $this->assertStringContainsString('border: 1px solid #E5E7EB;', $html, 'Cards need a light gray border.');
        $this->assertStringContainsString('color: #111827;', $html, 'Headings must be dark navy.');
        $this->assertStringContainsString('color: #374151;', $html, 'Body text must be dark and readable.');
        $this->assertStringContainsString('color: #6B7280;', $html, 'Secondary text must be gray.');

        $this->assertStringNotContainsString('background:#111827', $html, 'The old dark navy card surface is gone.');
        $this->assertStringNotContainsString('rgba(255,255,255,0.06)', $html);

        // The timeline renders with its light-surface palette, not the old
        // pastel-on-navy one.
        $this->assertStringContainsString('#047857', $html);
        $this->assertStringNotContainsString('#6ee7b7', $html);
        $this->assertSame(
            substr_count($html, '<style'),
            substr_count($html, '</style>'),
            'The light stylesheet must be closed.'
        );
    }

    public function test_seller_order_page_is_responsive(): void
    {
        [$seller, $order] = $this->sellerWithOrder();

        $html = $this->actingAs($seller)->withSession(['role' => 'seller'])
            ->get('/seller/orders/' . $order->id)->assertOk()->getContent();

        $this->assertStringContainsString('@media (max-width: 992px)', $html, 'Tablet breakpoint missing.');
        $this->assertStringContainsString('@media (max-width: 768px)', $html, 'Mobile breakpoint missing.');
        $this->assertStringContainsString('overflow-x: auto', $html, 'Wide tables must scroll on mobile.');
    }

    public function test_seller_order_progress_timeline_keeps_every_lifecycle_step(): void
    {
        [$seller, $order] = $this->sellerWithOrder();

        $html = $this->actingAs($seller)->withSession(['role' => 'seller'])
            ->get('/seller/orders/' . $order->id)->assertOk()->getContent();

        foreach (['Order Placed', 'Confirmed', 'Processing', 'Ready for Pickup', 'Picked Up', 'Out for Delivery', 'Delivered'] as $step) {
            $this->assertStringContainsString($step, $html, "Timeline step '{$step}' is missing.");
        }
    }

    public function test_notification_cards_are_light_and_keep_read_state_distinguishable(): void
    {
        $buyer = $this->buyer();

        $buyer->notify(new StoreAlert(
            'New order approved',
            'Order #KDP-XXXX contains products from your store.',
            '/orders/1',
            ['order_number' => 'KDP-XXXX']
        ));

        $html = $this->actingAs($buyer)->get('/notifications')->assertOk()->getContent();

        $this->assertStringContainsString('New order approved', $html);
        $this->assertStringContainsString('Order #KDP-XXXX', $html);
        $this->assertStringContainsString('notif-card', $html);
        $this->assertStringContainsString('is-unread', $html, 'Unread cards must be visually distinct.');
        $this->assertStringContainsString('Unread', $html);
        $this->assertStringContainsString('View details', $html);
        $this->assertStringContainsString('Mark all read', $html);
        $this->assertStringContainsString('background: #FFFFFF;', $html);
        $this->assertStringContainsString('color: #111827;', $html);
        $this->assertStringContainsString('color: #374151;', $html);
        $this->assertStringNotContainsString('background:#111827', $html);
        $this->assertStringContainsString('@media (max-width: 768px)', $html, 'Mobile breakpoint missing.');

        // Marking as read still works.
        $notification = $buyer->notifications()->first();
        $this->actingAs($buyer)->post('/notifications/' . $notification->id . '/read')->assertRedirect();

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_the_dark_admin_timeline_theme_is_preserved(): void
    {
        [$seller, $order] = $this->sellerWithOrder();

        $dark = view('components.order-status-timeline', ['order' => $order])->render();
        $light = view('components.order-status-timeline', ['order' => $order, 'theme' => 'light'])->render();

        $this->assertStringContainsString('#6ee7b7', $dark, 'Default (dark) timeline colours must be preserved.');
        $this->assertStringNotContainsString('#6ee7b7', $light);
        $this->assertStringContainsString('#047857', $light);

        // Both render the same steps: only the surface styling differs.
        foreach (['Order Placed', 'Delivered'] as $step) {
            $this->assertStringContainsString($step, $dark);
            $this->assertStringContainsString($step, $light);
        }

        $this->assertSame('seller', $seller->account_type);
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    private function seller(): User
    {
        return User::factory()->create(['account_type' => 'seller']);
    }

    private function buyer(): User
    {
        return User::factory()->create(['account_type' => 'buyer']);
    }

    private function checkoutPayload(): array
    {
        return [
            'address_option'  => 'new',
            'shipping_method' => 'standard',
            'payment_method'  => 'cod',
            'new_address'     => [
                'full_name'      => 'Alice',
                'phone'          => '1234567890',
                'house_number'   => '1',
                'street_address' => 'Main Street',
                'city'           => 'Metropolis',
                'state'          => 'State',
                'pincode'        => '10001',
                'country'        => 'India',
            ],
        ];
    }

    private function seedVariantProduct(): array
    {
        $options = [
            ['name' => 'Size', 'values' => ['S', 'M', 'L']],
            ['name' => 'Color', 'values' => ['Black', 'White']],
        ];

        $priceMap = [0 => 499, 1 => 509, 2 => 549, 3 => 559, 4 => 599, 5 => 609];
        $stockMap = [0 => 10, 1 => 8, 2 => 5, 3 => 4, 4 => 2, 5 => 3];
        $variants = [];
        $i = 0;

        foreach (ProductVariantService::generateVariantSelections($options) as $selection) {
            $sorted = $selection;
            ksort($sorted, SORT_STRING);
            $variants[] = [
                'id'     => 'v' . substr(md5(json_encode($sorted)), 0, 12),
                'values' => $selection,
                'sku'    => 'TS-' . $selection['Size'] . '-' . substr($selection['Color'], 0, 2),
                'price'  => $priceMap[$i],
                'stock'  => $stockMap[$i],
            ];
            $i++;
        }

        $model = Product::create([
            'slug'         => 'wireless-t-shirt',
            'title'        => 'Wireless T-Shirt',
            'subtitle'     => 'Cool',
            'description'  => 'Premium cotton',
            'price'        => 499,
            'quantity'     => 10,
            'stock_status' => 'in-stock',
            'category'     => 'Electronics',
            'category_id'  => app(ProductCatalogService::class)->resolveCategory('Electronics')?->id,
            'subcategory'  => 'Accessories',
            'status'       => 1,
            'tags'         => [],
            'options'      => $options,
            'variants'     => $variants,
            'is_seed'      => false,
        ]);

        return ['model' => $model, 'options' => $options, 'variants' => $variants];
    }

    private function seedProductOwnedBy(User $seller): array
    {
        $seeded = $this->seedVariantProduct();
        $seeded['model']->update(['seller_id' => $seller->id]);

        return $seeded;
    }

    /**
     * @return array{0: User, 1: Order}
     */
    private function sellerWithOrder(): array
    {
        $seller = $this->seller();
        $buyer = $this->buyer();
        $seeded = $this->seedVariantProduct();
        $seeded['model']->update(['seller_id' => $seller->id]);

        $order = Order::create([
            'user_id'          => $buyer->id,
            'customer_email'   => $buyer->email,
            'order_number'     => 'KDP-' . strtoupper(uniqid()),
            'status'           => 'confirmed',
            'subtotal'         => 549,
            'tax'              => 0,
            'shipping_cost'    => 0,
            'total'            => 549,
            'payment_method'   => 'cod',
            'shipping_name'    => 'Tobey Spider',
            'shipping_phone'   => '9999999999',
            'shipping_address' => '1 Main St',
            'shipping_city'    => 'Springfield',
            'shipping_state'   => 'IL',
            'shipping_pincode' => '10001',
        ]);

        OrderItem::create([
            'order_id'      => $order->id,
            'product_slug'  => 'wireless-t-shirt',
            'product_id'    => $seeded['model']->id,
            'variant_id'    => $seeded['variants'][2]['id'],
            'product_title' => 'Wireless T-Shirt',
            'sku'           => 'TS-M-BK',
            'price'         => 549,
            'quantity'      => 1,
            'subtotal'      => 549,
            'options_text'  => 'Size: M | Color: Black',
            'seller_id'     => $seller->id,
        ]);

        return [$seller, $order->fresh()];
    }
}
