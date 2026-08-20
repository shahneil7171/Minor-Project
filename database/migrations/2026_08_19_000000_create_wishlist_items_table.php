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
        Schema::create('wishlist_items', function (Blueprint $table) {
            $table->id();

            // The user who saved the product
            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');

            // Products are JSON-backed, so identify them by their slug
            $table->string('product_slug');

            $table->timestamps();

            // A user can save the same product only once
            $table->unique(
                ['user_id', 'product_slug'],
                'wishlist_user_product_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wishlist_items');
    }
};
