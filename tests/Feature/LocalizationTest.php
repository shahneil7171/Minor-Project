<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Setting;
use App\Models\User;
use App\Services\CartService;
use App\Services\CurrencyService;
use App\Services\PreferenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * PHASE 3 — multi-currency and multi-language.
 *
 * These tests pin the four invariants the rest of the feature depends on:
 *   1. only whitelisted codes are ever accepted (input cannot inject one),
 *   2. a chosen currency/locale reaches the session and the account,
 *   3. orders snapshot currency + rate, and history never re-prices itself,
 *   4. money is CONVERTED at render time only — stored amounts stay in base.
 */
class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::disk('local')->delete('custom_products_test.json');
    }

    private function buyer(): User
    {
        return User::factory()->create(['account_type' => 'buyer']);
    }

    private function admin(): User
    {
        return User::factory()->create(['account_type' => 'admin']);
    }

    private function placeOrder(User $user)
    {
        $this->actingAs($user);

        app(CartService::class)->save([
            'smart-watch-pro' => [
                'product'  => 'smart-watch-pro',
                'title'    => 'Smart Watch Pro',
                'price'    => 199.0,
                'quantity' => 1,
                'sku'      => 'KDP-SMW-001',
                'image'    => 'https://images.unsplash.com/photo-1518444209757-9ae0b9eb3734?auto=format&fit=crop&w=800&q=80',
            ],
        ]);

        return $this->post('/checkout', [
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
        ]);
    }

    public function test_currency_switcher_accepts_a_supported_code_and_converts_display(): void
    {
        // Config default: INR anchor -> 100 INR displays as ₹100.00, then as $1.20.
        $this->assertSame('INR', app(CurrencyService::class)->code());
        $this->assertSame('₹100.00', app(CurrencyService::class)->format(100));

        $this->post('/preferences/currency', ['currency' => 'USD'])
            ->assertSessionHas(config('currency.session_key'), 'USD');

        $this->assertSame('USD', app(CurrencyService::class)->code());
        $this->assertSame('$1.20', app(CurrencyService::class)->format(100));

        // The switcher itself is rendered on every page, guests included.
        $this->get('/')->assertOk()->assertSee('name="currency"', false);
    }

    public function test_currency_switcher_rejects_a_code_that_is_not_on_the_whitelist(): void
    {
        $response = $this->post('/preferences/currency', ['currency' => 'XYZ']);

        $response->assertSessionHasErrors('currency');
        $response->assertSessionMissing(config('currency.session_key'));
        $this->assertSame('INR', app(CurrencyService::class)->code());
    }

    public function test_locale_switcher_applies_the_translation_across_the_page(): void
    {
        $this->get('/')->assertOk()->assertSee('<html lang="en"', false);

        $this->post('/preferences/locale', ['locale' => 'hi'])
            ->assertSessionHas(config('locale.session_key'), 'hi');

        $page = $this->get('/');
        $page->assertOk();
        $page->assertSee('<html lang="hi"', false);
        $page->assertSee('dir="ltr"', false);

        $this->assertSame('hi', app()->getLocale());
        // The switcher offers every configured language, not just the active one.
        $page->assertSee('ગુજરાતી');
    }

    public function test_locale_switcher_rejects_a_code_that_is_not_on_the_whitelist(): void
    {
        // A hand-crafted value must never make the framework load an
        // arbitrary translation file.
        $response = $this->post('/preferences/locale', ['locale' => '../../etc/passwd']);

        $response->assertSessionHasErrors('locale');
        $response->assertSessionMissing(config('locale.session_key'));
        $this->assertSame('en', app()->getLocale());
    }

    public function test_a_signed_in_shopper_keeps_their_choice_across_requests(): void
    {
        $user = $this->buyer();

        $this->actingAs($user)
            ->post('/preferences/currency', ['currency' => 'EUR'])
            ->assertSessionHas('kdp.currency', 'EUR');

        $this->assertSame('EUR', $user->fresh()->getAttribute('preferred_currency'));

        // The account value (not just the session) must drive the next request,
        // so the memoized singleton is dropped first to simulate a new one.
        app()->forgetInstance(PreferenceService::class);
        app()->forgetInstance(CurrencyService::class);

        $reloaded = $user->fresh();
        $this->actingAs($reloaded)->get('/')->assertOk();
        $this->assertSame('EUR', app(PreferenceService::class)->currency($reloaded));
    }

    public function test_an_order_snapshots_its_currency_and_history_never_re_prices(): void
    {
        $user = $this->buyer();

        $this->actingAs($user);
        $this->post('/preferences/currency', ['currency' => 'USD']);

        $this->placeOrder($user)->assertRedirect();

        $order = Order::query()->latest('id')->firstOrFail();

        $this->assertSame('USD', $order->currency_code);
        $this->assertEqualsWithDelta(0.012, (float) $order->currency_rate, 0.000001);

        $asPaid = $order->money($order->total);
        $this->assertStringStartsWith('$', $asPaid);
        $this->assertSame(
            app(CurrencyService::class)->formatWith($order->total, 'USD', 0.012),
            $asPaid
        );

        // An admin now changes the exchange rate...
        Setting::put('rate_usd', '0.9');
        app(CurrencyService::class)->flush();

        $this->assertEqualsWithDelta(0.9, app(CurrencyService::class)->rate('USD'), 0.000001);
        $this->assertSame($asPaid, $order->fresh()->money($order->total));

        // ...and then the store's own base currency. Neither can re-price a
        // historical order: it keeps the code + rate it snapshotted.
        Setting::put('currency', 'USD');
        app(CurrencyService::class)->flush();

        $this->assertSame($asPaid, $order->fresh()->money($order->total));
        $this->assertSame('USD', app(CurrencyService::class)->base());
    }

    public function test_order_money_stays_in_the_base_currency_so_reports_do_not_drift(): void
    {
        $user = $this->buyer();

        $this->actingAs($user);
        $this->post('/preferences/currency', ['currency' => 'GBP']);
        $this->placeOrder($user)->assertRedirect();

        $order = Order::query()->latest('id')->firstOrFail();

        // Stored totals are untouched base amounts: the components still add up
        // to the total in the units the catalogue priced in.
        $this->assertEqualsWithDelta(
            (float) $order->subtotal
                + (float) $order->shipping_cost
                + (float) $order->tax
                - (float) $order->discount_amount,
            (float) $order->total,
            0.01
        );

        // Displaying it in another currency is a render-time operation only —
        // the stored figure is never rewritten to the converted one.
        $this->assertSame('£', app(CurrencyService::class)->symbol());
        $this->assertGreaterThan(
            app(CurrencyService::class)->convert($order->total, 'GBP'),
            (float) $order->total
        );
    }

    public function test_admin_settings_reject_a_locale_that_is_not_configured(): void
    {
        $this->actingAs($this->admin())->put('/admin/settings', [
            'store_name'     => 'KDP MART HQ',
            'store_email'    => 'hq@kdpmart.test',
            'store_phone'    => '+91 99999 11111',
            'currency'       => 'INR',
            'default_locale' => 'xx',
        ])->assertSessionHasErrors('default_locale');

        $this->assertSame('en', Setting::get('default_locale'));
    }

    public function test_admin_can_choose_the_default_language_and_narrow_offered_lists(): void
    {
        $this->actingAs($this->admin())->put('/admin/settings', [
            'store_name'  => 'KDP MART HQ',
            'store_email' => 'hq@kdpmart.test',
            'store_phone' => '+91 99999 11111',
            'currency'    => 'INR',

            'default_locale'       => 'gu',
            'supported_locales'    => 'en,gu,not-a-locale',
            'supported_currencies' => 'INR,FAKE,USD',
        ])->assertSessionHas('success');

        $this->assertSame('gu', Setting::get('default_locale'));

        // Unknown codes are dropped by the whitelist, never stored.
        $this->assertSame('en,gu', Setting::get('supported_locales'));
        $this->assertSame('INR,USD', Setting::get('supported_currencies'));

        $prefs = app(PreferenceService::class);
        $this->assertSame(['en', 'gu'], $prefs->supportedLocales());
        $this->assertSame(['INR', 'USD'], $prefs->supportedCurrencies());
    }

    public function test_admin_can_override_an_exchange_rate_but_never_a_negative_one(): void
    {
        $admin = $this->admin();

        $this->assertEqualsWithDelta(0.012, app(CurrencyService::class)->rate('USD'), 0.000001);

        $this->actingAs($admin)->put('/admin/settings', [
            'store_name'  => 'KDP MART HQ',
            'store_email' => 'hq@kdpmart.test',
            'store_phone' => '+91 99999 11111',
            'currency'    => 'INR',
            'rate_usd'    => '0.05',
        ])->assertSessionHas('success');

        $this->assertSame('0.05', Setting::get('rate_usd'));
        $this->assertEqualsWithDelta(0.05, app(CurrencyService::class)->rate('USD'), 0.000001);

        // A negative or non-numeric rate is ignored rather than trusted.
        $this->actingAs($admin)->put('/admin/settings', [
            'store_name'  => 'KDP MART HQ',
            'store_email' => 'hq@kdpmart.test',
            'store_phone' => '+91 99999 11111',
            'currency'    => 'INR',
            'rate_usd'    => '-3',
        ])->assertSessionHas('success');

        $this->assertSame('0.05', Setting::get('rate_usd'));
    }

    public function test_resetting_display_preferences_returns_to_store_defaults(): void
    {
        $this->post('/preferences/currency', ['currency' => 'GBP']);
        $this->post('/preferences/locale', ['locale' => 'gu']);

        $this->assertSame('GBP', app(CurrencyService::class)->code());
        $this->assertSame('gu', app()->getLocale());

        $this->post('/preferences/reset');

        $this->assertSame('INR', app(CurrencyService::class)->code());
        $this->assertSame('en', app()->getLocale());
    }
}
