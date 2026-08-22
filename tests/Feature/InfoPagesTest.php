<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Storefront info pages (Deals / About Us / Contact) and product routing
 * consistency:
 *
 * - /deals, /about, /contact are public named routes.
 * - The contact form validates input and gives success feedback.
 * - Product detail uses the canonical /products/{slug} URL.
 * - Legacy numeric URLs like /products/2 are NOT faked: an unknown product
 *   returns a proper 404 for signed-in shoppers.
 */
class InfoPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::disk('local')->delete('custom_products_test.json');
    }

    public function test_deals_page_renders_for_guests(): void
    {
        $response = $this->get(route('deals'));

        $response->assertOk();
        $response->assertSee('Deals');
        // Seed products ship with active special prices — they must appear.
        $response->assertSee('Smart Watch Pro');
    }

    public function test_about_page_renders_for_guests(): void
    {
        $this->get(route('about'))->assertOk()->assertSee('About');
    }

    public function test_contact_page_renders_for_guests(): void
    {
        $this->get(route('contact'))->assertOk()->assertSee('Contact');
    }

    public function test_contact_form_validates_and_accepts_messages(): void
    {
        // Missing/invalid fields are rejected.
        $this->post(route('contact.submit'), [
            'name'    => '',
            'email'   => 'not-an-email',
            'message' => 'short',
        ])->assertSessionHasErrors(['name', 'email', 'message']);

        // A valid submission is accepted with success feedback.
        $response = $this->post(route('contact.submit'), [
            'name'    => 'Jane Doe',
            'email'   => 'jane@example.com',
            'subject' => 'Order question',
            'message' => 'Where is my order number 12345 please?',
        ]);

        $response->assertRedirect(route('contact'));
        $response->assertSessionHas('success');
    }

    public function test_product_detail_uses_canonical_slug_url(): void
    {
        $buyer = User::factory()->create(['account_type' => 'buyer', 'status' => 'active']);

        $this->actingAs($buyer)
            ->get(route('product.show', ['product' => 'smart-watch-pro']))
            ->assertOk()
            ->assertSee('Smart Watch Pro');
    }

    public function test_legacy_numeric_product_url_returns_404_not_a_fake_page(): void
    {
        $buyer = User::factory()->create(['account_type' => 'buyer', 'status' => 'active']);

        // /products/2 does not match any catalog slug — it must be a 404,
        // never a guessed or faked product page.
        $this->actingAs($buyer)->get('/products/2')->assertNotFound();
    }
}
