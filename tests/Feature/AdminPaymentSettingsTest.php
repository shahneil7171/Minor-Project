<?php

namespace Tests\Feature;

use App\Models\AdminPaymentProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AdminPaymentSettingsTest extends TestCase
{
    use RefreshDatabase;

    private array $validPayload = [
        "upi_id" => "kdpmart@upi",
        "mobile_number" => "9876543210",
        "payment_email" => "payouts@kdpmart.test",
        "payment_method" => "both",
        "account_holder_name" => "KDP MART Store",
        "bank_name" => "State Bank of India",
        "branch_name" => "Mumbai Main",
        "account_number" => "1234567890",
        "confirm_account_number" => "1234567890",
        "ifsc_code" => "SBIN0001234",
        "account_type" => "savings",
        "is_active" => "1",
    ];

    private function admin(string $email): User
    {
        return User::factory()->create(["account_type" => "admin", "email" => $email]);
    }

    private function buyer(string $email): User
    {
        return User::factory()->create(["account_type" => "buyer", "email" => $email]);
    }

    private function fakeQrPng(int $padBytes = 0): UploadedFile
    {
        $png = base64_decode("iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==");
        if ($padBytes > 0) {
            $png .= str_repeat("0", $padBytes);
        }
        return UploadedFile::fake()->createWithContent("payment-qr.png", $png);
    }

    public function test_admin_can_view_payment_settings_page(): void
    {
        $admin = $this->admin("admin@example.com");
        $response = $this->actingAs($admin)->get(route("admin.payment-settings.index"));
        $response->assertOk();
        $response->assertSee("Admin Payment Settings");
    }

    public function test_non_admin_cannot_access_admin_payment_settings(): void
    {
        $buyer = $this->buyer("buyer@example.com");
        $response = $this->actingAs($buyer)->get(route("admin.payment-settings.index"));
        $response->assertStatus(403);
    }

    public function test_guest_cannot_access_admin_payment_settings(): void
    {
        $response = $this->get(route("admin.payment-settings.index"));
        $response->assertRedirect(route("login"));
    }

    public function test_seller_cannot_access_admin_payment_settings(): void
    {
        $seller = User::factory()->create(["account_type" => "seller", "email" => "seller@example.com"]);
        $response = $this->actingAs($seller)->get(route("admin.payment-settings.index"));
        $response->assertStatus(403);
    }



    public function test_admin_can_update_payment_settings(): void
    {
        $admin = $this->admin("admin@example.com");
        $response = $this->actingAs($admin)->put(route("admin.payment-settings.update"), $this->validPayload);
        $response->assertRedirect()->assertSessionHas("success", "Payment details saved.");
        $profile = AdminPaymentProfile::forAdmin();
        $this->assertSame("kdpmart@upi", $profile->upi_id);
        $this->assertSame("9876543210", $profile->mobile_number);
        $this->assertSame("payouts@kdpmart.test", $profile->payment_email);
        $this->assertSame("both", $profile->payment_method);
        $this->assertSame("savings", $profile->account_type);
        $this->assertSame("Mumbai Main", $profile->branch_name);
        $this->assertTrue($profile->is_active);
        $this->assertSame("configured", $profile->payment_status);
        $this->assertDatabaseMissing("admin_payment_profiles", ["account_number" => "1234567890"]);
        $this->assertNotSame("1234567890", $profile->account_number);
    }

    public function test_non_admin_cannot_update_admin_payment_settings(): void
    {
        $buyer = $this->buyer("buyer@example.com");
        $response = $this->actingAs($buyer)->put(route("admin.payment-settings.update"), $this->validPayload);
        $response->assertStatus(403);
    }

    public function test_account_confirmation_mismatch_is_rejected(): void
    {
        $admin = $this->admin("admin@example.com");
        $response = $this->actingAs($admin)->put(route("admin.payment-settings.update"), array_merge($this->validPayload, ["confirm_account_number" => "0000000000"]));
        $response->assertSessionHasErrors("confirm_account_number");
    }

    public function test_confirm_account_number_required_when_entering_new_account(): void
    {
        $admin = $this->admin("admin@example.com");
        $response = $this->actingAs($admin)->put(route("admin.payment-settings.update"), array_merge($this->validPayload, ["confirm_account_number" => ""]));
        $response->assertSessionHasErrors("confirm_account_number");
    }

    public function test_invalid_upi_id_is_rejected(): void
    {
        $admin = $this->admin("admin@example.com");
        $response = $this->actingAs($admin)->put(route("admin.payment-settings.update"), array_merge($this->validPayload, ["upi_id" => "not-a-upi-id"]));
        $response->assertSessionHasErrors("upi_id");
    }

    public function test_invalid_ifsc_is_rejected(): void
    {
        $admin = $this->admin("admin@example.com");
        $response = $this->actingAs($admin)->put(route("admin.payment-settings.update"), array_merge($this->validPayload, ["ifsc_code" => "BADIFSC"]));
        $response->assertSessionHasErrors("ifsc_code");
    }

    public function test_invalid_qr_upload_is_rejected(): void
    {
        $admin = $this->admin("admin@example.com");
        $response = $this->actingAs($admin)->put(route("admin.payment-settings.update"), array_merge($this->validPayload, ["qr_code" => UploadedFile::fake()->create("document.pdf", 100)]));
        $response->assertSessionHasErrors("qr_code");
    }

    public function test_oversized_qr_upload_is_rejected(): void
    {
        $admin = $this->admin("admin@example.com");
        $response = $this->actingAs($admin)->put(route("admin.payment-settings.update"), array_merge($this->validPayload, ["qr_code" => $this->fakeQrPng(3000 * 1024)]));
        $response->assertSessionHasErrors("qr_code");
    }


    public function test_qr_code_upload_works(): void
    {
        $admin = $this->admin("admin@example.com");
        $response = $this->actingAs($admin)->put(route("admin.payment-settings.update"), array_merge($this->validPayload, ["qr_code" => $this->fakeQrPng()]));
        $response->assertRedirect();
        $profile = AdminPaymentProfile::forAdmin()->refresh();
        $this->assertNotNull($profile->qr_code_path);
        $this->assertStringContainsString("uploads/qr-codes/", $profile->qr_code_path);
    }

    public function test_qr_code_removal_works(): void
    {
        $admin = $this->admin("admin@example.com");
        $this->actingAs($admin)->put(route("admin.payment-settings.update"), array_merge($this->validPayload, ["qr_code" => $this->fakeQrPng()]));
        $profile = AdminPaymentProfile::forAdmin()->refresh();
        $this->assertNotNull($profile->qr_code_path);
        $response = $this->actingAs($admin)->post(route("admin.payment-settings.qr.remove"));
        $response->assertRedirect()->assertSessionHas("success");
        $profile->refresh();
        $this->assertNull($profile->qr_code_path);
    }

    public function test_storing_details_does_not_automatically_verify(): void
    {
        $admin = $this->admin("admin@example.com");
        $this->actingAs($admin)->put(route("admin.payment-settings.update"), $this->validPayload);
        $profile = AdminPaymentProfile::forAdmin()->refresh();
        $this->assertSame("configured", $profile->payment_status);
        $this->assertFalse($profile->isVerified());
    }

    public function test_existing_profile_is_updated_not_duplicated(): void
    {
        $admin = $this->admin("admin@example.com");
        $this->actingAs($admin)->put(route("admin.payment-settings.update"), $this->validPayload);
        $this->actingAs($admin)->put(route("admin.payment-settings.update"), array_merge($this->validPayload, ["upi_id" => "kdpmart-new@upi"]));
        $this->assertCount(1, AdminPaymentProfile::all());
        $this->assertSame("kdpmart-new@upi", AdminPaymentProfile::forAdmin()->refresh()->upi_id);
    }
}

