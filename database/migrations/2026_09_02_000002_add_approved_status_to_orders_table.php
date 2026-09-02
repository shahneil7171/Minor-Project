<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Extends the orders.status enum with the "approved" step (between
     * "pending" and "processing") so admins can explicitly approve a placed
     * order before it enters seller processing/delivery. Also records when
     * the approval happened. Same additive pattern as the earlier "packed"
     * status migration — existing statuses/values stay untouched.
     *
     * Laravel handles both MySQL (ALTER TABLE ... MODIFY) and SQLite (native
     * table rebuild) for this column modification.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->enum('status', [
                'pending',
                'approved',
                'processing',
                'packed',
                'shipped',
                'delivered',
                'cancelled',
            ])->default('pending')->change();
        });

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'approved_at')) {
                $table->dropColumn('approved_at');
            }
        });

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
};
