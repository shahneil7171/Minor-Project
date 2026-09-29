<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PHASE 4 — Admin audit log.
 *
 * One append-only row per significant administrative action (product
 * created, seller approved, order status changed, return approved, coupon
 * deleted, user role changed, ...).
 *
 * SECURITY: this table is deliberately narrow. It stores who acted, what they
 * acted on, a human-readable description, the originating IP and a timestamp.
 * It NEVER stores passwords, password-reset tokens, remember tokens, APP_KEY,
 * database credentials, bank account numbers, UPI/QR private data or any other
 * secret — App\Services\AuditLogService redacts those before writing.
 *
 * `user_name` / `user_role` are denormalised snapshots so the trail still
 * reads correctly after an account is deleted (the FK is NULL ON DELETE).
 * `target_id` is a string because the target is polymorphic (Product, Order,
 * Coupon, User, ReturnRequest, ...).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();

            // The acting user. NULL for system/CLI actions and kept NULL (not
            // cascaded) when the account is later removed.
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Snapshot of the actor at the time of the action.
            $table->string('user_name')->nullable();
            $table->string('user_role', 30)->nullable();

            // "product.created", "order.status_changed", "return.approved", ...
            $table->string('action', 80);

            // Polymorphic target: model class name (or a short label).
            $table->string('target_type', 80)->nullable();
            $table->string('target_id', 64)->nullable();

            // Safe, human-readable summary. Never raw request payloads.
            $table->text('description');

            // Optional extra context, already redacted by AuditLogService.
            $table->json('context')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();

            $table->timestamps();

            $table->index(['action', 'created_at']);
            $table->index(['target_type', 'target_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
