<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PHASE 2 completion delta — purely additive, preserves all existing data.
 *
 * - Adds `pickup_assigned_at` as the spec-named alias of the existing
 *   `pickup_scheduled_at` (both columns are maintained together so old and
 *   new code read the same moment).
 * - Adds `return_status_histories`: one row per REAL return status change,
 *   written exclusively by App\Services\ReturnService (mirrors the Phase 1
 *   order_status_histories audit trail; return history stays separate from
 *   order history).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('returns', function (Blueprint $table) {
            if (! Schema::hasColumn('returns', 'pickup_assigned_at')) {
                $table->timestamp('pickup_assigned_at')->nullable()->after('pickup_scheduled_at');
            }
        });

        // Backfill the alias so existing scheduled pickups read identically.
        try {
            DB::table('returns')
                ->whereNull('pickup_assigned_at')
                ->whereNotNull('pickup_scheduled_at')
                ->update(['pickup_assigned_at' => DB::raw('pickup_scheduled_at')]);
        } catch (Throwable $e) {
            // Table may not exist on a fresh install before earlier migrations.
        }

        if (! Schema::hasTable('return_status_histories')) {
            Schema::create('return_status_histories', function (Blueprint $table) {
                $table->id();
                $table->foreignId('return_request_id')
                    ->constrained('returns')
                    ->cascadeOnDelete();
                $table->string('from_status', 30)->nullable();
                $table->string('to_status', 30);
                $table->foreignId('changed_by')->nullable()
                    ->constrained('users')->nullOnDelete();
                $table->text('note')->nullable();
                $table->timestamps();
                $table->index(['return_request_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('return_status_histories');

        if (Schema::hasColumn('returns', 'pickup_assigned_at')) {
            Schema::table('returns', function (Blueprint $table) {
                $table->dropColumn('pickup_assigned_at');
            });
        }
    }
};
