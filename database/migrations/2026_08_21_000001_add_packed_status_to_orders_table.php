<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Extends the orders.status enum to include the "packed" step so the full
     * lifecycle becomes: pending -> processing -> packed -> shipped -> delivered
     * (plus cancelled). The existing default stays "pending".
     *
     * Laravel handles both MySQL (ALTER TABLE ... MODIFY) and SQLite (native
     * table rebuild) for this column modification.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('status', [
                'pending',
                'processing',
                'packed',
                'shipped',
                'delivered',
                'cancelled',
            ])->default('pending')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('status', [
                'pending',
                'processing',
                'shipped',
                'delivered',
                'cancelled',
            ])->default('pending')->change();
        });
    }
};
