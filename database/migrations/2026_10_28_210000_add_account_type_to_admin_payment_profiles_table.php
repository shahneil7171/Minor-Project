<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the missing `account_type` column to the admin payment profiles table.
     *
     * The original create migration was applied before `account_type` was added
     * to it, so the column is absent from existing databases. This additive
     * migration brings the schema in line without touching any data.
     */
    public function up(): void
    {
        if (Schema::hasTable('admin_payment_profiles') && ! Schema::hasColumn('admin_payment_profiles', 'account_type')) {
            Schema::table('admin_payment_profiles', function (Blueprint $table) {
                $table->string('account_type')->nullable()->after('ifsc_code');
            });
        }
    }

    /**
     * Reverse the addition (non-destructive: drops only the added column).
     */
    public function down(): void
    {
        if (Schema::hasTable('admin_payment_profiles') && Schema::hasColumn('admin_payment_profiles', 'account_type')) {
            Schema::table('admin_payment_profiles', function (Blueprint $table) {
                $table->dropColumn('account_type');
            });
        }
    }
};
