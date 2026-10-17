<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds Razorpay payment-gateway fields to the orders table so that
     * online payments can be tracked, verified, and reconciled without
     * creating a separate payment system.
     *
     * Columns are nullable so existing COD / simulated orders are unaffected.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // The payment provider used for this order ('razorpay', 'cod', null for legacy).
            $table->string('payment_provider')->nullable()->after('payment_method');

            // Razorpay-generated order ID (created server-side before checkout).
            $table->string('razorpay_order_id')->nullable()->unique()->after('payment_provider');

            // Razorpay payment ID (assigned once the customer pays).
            $table->string('razorpay_payment_id')->nullable()->after('razorpay_order_id');

            // Server-verified payment status: pending, authorized, captured, failed, refunded, partially_refunded.
            $table->string('payment_status')->nullable()->after('razorpay_payment_id');

            // The timestamp when payment was successfully verified/captured.
            $table->timestamp('payment_captured_at')->nullable()->after('payment_status');

            // Optional provider refund ID, set when a refund is initiated via Razorpay.
            $table->string('provider_refund_id')->nullable()->after('payment_captured_at');

            // Index for looking up orders by Razorpay order ID (webhook handling).
            $table->index('razorpay_order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['razorpay_order_id']);
            $table->dropColumn([
                'payment_provider',
                'razorpay_order_id',
                'razorpay_payment_id',
                'payment_status',
                'payment_captured_at',
                'provider_refund_id',
            ]);
        });
    }
};
