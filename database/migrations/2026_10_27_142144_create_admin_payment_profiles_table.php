<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Admin payment profile: the UPI / QR / bank details the Administrator
     * configures so buyers can pay for admin-owned (platform) products.
     * This is a single store-level profile (one row, no seller_id) — it is
     * kept entirely separate from seller_payment_profiles so one merchant's
     * details can never leak to another.
     *
     * Bank fields are optional; the account number is stored encrypted and
     * never shown in full outside the admin's own settings page.
     *
     * Additive migration: no existing tables are touched.
     */
    public function up(): void
    {
        Schema::create('admin_payment_profiles', function (Blueprint $table) {
            $table->id();

            $table->string('upi_id')->nullable();
            $table->string('mobile_number')->nullable();
            $table->string('payment_email')->nullable();
            $table->string('qr_code_path')->nullable();

            // Optional bank details (private — never rendered for buyers).
            $table->string('account_holder_name')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('branch_name')->nullable();
            $table->text('account_number')->nullable();
            $table->string('ifsc_code')->nullable();
            $table->string('account_type')->nullable();

            // The administrator can pause the store's payment profile without
            // deleting the configured details.
            $table->boolean('is_active')->default(true);

            // Manual admin-driven verification lifecycle (same labels as the
            // seller profile: not_configured / configured / verified / ...).
            $table->string('payment_status')->default('not_configured');
            $table->string('payment_method')->nullable();
            $table->text('admin_note')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('verified_at')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_payment_profiles');
    }
};
