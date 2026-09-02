<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The delivery layer: one normalized row per assigned order shipment.
     * A unique index on order_id guarantees a single active delivery record
     * per order — reassignment UPDATES the row (partner + timestamps) instead
     * of creating duplicates.
     */
    public function up(): void
    {
        Schema::create('order_deliveries', function (Blueprint $table) {
            $table->id();

            // The order being delivered. One delivery record per order.
            $table->foreignId('order_id')
                ->unique()
                ->constrained('orders')
                ->cascadeOnDelete();

            // The delivery partner responsible for this delivery. Nullable +
            // nullOnDelete so deleting a partner account never destroys the
            // delivery history (admin can reassign from the deliveries page).
            $table->foreignId('delivery_partner_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // The admin who made the assignment.
            $table->foreignId('assigned_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Delivery lifecycle: assigned -> ready_for_pickup -> picked_up ->
            // out_for_delivery -> delivered (failed from any active state).
            $table->enum('status', [
                'assigned',
                'ready_for_pickup',
                'picked_up',
                'out_for_delivery',
                'delivered',
                'failed',
            ])->default('assigned')->index();

            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('out_for_delivery_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();

            $table->text('delivery_notes')->nullable();

            $table->timestamps();

            // Partner dashboards filter by their own active deliveries.
            $table->index(['delivery_partner_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_deliveries');
    }
};
