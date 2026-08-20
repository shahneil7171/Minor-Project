<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();

            // Unique, human-readable promo code (e.g. "WELCOME10").
            $table->string('code')->unique();

            // "fixed" = flat amount off; "percent" = percentage off.
            $table->enum('type', ['fixed', 'percent'])->default('fixed');

            // Flat amount (for "fixed") or percentage points (for "percent").
            $table->unsignedInteger('value');

            // Optional per-code usage limit.
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('used_count')->default(0);

            // Minimum order subtotal required before the code can be used.
            $table->decimal('min_order_amount', 10, 2)->nullable();

            // Optional expiry date.
            $table->date('expires_at')->nullable();

            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupons');
    }
};
