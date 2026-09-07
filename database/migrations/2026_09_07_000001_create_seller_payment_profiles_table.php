<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Seller payment profiles: the UPI / QR / bank details a seller
     * configures so buyers can pay for the seller's products. One profile
     * per seller (unique seller_id). Bank fields are optional; the account
     * number is stored encrypted (see SellerPaymentProfile::$casts) and is
     * never shown in full outside the seller's own settings page.
     *
     * Additive migration: no existing tables are touched.
     */
    public function up(): void
    {
        Schema::create('seller_payment_profiles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('seller_id')
                ->unique()
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('upi_id')->nullable();
            $table->string('mobile_number')->nullable();
            $table->string('qr_code_path')->nullable();

            // Optional bank details (private — never rendered for buyers).
            $table->string('account_holder_name')->nullable();
            $table->string('bank_name')->nullable();
            $table->text('account_number')->nullable();
            $table->string('ifsc_code')->nullable();

            // A seller can pause their payment profile (e.g. on holiday)
            // without deleting the configured details.
            $table->boolean('is_active')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seller_payment_profiles');
    }
};
