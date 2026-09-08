<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Extends seller_payment_profiles with the marketplace payout fields:
     * preferred payment method (UPI / bank transfer / both), a separate
     * payment contact email, branch name, account type (Savings/Current),
     * the admin-driven verification lifecycle (payment_status, admin_note,
     * rejection_reason, verified_at).
     *
     * Additive migration: no existing columns are altered or dropped.
     */
    public function up(): void
    {
        Schema::table('seller_payment_profiles', function (Blueprint $table) {
            $table->string('payment_method')->nullable()->after('ifsc_code');
            $table->string('payment_email')->nullable()->after('mobile_number');
            $table->string('branch_name')->nullable()->after('bank_name');
            $table->string('account_type')->nullable()->after('ifsc_code');

            // Admin-driven verification lifecycle. Defaults: "not_configured"
            // until the seller stores at least one payment destination.
            $table->string('payment_status', 30)->default('not_configured')->after('is_active');
            $table->text('admin_note')->nullable()->after('payment_status');
            $table->text('rejection_reason')->nullable()->after('admin_note');
            $table->timestamp('verified_at')->nullable()->after('rejection_reason');
        });

        // Backfill: an existing profile that already holds payment information
        // (UPI handle, QR file or bank account) is "configured", never "not_configured".
        DB::table('seller_payment_profiles')
            ->where('payment_status', 'not_configured')
            ->where(function ($query) {
                $query->whereNotNull('upi_id')
                    ->orWhereNotNull('qr_code_path')
                    ->orWhereNotNull('account_number');
            })
            ->update(['payment_status' => 'configured']);
    }

    public function down(): void
    {
        Schema::table('seller_payment_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'payment_method',
                'payment_email',
                'branch_name',
                'account_type',
                'payment_status',
                'admin_note',
                'rejection_reason',
                'verified_at',
            ]);
        });
    }
};