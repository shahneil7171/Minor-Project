<?php

namespace Tests\Feature;

use App\Mail\OrderApprovedMail;
use App\Mail\SellerOrderApprovedMail;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CurrencyService;
use App\Services\OrderWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * KDP MART — Navigation translation + product ownership.
 *
 * PART 1 (translation): Phase 3 stored the strings in FLAT files
 * (lang/en.php, lang/hi.php, lang/gu.php). Laravel's FileLoader resolves a
 * dotted key like __('nav.home') by loading lang/{locale}/nav.php, so the flat
 * files were never read and the raw key ("nav.home") was rendered to customers.
 * These tests pin the group-file layout: the same keys must resolve in en/hi/gu
 * and no raw key may ever reach the page.
 *
 * PART 2 (ownership): a product is either seller-owned (seller_id set) or
 * platform-owned (seller_id NULL). Admin approval is NOT seller assignment, so
 * platform products must never be attributed to a seller, in the UI or in an
 * order item.
 */
class NavigationAndOwnershipTest extends TestCase
{
    use RefreshDatabase;

    /** The exact navigation keys required to resolve in every locale. */
    private const NAV_KEYS = [
        'nav.home',
        'nav.categories',
        'nav.products',
        'nav.deals',
        'nav.about',
        'nav.contact',
    ];

    /**
     * A title that is NOT one of the three config/catalog.php seed products
     * ("Smart Watch Pro", "Signature Headphones", "Premium Backpack"), so the
     * product created here is always the row under test.
     */
    private array $productPayload = [
        'title'        => 'Aurora Desk Lamp',
        'description'  => 'A platform-owned desk lamp.',
        'price'        => '2499.00',
        'quantity'     => '10',
        'stock_status' => 'in-stock',
        'category'     => 'Electronics',
    ];

    // -----------------------------------------------------------------------
    // 1-3. EN / HI / GU NAVIGATION TRANSLATION
    // -----------------------------------------------------------------------

    public function test_english_navigation_keys_resolve_to_english_text(): void
    {
        app()->setLocale('en');

        $expected = [
            'nav.home'       => 'Home',
            'nav.categories' => 'Categories',
            'nav.products'   => 'Products',
            'nav.deals'      => 'Deals',
            'nav.about'      => 'About Us',
            'nav.contact'    => 'Contact',
        ];

        foreach ($expected as $key => $value) {
            $this->assertSame($value, __($key), "{$key} must resolve in English.");
        }
    }

    public function test_hindi_navigation_keys_resolve_to_hindi_text(): void
    {
        app()->setLocale('hi');

        $expected = [
            'nav.home'       => 'होम',
            'nav.categories' => 'श्रेणियाँ',
            'nav.products'   => 'उत्पाद',
            'nav.deals'      => 'ऑफ़र',
            'nav.about'      => 'हमारे बारे में',
            'nav.contact'    => 'संपर्क',
        ];

        foreach ($expected as $key => $value) {
            $this->assertSame($value, __($key), "{$key} must resolve in Hindi.");
        }
    }

    public function test_gujarati_navigation_keys_resolve_to_gujarati_text(): void
    {
        app()->setLocale('gu');

        $expected = [
            'nav.home'       => 'હોમ',
            'nav.categories' => 'શ્રેણીઓ',
            'nav.products'   => 'ઉત્પાદનો',
            'nav.deals'      => 'ઑફર્સ',
            'nav.about'      => 'અમારા વિશે',
            'nav.contact'    => 'સંપર્ક',
        ];

        foreach ($expected as $key => $value) {
            $this->assertSame($value, __($key), "{$key} must resolve in Gujarati.");
        }
    }

    /** The same key set must exist in every locale file (no drift). */
    public function test_every_locale_file_defines_the_same_navigation_keys(): void
    {
        $perLocale = [];

        foreach (['en', 'hi', 'gu'] as $locale) {
            $perLocale[$locale] = array_keys(require lang_path("{$locale}/nav.php"));
        }

        $this->assertSame($perLocale['en'], $perLocale['hi'], 'Hindi nav keys must match English.');
        $this->assertSame($perLocale['en'], $perLocale['gu'], 'Gujarati nav keys must match English.');

        foreach (self::NAV_KEYS as $key) {
            $this->assertContains(substr($key, 4), $perLocale['en'], "{$key} must exist in en/nav.php");
        }
    }

    /** Every shipped key resolves to real text, never back to the key itself. */
    public function test_shipped_keys_never_leak_a_raw_key_in_any_locale(): void
    {
        foreach (['en', 'hi', 'gu'] as $locale) {
            app()->setLocale($locale);

            foreach (self::NAV_KEYS as $key) {
                $this->assertNotSame($key, __($key), "{$key} must not leak a raw key in {$locale}.");
            }
        }
    }

    /**
     * The rendered storefront nav must contain text, never "nav.*".
     *
     * One locale per test method on purpose: PreferenceService is a
     * per-request singleton that memoises the resolved locale, so issuing
     * several differently-localised requests inside ONE test method would
     * reuse the first request's locale. Real requests each get a fresh
     * instance, so this only affects the test, not the app.
     */
    public function test_english_storefront_navigation_never_renders_a_raw_key(): void
    {
        $this->assertNavHasNoRawKey('en', 'Home');
    }

    public function test_hindi_storefront_navigation_never_renders_a_raw_key(): void
    {
        $this->assertNavHasNoRawKey('hi', 'होम');
    }

    public function test_gujarati_storefront_navigation_never_renders_a_raw_key(): void
    {
        $this->assertNavHasNoRawKey('gu', 'હોમ');
    }

    private function assertNavHasNoRawKey(string $locale, string $expectedHome): void
    {
        $html = $this->withSession([config('locale.session_key') => $locale])
            ->get('/')
            ->assertOk()
            ->assertSee($expectedHome)
            ->getContent();

        // A raw key only ever leaks as the rendered TEXT of an element
        // (e.g. >nav.home<), so match that exact shape rather than the bare
        // substring, which would also hit unrelated CSS/JS.
        foreach (self::NAV_KEYS as $key) {
            $this->assertStringNotContainsString(
                '>'.$key.'<',
                $html,
                "{$key} leaked as a raw key into the {$locale} navigation."
            );
        }
    }

    // -----------------------------------------------------------------------
    // 4. LOCALE SWITCHING + PERSISTENCE
    // -----------------------------------------------------------------------

    public function test_locale_switch_persists_and_changes_the_application_locale(): void
    {
        $this->get('/')->assertOk()->assertSee('<html lang="en"', false);

        $this->post('/preferences/locale', ['locale' => 'hi'])
            ->assertSessionHas(config('locale.session_key'), 'hi');

        // The switch response is the last one rendered, so app()->getLocale()
        // here reflects the NEW choice: the controller applies it immediately
        // (PreferenceService::setLocale) to avoid an English-then-Hindi flicker.
        $this->assertSame('hi', app()->getLocale());

        // Refresh: SetPreferences re-applies the stored session choice on the
        // next request, and it keeps holding across storefront navigation.
        // (All public pages - /products itself is behind the auth middleware.)
        foreach (['/', '/contact', '/about'] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('<html lang="hi"', false);
        }
    }

    public function test_an_unsupported_locale_is_rejected_and_never_applied(): void
    {
        $this->post('/preferences/locale', ['locale' => 'fr'])
            ->assertSessionHasErrors('locale')
            ->assertSessionMissing(config('locale.session_key'));

        $this->assertSame('en', app()->getLocale());
    }

    /**
     * Language and currency are independent: changing one keeps the other.
     *
     * The currency is set first, then the language - two requests in one
     * test method, which is valid here because the locale under test is the
     * FINAL one asserted (the middleware re-applies it on the second request).
     */
    public function test_changing_language_does_not_reset_currency(): void
    {
        $this->post('/preferences/currency', ['currency' => 'EUR'])
            ->assertSessionHas(config('currency.session_key'), 'EUR');

        $this->post('/preferences/locale', ['locale' => 'gu'])->assertRedirect();

        $this->assertSame('EUR', app(CurrencyService::class)->code());
        $this->assertSame('gu', app()->getLocale());
    }

    public function test_changing_currency_does_not_reset_language(): void
    {
        $this->post('/preferences/locale', ['locale' => 'hi'])->assertRedirect();

        $this->post('/preferences/currency', ['currency' => 'GBP'])->assertRedirect();

        $this->assertSame('GBP', app(CurrencyService::class)->code());
    }

    // -----------------------------------------------------------------------
    // 5-9. PLATFORM-OWNED VS SELLER-OWNED PRODUCTS
    // -----------------------------------------------------------------------

    private function seller(?string $email = null): User
    {
        return User::factory()->create([
            'account_type' => 'seller',
            'email'        => $email,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['account_type' => 'admin']);
    }

    private function createProductAs(User $author, array $overrides = []): Product
    {
        $payload = array_merge($this->productPayload, $overrides);

        $this->actingAs($author)->post('/products', $payload)->assertRedirect(route('products'));

        return Product::where('title', $payload['title'])->firstOrFail();
    }

    /** 5. An admin-created product is PLATFORM-owned (seller_id NULL). */
    public function test_admin_created_product_is_platform_owned(): void
    {
        $product = $this->createProductAs($this->admin());

        $this->assertNull(
            $product->fresh()->seller_id,
            'An admin-created product must not be assigned to a seller.'
        );
    }

    /** 6. A seller-created product belongs to that seller. */
    public function test_seller_created_product_belongs_to_that_seller(): void
    {
        $seller = $this->seller('seller-a@example.com');

        $product = $this->createProductAs($seller);

        $this->assertSame($seller->id, $product->fresh()->seller_id);
    }

    /** 7. A seller cannot edit a platform-owned product (server-side). */
    public function test_seller_cannot_edit_a_platform_owned_product(): void
    {
        $product = $this->createProductAs($this->admin());
        $seller = $this->seller('seller-b@example.com');

        $this->actingAs($seller)
            ->get("/products/{$product->slug}/edit")
            ->assertForbidden();

        $this->actingAs($seller)
            ->post("/products/{$product->slug}/update", $this->productPayload)
            ->assertForbidden();

        // Still platform-owned afterwards: the forged POST changed nothing.
        $this->assertNull($product->fresh()->seller_id);
    }

    /** 8. A seller cannot edit or delete another seller's product. */
    public function test_seller_cannot_manage_another_sellers_product(): void
    {
        $owner = $this->seller('owner@example.com');
        $other = $this->seller('other@example.com');

        $product = $this->createProductAs($owner);

        $this->actingAs($other)
            ->get("/products/{$product->slug}/edit")
            ->assertForbidden();

        $this->actingAs($other)
            ->post("/products/{$product->slug}/delete")
            ->assertForbidden();

        $this->assertDatabaseHas('products', ['id' => $product->id]);
        $this->assertSame($owner->id, $product->fresh()->seller_id);
    }

    /** 9. An admin can still edit a platform-owned product. */
    public function test_admin_can_edit_a_platform_owned_product(): void
    {
        $product = $this->createProductAs($this->admin());
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get("/products/{$product->slug}/edit")
            ->assertOk()
            ->assertSee('Owner:');

        $this->actingAs($admin)
            ->post("/products/{$product->slug}/update", array_merge($this->productPayload, [
                'title' => 'Smart Watch Pro 2',
                'price' => '2999.00',
            ]))
            ->assertRedirect(route('products'));

        $product->refresh();
        $this->assertSame('Smart Watch Pro 2', $product->title);

        // Editing unrelated fields must not change ownership.
        $this->assertNull($product->seller_id);
    }

    /** A platform product is labelled "Sold by KDP MART", never a seller. */
    public function test_platform_product_displays_kdp_mart_as_the_source(): void
    {
        $product = $this->createProductAs($this->admin());

        $this->get("/products/{$product->slug}")
            ->assertOk()
            ->assertSee('Sold by')
            ->assertSee('KDP MART')
            ->assertDontSee('Seller: Unknown');
    }

    /** A seller-owned product shows the real seller's name as the source. */
    public function test_seller_product_displays_the_seller_as_the_source(): void
    {
        $seller = $this->seller('named-seller@example.com');
        $seller->update(['name' => 'Bright Bazaar']);

        $product = $this->createProductAs($seller);

        $this->get("/products/{$product->slug}")
            ->assertOk()
            ->assertSee('Sold by')
            ->assertSee('Bright Bazaar');
    }

    /** A seller browsing a platform product is a shopper, not a manager. */
    public function test_seller_sees_shopping_actions_not_edit_for_a_platform_product(): void
    {
        $product = $this->createProductAs($this->admin());
        $seller = $this->seller('shopper@example.com');

        $response = $this->actingAs($seller)->get("/products/{$product->slug}")->assertOk();

        $response->assertSee('Add to cart')->assertSee('Buy Now');
        $response->assertDontSee('Edit Product');
        $response->assertDontSee('Remove Product');
    }

    // -----------------------------------------------------------------------
    // 10-13. ORDER ITEMS + NOTIFICATIONS
    // -----------------------------------------------------------------------

    private function checkoutFields(): array
    {
        return [
            'address_option'  => 'new',
            'shipping_method' => 'standard',
            'payment_method'  => 'cod',
            'new_address'     => [
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

    private function placeOrderFor(User $buyer, Product ...$products): Order
    {
        $cart = [];

        foreach ($products as $product) {
            $cart[$product->slug] = [
                'product'  => $product->slug,
                'title'    => $product->title,
                'price'    => (float) $product->price,
                'quantity' => 1,
                'sku'      => $product->sku,
                'image'    => $product->image,
            ];
        }

        $this->actingAs($buyer);
        app(\App\Services\CartService::class)->save($cart);
        $this->post('/checkout', $this->checkoutFields())->assertRedirect('/checkout/complete');

        return Order::where('user_id', $buyer->id)->latest('id')->firstOrFail();
    }

    /** 10. Buying a platform product creates an order item with NO seller. */
    public function test_platform_owned_order_item_has_no_seller(): void
    {
        $buyer = User::factory()->create(['account_type' => 'buyer']);
        $product = $this->createProductAs($this->admin());

        $order = $this->placeOrderFor($buyer, $product);

        $this->assertNull(
            $order->items()->firstOrFail()->seller_id,
            'A platform-owned order item must never be attributed to a seller.'
        );
    }

    /** 11. Buying a seller product snapshots THAT seller on the item. */
    public function test_seller_owned_order_item_snapshots_the_seller(): void
    {
        $buyer = User::factory()->create(['account_type' => 'buyer']);
        $seller = $this->seller('seller-a@example.com');

        $order = $this->placeOrderFor($buyer, $this->createProductAs($seller));

        $this->assertSame($seller->id, $order->items()->firstOrFail()->seller_id);
    }

    /**
     * A mixed order keeps all three ownership relationships: Seller A, Seller B
     * and the platform. The platform line is never forced onto a seller.
     */
    public function test_mixed_order_preserves_every_owner_including_the_platform(): void
    {
        $buyer = User::factory()->create(['account_type' => 'buyer']);
        $sellerA = $this->seller('a@example.com');
        $sellerB = $this->seller('b@example.com');

        $order = $this->placeOrderFor(
            $buyer,
            $this->createProductAs($sellerA, ['title' => 'Seller A Item']),
            $this->createProductAs($sellerB, ['title' => 'Seller B Item']),
            $this->createProductAs($this->admin(), ['title' => 'Platform Item']),
        );

        $this->assertSame(3, $order->items()->count());
        $this->assertSame(1, $order->items()->where('seller_id', $sellerA->id)->count());
        $this->assertSame(1, $order->items()->where('seller_id', $sellerB->id)->count());
        $this->assertSame(1, $order->items()->whereNull('seller_id')->count());
    }

    /** 12. Only the seller who actually owns a line is notified. */
    public function test_only_owning_seller_is_notified_for_a_seller_owned_order(): void
    {
        Mail::fake();
        Notification::fake();

        $buyer = User::factory()->create(['account_type' => 'buyer']);
        $sellerA = $this->seller('a@example.com');
        $sellerB = $this->seller('b@example.com');

        $order = $this->placeOrderFor($buyer, $this->createProductAs($sellerA));

        app(OrderWorkflowService::class)->approve($order->refresh(), $this->admin());

        Mail::assertSent(SellerOrderApprovedMail::class, fn ($mail) => $mail->hasTo($sellerA->email));

        // Seller B owns nothing here: no mail and no in-app notification.
        Mail::assertNotSent(SellerOrderApprovedMail::class, fn ($mail) => $mail->hasTo($sellerB->email));
        $this->assertSame(0, $sellerB->fresh()->notifications()->count());

        // Buyer notifications keep working.
        Mail::assertSent(OrderApprovedMail::class, fn ($mail) => $mail->hasTo($buyer->email));
    }

    /**
     * 13. A platform-owned order notifies NO seller - never a random one - while
     * the buyer is still notified.
     */
    public function test_platform_owned_order_notifies_no_seller_but_still_notifies_the_buyer(): void
    {
        Mail::fake();
        Notification::fake();

        $buyer = User::factory()->create(['account_type' => 'buyer']);
        $unrelatedSeller = $this->seller('unrelated@example.com');

        $order = $this->placeOrderFor($buyer, $this->createProductAs($this->admin()));

        app(OrderWorkflowService::class)->approve($order->refresh(), $this->admin());

        // The platform line belongs to no seller: nobody is emailed.
        Mail::assertNotSent(SellerOrderApprovedMail::class);
        $this->assertSame(0, $unrelatedSeller->fresh()->notifications()->count());

        Mail::assertSent(OrderApprovedMail::class, fn ($mail) => $mail->hasTo($buyer->email));
        $this->assertSame('confirmed', $order->fresh()->status);
    }

    /**
     * A mixed order notifies BOTH owning sellers, and the platform line stays
     * attributed to nobody.
     */
    public function test_mixed_order_notifies_each_owning_seller_for_their_own_line(): void
    {
        Mail::fake();
        Notification::fake();

        $buyer = User::factory()->create(['account_type' => 'buyer']);
        $sellerA = $this->seller('a@example.com');
        $sellerB = $this->seller('b@example.com');

        $order = $this->placeOrderFor(
            $buyer,
            $this->createProductAs($sellerA, ['title' => 'Seller A Item']),
            $this->createProductAs($sellerB, ['title' => 'Seller B Item']),
            $this->createProductAs($this->admin(), ['title' => 'Platform Item']),
        );

        app(OrderWorkflowService::class)->approve($order->refresh(), $this->admin());

        Mail::assertSent(SellerOrderApprovedMail::class, fn ($mail) => $mail->hasTo($sellerA->email));
        Mail::assertSent(SellerOrderApprovedMail::class, fn ($mail) => $mail->hasTo($sellerB->email));

        $this->assertSame(1, $order->items()->whereNull('seller_id')->count());
    }
}
