<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Links products (and the order line snapshots created at checkout) to
     * the seller account that manages them, enabling seller order
     * notifications and the seller order view. Nullable: admin-created and
     * seed catalog products have no seller.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('seller_id')
                ->nullable()
                ->after('is_seed')
                ->constrained('users')
                ->nullOnDelete();

            $table->index('seller_id');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('seller_id')
                ->nullable()
                ->after('options_text')
                ->constrained('users')
                ->nullOnDelete();

            $table->index('seller_id');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('seller_id');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('seller_id');
        });
    }
};
