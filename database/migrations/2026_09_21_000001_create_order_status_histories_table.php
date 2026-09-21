<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Order status audit trail (Phase 1 / Step 1 — order lifecycle).
 *
 * One row per REAL status change, written exclusively by
 * App\Services\OrderStatusService. `changed_by` identifies the authenticated
 * user who caused the transition and is nullable for system-generated
 * transitions (and for the legacy backfill performed by the follow-up
 * lifecycle migration).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('order_status_histories')) {
            return;
        }

        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();

            $table->foreignId('order_id')
                ->constrained('orders')
                ->cascadeOnDelete();

            // Null for the very first recorded entry ("Order Placed").
            $table->string('from_status')->nullable();

            $table->string('to_status');

            // The actor: admin / seller / delivery partner / buyer.
            // Nullable so deleting an account never destroys the audit trail.
            $table->foreignId('changed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('note')->nullable();

            $table->timestamps();

            // The order page loads the full trail for a single order.
            $table->index(['order_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_status_histories');
    }
};
