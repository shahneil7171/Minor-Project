<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds Cashfree payment-gateway fields to the orders table for the
     * shared dual-gateway flow. Columns are nullable so COD, Razorpay and
     * legacy rows are unaffected; `payment_provider` is the source of truth
     * and tells us whether these fields hold the Cashfree order/payment id.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('cashfree_order_id')->nullable()->unique()->after('razorpay_payment_id');
            $table->string('cashfree_payment_id')->nullable()->after('cashfree_order_id');
            $table->index('cashfree_order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['cashfree_order_id']);
            $table->dropColumn([
                'cashfree_order_id',
                'cashfree_payment_id',
            ]);
        });
    }
};
