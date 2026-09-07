<?php

namespace Tests\Feature;

use App\Models\SellerPaymentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * KDP MART — Seller Payment Profiles (UPI / QR / bank details).
 *
 * Financial data is sensitive: a seller can only ever read/write their OWN
 * profile (the controller always resolves it from the authenticated user),
 * bank account numbers are stored encrypted and displayed masked, and the
 * buyer-facing checkout shows only UPI/QR details — never bank details.
 * There is no payment gateway: nothing here claims a completed payment.
 */
class SellerPaymentProfileTest extends TestCase
{
    use RefreshDatabase;

    private array $validPayload = [
        'upi_id'              => 'seller-a@upi',
        'mobile_number'       => '9876543210',
        'account_holder_name' => 'Seller A',
        'bank_name'           => 'State Bank of India',
        'account_number'      => '1234567890',
        'ifsc_code'           => 'SBIN0001234',
        'is_active'           => '1',
    ];

    private function seller(string $email): User
    {
        return User::factory()->create([
            'account_type' => 'seller',
            'email' => $email,
        ]);
    }

    /**
     * A real 1x1 PNG (no GD dependency). Optional padding inflates the byte
     * size while keeping the PNG magic bytes intact.
     */
    private function fakeQrPng(int $padBytes = 0): UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');

        if ($padBytes > 0) {
            $png .= str_repeat('0', $padBytes);
        }

        return UploadedFile::fake()->createWithContent('payment-qr.png', $png);
    }

    // -----------------------------------------------------------------------
    // PROFILE CRUD
    // -----------------------------------------------------------------------

    public function test_seller_can_create_a_payment_profile(): void
    {
        $sellerA = $this->seller('seller-a@example.com');

        $response = $this->actingAs($sellerA)
            ->post(route('seller.payment-settings.update'), $this->validPayload);

        $response->assertRedirect()
            ->assertSessionHas('success', 'Payment details saved.');

        $profile = SellerPaymentProfile::where('seller_id', $sellerA->id)->firstOrFail();
        $this->assertSame('seller-a@upi', $profile->upi_id);
        $this->assertSame('9876543210', $profile->mobile_number);
        $this->assertTrue($profile->is_active);

        // The account number is stored encrypted — never in plain text.
        $this->assertDatabaseMissing('seller_payment_profiles', ['account_number' => '1234567890']);
        $this->assertSame('1234567890', $profile->account_number);
    }

    public function test_seller_can_update_their_own_payment_profile(): void
    {
        $sellerA = $this->seller('seller-a@example.com');

        $this->actingAs($sellerA)->post(route('seller.payment-settings.update'), $this->validPayload);

        $this->actingAs($sellerA)
            ->post(route('seller.payment-settings.update'), [
                'upi_id'         => 'new-handle@ybl',
                'mobile_number'  => '9876543210',
                'account_number' => '', // blank keeps the stored value
                'is_active'      => '1',
            ]);

        $profile = SellerPaymentProfile::where('seller_id', $sellerA->id)->firstOrFail();
        $this->assertSame('new-handle@ybl', $profile->upi_id);
        $this->assertSame('1234567890', $profile->account_number);
    }

    public function test_seller_cannot_update_another_sellers_payment_profile(): void
    {
        $sellerA = $this->seller('seller-a@example.com');
        $sellerB = $this->seller('seller-b@example.com');

        $this->actingAs($sellerA)->post(route('seller.payment-settings.update'), $this->validPayload);

        // Seller B posts their own settings — the request can never touch
        // Seller A's profile because the controller keys off auth()->user().
        $this->actingAs($sellerB)
            ->post(route('seller.payment-settings.update'), [
                'upi_id'    => 'seller-b@upi',
                'is_active' => '1',
            ]);

        $profileA = SellerPaymentProfile::where('seller_id', $sellerA->id)->firstOrFail();
        $this->assertSame('seller-a@upi', $profileA->upi_id);

        // Seller B's own profile was created instead — one row per seller.
        $this->assertSame(2, SellerPaymentProfile::count());
        $this->assertDatabaseHas('seller_payment_profiles', [
            'seller_id' => $sellerB->id,
            'upi_id' => 'seller-b@upi',
        ]);
    }

    public function test_seller_cannot_view_another_sellers_private_payment_profile(): void
    {
        $sellerA = $this->seller('seller-a@example.com');
        $sellerB = $this->seller('seller-b@example.com');

        $this->actingAs($sellerA)->post(route('seller.payment-settings.update'), $this->validPayload);

        $response = $this->actingAs($sellerB)->get(route('seller.payment-settings.index'));

        $response->assertOk();
        // Seller B sees only their own (empty) settings — none of A's data.
        $response->assertDontSee('seller-a@upi');
        $response->assertDontSee('State Bank of India');
        $response->assertDontSee('1234567890');
    }

    // -----------------------------------------------------------------------
    // QR CODE UPLOADS
    // -----------------------------------------------------------------------

    public function test_qr_code_upload_works(): void
    {
        $sellerA = $this->seller('seller-a@example.com');

        $response = $this->actingAs($sellerA)
            ->post(route('seller.payment-settings.update'), array_merge($this->validPayload, [
                'qr_code' => $this->fakeQrPng(),
            ]));

        $response->assertRedirect()->assertSessionHas('success');

        $profile = SellerPaymentProfile::where('seller_id', $sellerA->id)->firstOrFail();
        $this->assertNotNull($profile->qr_code_path);

        // The file was stored under the QR upload directory.
        $this->assertFileExists(public_path(ltrim($profile->qr_code_path, '/')));

        // Cleanup of the test artifact.
        @unlink(public_path(ltrim($profile->qr_code_path, '/')));
    }

    public function test_invalid_qr_upload_is_rejected(): void
    {
        $sellerA = $this->seller('seller-a@example.com');

        // A PDF masquerading as an image must be rejected (no executables,
        // no arbitrary file types) — and nothing may be stored.
        $response = $this->actingAs($sellerA)
            ->post(route('seller.payment-settings.update'), array_merge($this->validPayload, [
                'qr_code' => UploadedFile::fake()->create('evil.pdf', 100, 'application/pdf'),
            ]));

        $response->assertSessionHasErrors('qr_code');
        $this->assertNull(SellerPaymentProfile::where('seller_id', $sellerA->id)->first()?->qr_code_path);
    }

    public function test_oversized_qr_upload_is_rejected(): void
    {
        $sellerA = $this->seller('seller-a@example.com');

        $response = $this->actingAs($sellerA)
            ->post(route('seller.payment-settings.update'), array_merge($this->validPayload, [
                'qr_code' => $this->fakeQrPng(3 * 1024 * 1024), // 3 MB > 2 MB limit
            ]));

        $response->assertSessionHasErrors('qr_code');
    }

    public function test_qr_code_replacement_removes_the_old_file_only_after_success(): void
    {
        $sellerA = $this->seller('seller-a@example.com');

        $this->actingAs($sellerA)
            ->post(route('seller.payment-settings.update'), array_merge($this->validPayload, [
                'qr_code' => $this->fakeQrPng(),
            ]));

        $first = SellerPaymentProfile::where('seller_id', $sellerA->id)->firstOrFail()->qr_code_path;
        $this->assertFileExists(public_path(ltrim($first, '/')));

        $this->actingAs($sellerA)
            ->post(route('seller.payment-settings.update'), array_merge($this->validPayload, [
                'qr_code' => $this->fakeQrPng(),
            ]));

        $second = SellerPaymentProfile::where('seller_id', $sellerA->id)->firstOrFail()->qr_code_path;

        // New image stored; the superseded one is gone.
        $this->assertNotSame($first, $second);
        $this->assertFileExists(public_path(ltrim($second, '/')));
        $this->assertFileDoesNotExist(public_path(ltrim($first, '/')));

        @unlink(public_path(ltrim($second, '/')));
    }

    public function test_seller_can_remove_their_qr_code(): void
    {
        $sellerA = $this->seller('seller-a@example.com');

        $this->actingAs($sellerA)
            ->post(route('seller.payment-settings.update'), array_merge($this->validPayload, [
                'qr_code' => $this->fakeQrPng(),
            ]));

        $profile = SellerPaymentProfile::where('seller_id', $sellerA->id)->firstOrFail();
        $stored = public_path(ltrim($profile->qr_code_path, '/'));
        $this->assertFileExists($stored);

        $this->actingAs($sellerA)
            ->post(route('seller.payment-settings.qr.remove'))
            ->assertRedirect()
            ->assertSessionHas('success', 'QR code removed.');

        $profile->refresh();
        $this->assertNull($profile->qr_code_path);
        $this->assertFileDoesNotExist($stored);
    }

    // -----------------------------------------------------------------------
    // VALIDATION
    // -----------------------------------------------------------------------

    public function test_seller_upi_id_validation_works(): void
    {
        $sellerA = $this->seller('seller-a@example.com');

        $response = $this->actingAs($sellerA)
            ->post(route('seller.payment-settings.update'), array_merge($this->validPayload, ['upi_id' => 'not-a-upi-id']));

        $response->assertSessionHasErrors('upi_id');

        // A legitimate alternate PSP handle must still be accepted.
        $this->actingAs($sellerA)
            ->post(route('seller.payment-settings.update'), array_merge($this->validPayload, ['upi_id' => 'name@okaxis']))
            ->assertSessionHasNoErrors();
    }

    public function test_seller_mobile_number_validation_works(): void
    {
        $sellerA = $this->seller('seller-a@example.com');

        $this->actingAs($sellerA)
            ->post(route('seller.payment-settings.update'), array_merge($this->validPayload, ['mobile_number' => '12345']))
            ->assertSessionHasErrors('mobile_number');
    }

    public function test_bank_details_validation_works(): void
    {
        $sellerA = $this->seller('seller-a@example.com');

        // Account numbers must be 6-20 digits.
        $this->actingAs($sellerA)
            ->post(route('seller.payment-settings.update'), array_merge($this->validPayload, ['account_number' => 'abc123']))
            ->assertSessionHasErrors('account_number');

        // IFSC must follow the 4-letter + 0 + 6-alnum format.
        $this->actingAs($sellerA)
            ->post(route('seller.payment-settings.update'), array_merge($this->validPayload, ['ifsc_code' => '12SB0000']))
            ->assertSessionHasErrors('ifsc_code');

        // Valid values are accepted.
        $this->actingAs($sellerA)
            ->post(route('seller.payment-settings.update'), $this->validPayload)
            ->assertSessionHasNoErrors();
    }

    // -----------------------------------------------------------------------
    // ROLE ACCESS TO PAYMENT SETTINGS
    // -----------------------------------------------------------------------

    public function test_non_sellers_cannot_access_the_payment_settings_area(): void
    {
        foreach (['buyer', 'delivery_partner', 'staff'] as $type) {
            $user = User::factory()->create(['account_type' => $type]);

            $this->actingAs($user)
                ->get(route('seller.payment-settings.index'))
                ->assertForbidden();
        }
    }

    public function test_admin_can_view_payment_profiles_but_only_masked_account_numbers(): void
    {
        $sellerA = $this->seller('seller-a@example.com');
        $admin = User::factory()->create(['account_type' => 'admin']);

        $this->actingAs($sellerA)->post(route('seller.payment-settings.update'), $this->validPayload);

        $response = $this->actingAs($admin)->get(route('admin.seller-payments.index'));

        $response->assertOk();
        $response->assertSee('seller-a@upi');
        $response->assertSee('XXXXXX7890');       // masked form
        $response->assertDontSee('1234567890');   // never the full number
    }

    public function test_seller_sees_only_masked_account_number_on_their_own_settings_page(): void
    {
        $sellerA = $this->seller('seller-a@example.com');

        $this->actingAs($sellerA)->post(route('seller.payment-settings.update'), $this->validPayload);

        $response = $this->actingAs($sellerA)->get(route('seller.payment-settings.index'));

        $response->assertOk();
        $response->assertSee('XXXXXX7890');
        $response->assertDontSee('1234567890');
    }

    // -----------------------------------------------------------------------
    // PAYMENT INFORMATION VISIBILITY
    // -----------------------------------------------------------------------

    public function test_buyer_at_checkout_sees_only_the_permitted_payment_information(): void
    {
        $sellerA = $this->seller('seller-a@example.com');
        $buyer = User::factory()->create(['account_type' => 'buyer']);

        $this->actingAs($sellerA)->post(route('seller.payment-settings.update'), $this->validPayload);

        // A product OWNED by seller A — the payment destination is derived
        // from the product's seller (seed/unowned products have no UPI).
        $product = $this->createSellerProduct($sellerA);

        $this->actingAs($buyer);
        app(\App\Services\CartService::class)->save([
            $product->slug => [
                'product'  => $product->slug,
                'title'    => $product->title,
                'price'    => (float) $product->price,
                'quantity' => 1,
            ],
        ]);

        $response = $this->actingAs($buyer)->get(route('checkout.index'));

        $response->assertOk();
        // Permitted: the seller's UPI ID + honest pending-payment copy.
        $response->assertSee('seller-a@upi');
        $response->assertSee('Pending');
        // Never permitted: any private bank details.
        $response->assertDontSee('1234567890');
        $response->assertDontSee('XXXXXX7890');
        $response->assertDontSee('State Bank of India');
        $response->assertDontSee('SBIN0001234');
    }

    public function test_storefront_never_exposes_sensitive_bank_information(): void
    {
        $sellerA = $this->seller('seller-a@example.com');
        $product = $this->createSellerProduct($sellerA);

        $this->actingAs($sellerA)->post(route('seller.payment-settings.update'), $this->validPayload);

        foreach ([route('products'), route('product.show', ['product' => $product->slug])] as $url) {
            $response = $this->get($url);
            $response->assertOk();
            $response->assertDontSee('1234567890');
            $response->assertDontSee('XXXXXX7890');
            $response->assertDontSee('SBIN0001234');
            $response->assertDontSee('State Bank of India');
        }
    }

    private function createSellerProduct(User $owner): \App\Models\Product
    {
        $this->actingAs($owner)->post('/products', [
            'title'        => 'Pay Profile Product',
            'description'  => 'Catalog item owned by the seller.',
            'price'        => '499.00',
            'quantity'     => '3',
            'stock_status' => 'in-stock',
            'category'     => 'Electronics',
        ]);

        return \App\Models\Product::where('title', 'Pay Profile Product')->firstOrFail();
    }
}
