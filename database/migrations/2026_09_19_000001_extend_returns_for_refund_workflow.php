<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Extends the existing "returns" table (created with the Sales & Marketing
 * tables) so it can carry the full return & refund workflow:
 *
 *  - links the request to the exact order line (order_item_id) so the
 *    refundable quantity can be tracked and double returns prevented;
 *  - records the seller, quantity, evidence images, description and the
 *    refund accounting (amount / status / reference);
 *  - records the return deadline (delivery date + configured window) so the
 *    7/10-day rule is auditable rather than recomputed blindly;
 *  - widens "status" from the original 4-value enum to a string so the
 *    approval -> pickup -> received -> refund workflow can be tracked.
 *
 * Purely additive: no column is dropped and no existing row is lost. The two
 * legacy statuses are migrated onto the new vocabulary
 * (requested -> pending, completed -> refunded).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('returns', function (Blueprint $table) {
            if (! Schema::hasColumn('returns', 'order_item_id')) {
                $table->foreignId('order_item_id')->nullable()->after('order_id')
                    ->constrained('order_items')->nullOnDelete();
            }
            if (! Schema::hasColumn('returns', 'product_id')) {
                $table->unsignedBigInteger('product_id')->nullable()->after('product_slug');
            }
            if (! Schema::hasColumn('returns', 'seller_id')) {
                $table->foreignId('seller_id')->nullable()->after('product_id')
                    ->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('returns', 'quantity')) {
                $table->unsignedInteger('quantity')->default(1)->after('product_title');
            }
            if (! Schema::hasColumn('returns', 'description')) {
                $table->text('description')->nullable()->after('reason');
            }
            if (! Schema::hasColumn('returns', 'images')) {
                $table->json('images')->nullable()->after('description');
            }
            if (! Schema::hasColumn('returns', 'refund_status')) {
                $table->string('refund_status', 30)->default('none')->after('status');
            }
            if (! Schema::hasColumn('returns', 'refund_amount')) {
                $table->decimal('refund_amount', 10, 2)->default(0)->after('refund_status');
            }
            if (! Schema::hasColumn('returns', 'shipping_refund_amount')) {
                $table->decimal('shipping_refund_amount', 10, 2)->default(0)->after('refund_amount');
            }
            if (! Schema::hasColumn('returns', 'refund_reference')) {
                $table->string('refund_reference')->nullable()->after('shipping_refund_amount');
            }
            if (! Schema::hasColumn('returns', 'return_deadline')) {
                $table->timestamp('return_deadline')->nullable()->after('refund_reference');
            }
            if (! Schema::hasColumn('returns', 'requested_at')) {
                $table->timestamp('requested_at')->nullable()->after('return_deadline');
            }
            if (! Schema::hasColumn('returns', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('requested_at');
            }
            if (! Schema::hasColumn('returns', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('approved_at');
            }
            if (! Schema::hasColumn('returns', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('rejected_at');
            }
            if (! Schema::hasColumn('returns', 'refunded_at')) {
                $table->timestamp('refunded_at')->nullable()->after('completed_at');
            }
            if (! Schema::hasColumn('returns', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('admin_note');
            }
            if (! Schema::hasColumn('returns', 'delivery_partner_id')) {
                $table->foreignId('delivery_partner_id')->nullable()->after('rejection_reason')
                    ->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('returns', 'assigned_by')) {
                $table->foreignId('assigned_by')->nullable()->after('delivery_partner_id')
                    ->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('returns', 'pickup_notes')) {
                $table->text('pickup_notes')->nullable()->after('assigned_by');
            }
            if (! Schema::hasColumn('returns', 'pickup_scheduled_at')) {
                $table->timestamp('pickup_scheduled_at')->nullable()->after('pickup_notes');
            }
            if (! Schema::hasColumn('returns', 'picked_up_at')) {
                $table->timestamp('picked_up_at')->nullable()->after('pickup_scheduled_at');
            }
            if (! Schema::hasColumn('returns', 'received_at')) {
                $table->timestamp('received_at')->nullable()->after('picked_up_at');
            }
            if (! Schema::hasColumn('returns', 'inspected_at')) {
                $table->timestamp('inspected_at')->nullable()->after('received_at');
            }
            if (! Schema::hasColumn('returns', 'refund_processing_at')) {
                $table->timestamp('refund_processing_at')->nullable()->after('inspected_at');
            }
        });

        // Move the legacy 4-value enum onto the workflow vocabulary before the
        // column becomes a string. Existing rows keep their meaning.
        DB::table('returns')->where('status', 'requested')->update(['status' => 'pending']);
        DB::table('returns')->where('status', 'completed')->update(['status' => 'refunded']);

        Schema::table('returns', function (Blueprint $table) {
            $table->string('status', 30)->default('pending')->change();
        });

        // Existing rows pre-date the workflow columns: backfill the request
        // timestamp and the refund status so nothing reads as "none" by
        // accident.
        DB::table('returns')->whereNull('requested_at')->update(['requested_at' => DB::raw('created_at')]);
        DB::table('returns')->where('refund_status', 'none')
            ->where('status', 'refunded')
            ->update(['refund_status' => 'refunded', 'refunded_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        DB::table('returns')->where('status', 'pending')->update(['status' => 'requested']);
        DB::table('returns')->where('status', 'refunded')->update(['status' => 'completed']);

        Schema::table('returns', function (Blueprint $table) {
            $table->enum('status', ['requested', 'approved', 'rejected', 'completed'])
                ->default('requested')->change();
        });

        Schema::table('returns', function (Blueprint $table) {
            foreach ([
                'refund_processing_at', 'inspected_at', 'received_at', 'picked_up_at',
                'pickup_scheduled_at', 'pickup_notes', 'assigned_by', 'delivery_partner_id',
                'rejection_reason', 'refunded_at', 'completed_at', 'rejected_at', 'approved_at',
                'requested_at', 'return_deadline', 'refund_reference', 'shipping_refund_amount',
                'refund_amount', 'refund_status', 'images', 'description', 'quantity',
                'seller_id', 'product_id', 'order_item_id',
            ] as $column) {
                if (Schema::hasColumn('returns', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};