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
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();

            // The user who submitted the review
            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');

            // Products are JSON-backed, so identify them by their slug
            $table->string('product_slug');

            // Rating from 1 to 5 stars
            $table->unsignedTinyInteger('rating');

            // Review text
            $table->text('comment')->nullable();

            // Review moderation status
            $table->enum('status', [
                'pending',
                'approved',
                'rejected',
            ])->default('pending');

            $table->timestamps();

            // One review per user for each product
            $table->unique(
                ['user_id', 'product_slug'],
                'reviews_user_product_unique'
            );

            // Useful for product review queries and admin filtering
            $table->index(
                ['product_slug', 'status'],
                'reviews_product_status_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};