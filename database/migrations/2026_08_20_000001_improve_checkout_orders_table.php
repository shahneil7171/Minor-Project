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
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'customer_email')) {
                $table->string('customer_email')->nullable()->after('user_id');
            }

            if (! Schema::hasColumn('orders', 'shipping_method')) {
                $table->string('shipping_method')->nullable()->after('shipping_cost');
            }

            if (! Schema::hasColumn('orders', 'shipping_country')) {
                $table->string('shipping_country')->default('India')->after('shipping_pincode');
            }
        });

        try {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
            });
        } catch (Throwable $e) {
            // Some drivers, especially SQLite during tests, rebuild foreign keys automatically.
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });

        try {
            Schema::table('orders', function (Blueprint $table) {
                $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            });
        } catch (Throwable $e) {
            // Keep the migration portable across local SQLite and MySQL setups.
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'customer_email')) {
                $table->dropColumn('customer_email');
            }

            if (Schema::hasColumn('orders', 'shipping_method')) {
                $table->dropColumn('shipping_method');
            }

            if (Schema::hasColumn('orders', 'shipping_country')) {
                $table->dropColumn('shipping_country');
            }
        });
    }
};
