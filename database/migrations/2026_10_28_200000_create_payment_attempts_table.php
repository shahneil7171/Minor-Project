<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Payment attempts for simulated online methods (UPI / Card). */
    public function up(): void
    {
        Schema::create('payment_attempts', function (Blueprint $table) {
            $table->id();

            // The shopper who initiated the attempt (nullable for guest checkouts).
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('cascade');

            // The order this attempt pays for.
            $table->foreignId('order_id')
                ->constrained('orders')
                ->onDelete('cascade');

            // Public, unguessable attempt identifier used in URLs (never the DB id).
            $table->string('token')->unique();
            $table->index('token');

            // 'upi' | 'card'
            $table->string('payment_method');

            // Money columns mirror the order so the attempt can be verified
            // server-side against the order total + currency.
            $table->string('currency')->default('INR');
            $table->decimal('amount', 10, 2);

            // Server-side lifecycle: pending -> success | failed | expired.
            $table->enum('status', ['pending', 'success', 'failed', 'expired'])
                ->default('pending');

            // Exactly five minutes after initiation; server time is authority.
            $table->timestamp('expires_at');
            $table->index('expires_at');

            // Populated only when the attempt is verified as successful.
            $table->timestamp('verified_at')->nullable();

            // Server-generated transaction reference (simulated in demo mode).
            $table->string('provider_reference')->nullable();

            // Human reason for failure (demo mode only).
            $table->text('failure_reason')->nullable();

            // Fast lookup of a user's / order's attempts by status.
            $table->index(['user_id', 'status']);
            $table->index(['order_id', 'status']);

            $table->timestamps();
        });
    }

    /** Do not drop the table on rollback — payment records must never be destroyed. */
    public function down(): void
    {
        Schema::dropIfExists('payment_attempts');
    }
};
