<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds the "delivery_partner" staff role to the account_type enum so the
     * application supports: buyer, seller, admin, manager, delivery_partner.
     *
     * Same additive pattern used when "manager" was introduced in
     * 2026_08_21_100003_create_system_tables.php. Existing account values
     * are untouched; delivery partners can only be created by admins.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('account_type', ['buyer', 'seller', 'admin', 'manager', 'delivery_partner'])
                ->default('buyer')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('account_type', ['buyer', 'seller', 'admin', 'manager'])
                ->default('buyer')
                ->change();
        });
    }
};
