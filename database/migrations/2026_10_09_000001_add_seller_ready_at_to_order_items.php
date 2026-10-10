<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * MULTI-SELLER READINESS: an order can contain lines from several
     * sellers, but the order-level lifecycle (confirmed -> processing ->
     * ready_for_pickup -> assigned ...) has a single status column. This
     * additive, nullable stamp records the moment EACH seller finished
     * packing their own portion. The order may only advance to
     * "Ready for Pickup" once every distinct seller's lines carry this
     * stamp; until then the admin UI shows "Partially Ready".
     *
     * Nullable so existing in-flight rows keep working: a seller marking
     * ready stamps their own lines first, then the readiness check passes.
     */
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->timestamp('seller_ready_at')
                ->nullable()
                ->after('seller_id');

            $table->index('seller_ready_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex(['seller_ready_at']);
            $table->dropColumn('seller_ready_at');
        });
    }
};
