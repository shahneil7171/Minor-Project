<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds the "staff" operational role to the account_type enum so the
     * application supports: buyer, seller, admin, manager, delivery_partner,
     * staff.
     *
     * Same additive pattern used when "delivery_partner" was introduced in
     * 2026_09_02_000001_add_delivery_partner_account_type.php. Existing
     * account values are untouched and no data is migrated or removed.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('account_type', ['buyer', 'seller', 'admin', 'manager', 'delivery_partner', 'staff'])
                ->default('buyer')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('account_type', ['buyer', 'seller', 'admin', 'manager', 'delivery_partner'])
                ->default('buyer')
                ->change();
        });
    }
};
