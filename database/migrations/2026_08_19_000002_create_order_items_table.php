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
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();

            // Parent order
            $table->foreignId('order_id')
                ->constrained('orders')
                ->onDelete('cascade');

            // Products are JSON-backed and can be edited/removed over time, so
            // each order line keeps a full snapshot of what was purchased.
            $table->string('product_slug');
            $table->string('product_title');
            $table->string('product_image')->nullable();
            $table->string('sku')->nullable();

            $table->decimal('price', 10, 2);
            $table->unsignedInteger('quantity');
            $table->decimal('subtotal', 10, 2);

            // Selected variant description, e.g. "Size: M, Color: Black"
            $table->string('options_text')->nullable();

            $table->timestamps();

            $table->index('order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
