<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Buyer saved payment methods: UPI IDs, and safe display metadata for
     * cards (brand, masked last four, expiry) — never raw card numbers or
     * CVVs. Card tokenization is NOT currently supported by the configured
     * gateways (Razorpay/Cashfree), so the `gateway_token` column is
     * nullable and reserved for future tokenization support.
     *
     * Additive migration: no existing tables are touched.
     */
    public function up(): void
    {
        Schema::create('buyer_payment_methods', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // 'upi' | 'card' | 'netbanking'
            $table->string('type', 20);

            // Display label chosen by the buyer (e.g. "My GPay", "Work card").
            $table->string('label')->nullable();

            // UPI: the buyer's UPI ID (stored as-is, validated on save).
            $table->string('upi_id')->nullable();

            // Card (safe metadata only — no raw PAN/CVV ever):
            $table->string('card_brand')->nullable();      // e.g. Visa, Mastercard
            $table->string('card_last4', 4)->nullable();   // masked last 4 digits
            $table->unsignedTinyInteger('card_expiry_month')->nullable();
            $table->unsignedSmallInteger('card_expiry_year')->nullable();

            // Gateway token/reference (nullable — tokenization not currently
            // available; reserved for future gateway support).
            $table->string('gateway_provider')->nullable();
            $table->string('gateway_token')->nullable();

            // Net banking: preferred bank code (nullable).
            $table->string('bank_code')->nullable();

            // Default flag — enforced in the controller, not at DB level, to
            // allow easy switching.
            $table->boolean('is_default')->default(false);

            $table->timestamps();

            $table->index(['user_id', 'type']);
            $table->index(['user_id', 'is_default']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('buyer_payment_methods');
    }
};
