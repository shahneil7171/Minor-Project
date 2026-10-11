<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('smart_wallets')) {
            Schema::create('smart_wallets', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
                $table->decimal('balance', 12, 2)->default(0);
                $table->decimal('total_earned', 12, 2)->default(0);
                $table->decimal('total_spent', 12, 2)->default(0);
                $table->decimal('total_refund_credited', 12, 2)->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('smart_coin_transactions')) {
            Schema::create('smart_coin_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('wallet_id')->constrained('smart_wallets')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('type', 40);
                $table->string('direction', 10);
                $table->decimal('amount', 12, 2);
                $table->string('reference_type')->nullable();
                $table->unsignedBigInteger('reference_id')->nullable();
                $table->string('idempotency_key')->unique();
                $table->text('description')->nullable();
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status', 20)->default('completed');
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
                $table->index(['user_id', 'created_at']);
                $table->index(['reference_type', 'reference_id']);
            });
        }

        if (! Schema::hasTable('refunds')) {
            Schema::create('refunds', function (Blueprint $table) {
                $table->id();
                $table->foreignId('return_request_id')->constrained('returns')->cascadeOnDelete();
                $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
                $table->foreignId('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->decimal('amount', 12, 2)->default(0);
                $table->string('currency_code', 10)->default('INR');
                $table->string('method', 40)->default('original_simulated');
                $table->string('provider', 40)->default('simulated');
                $table->string('status', 20)->default('pending');
                $table->string('provider_reference')->nullable();
                $table->string('idempotency_key')->unique();
                $table->text('note')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
                $table->index(['return_request_id']);
                $table->index(['status']);
            });
        }
        if (! Schema::hasTable('replacements')) {
            Schema::create('replacements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('return_request_id')->constrained('returns')->cascadeOnDelete();
                $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
                $table->foreignId('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('product_slug')->nullable();
                $table->string('variant_id', 100)->nullable();
                $table->unsignedInteger('quantity')->default(1);
                $table->decimal('price_difference', 12, 2)->default(0);
                $table->string('payment_status', 20)->default('not_required');
                $table->string('status', 30)->default('requested');
                $table->string('idempotency_key')->unique();
                $table->text('note')->nullable();
                $table->timestamp('shipped_at')->nullable();
                $table->timestamp('delivered_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('cod_refund_payouts')) {
            Schema::create('cod_refund_payouts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('refund_id')->constrained('refunds')->cascadeOnDelete();
                $table->foreignId('return_request_id')->constrained('returns')->cascadeOnDelete();
                $table->foreignId('delivery_partner_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
                $table->decimal('amount', 12, 2)->default(0);
                $table->string('status', 20)->default('assigned');
                $table->text('confirmation_evidence')->nullable();
                $table->timestamp('confirmed_at')->nullable();
                $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('note')->nullable();
                $table->timestamps();
                $table->index(['delivery_partner_id', 'status']);
            });
        }

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'coins_redeemed')) {
                $table->decimal('coins_redeemed', 12, 2)->default(0);
            }
            if (! Schema::hasColumn('orders', 'coins_refunded')) {
                $table->decimal('coins_refunded', 12, 2)->default(0);
            }
        });

        Schema::table('returns', function (Blueprint $table) {
            if (! Schema::hasColumn('returns', 'resolution')) {
                $table->string('resolution', 30)->nullable();
            }
            if (! Schema::hasColumn('returns', 'refund_method')) {
                $table->string('refund_method', 40)->nullable();
            }
            if (! Schema::hasColumn('returns', 'inspection_result')) {
                $table->string('inspection_result', 30)->nullable();
            }
            if (! Schema::hasColumn('returns', 'replacement_id')) {
                $table->unsignedBigInteger('replacement_id')->nullable();
            }
            if (! Schema::hasColumn('returns', 'product_variant_id')) {
                $table->string('product_variant_id', 100)->nullable();
            }
        });

    }

    public function down(): void
    {
        Schema::table('returns', function (Blueprint $table) {
            foreach (['replacement_id', 'inspection_result', 'refund_method', 'resolution', 'product_variant_id'] as $col) {
                if (Schema::hasColumn('returns', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
        Schema::table('orders', function (Blueprint $table) {
            foreach (['coins_refunded', 'coins_redeemed'] as $col) {
                if (Schema::hasColumn('orders', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
        Schema::dropIfExists('cod_refund_payouts');
        Schema::dropIfExists('replacements');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('smart_coin_transactions');
        Schema::dropIfExists('smart_wallets');
    }
};
