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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            // The customer who placed the order
            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');

            // Human friendly unique order reference
            $table->string('order_number')->unique();

            // Order lifecycle: pending -> processing -> shipped -> delivered
            $table->enum('status', [
                'pending',
                'processing',
                'shipped',
                'delivered',
                'cancelled',
            ])->default('pending');

            // Money (products are JSON-backed; snapshots are stored per line)
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('tax', 10, 2)->default(0);
            $table->decimal('shipping_cost', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);

            $table->string('payment_method')->nullable();

            // Shipping snapshot (so history stays intact even if profiles change)
            $table->string('shipping_name');
            $table->string('shipping_phone');
            $table->string('shipping_address');
            $table->string('shipping_city');
            $table->string('shipping_state');
            $table->string('shipping_pincode');

            $table->text('notes')->nullable();

            $table->timestamps();

            // Fast lookup for a user's order history
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
