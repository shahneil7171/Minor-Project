<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persistent, per-user carts.
 *
 * One cart row per user; the individual product lines live in cart_items and
 * are uniquely keyed per cart (the variant-aware "cart key" used across the
 * storefront). Carts are intentionally tied to user_id — NOT to sessions —
 * so each customer's cart survives logout and can never leak into another
 * account (including staff/admin accounts).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained()->cascadeOnDelete();
            $table->string('product_key', 191);
            $table->string('product_slug', 191)->index();
            $table->string('title');
            $table->string('image')->nullable();
            $table->decimal('price', 10, 2)->default(0);
            $table->unsignedInteger('quantity')->default(1);
            $table->json('selected_options')->nullable();
            $table->string('options_text')->nullable();
            $table->string('sku')->nullable();
            $table->string('variant_id')->nullable();
            $table->timestamps();

            $table->unique(['cart_id', 'product_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
    }
};
