<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PHASE 3 — multi-currency + multi-language storage.
     *
     * orders:   snapshot the currency the buyer paid in (code + rate) so a
     *           historical order never re-prices itself after an admin changes
     *           exchange rates. Money columns stay in the BASE currency so
     *           every existing revenue/reporting query keeps working.
     * users:    persist a signed-in buyer's display preferences across devices.
     *
     * Both are nullable-then-defaulted so the migration is safe on existing
     * rows and reversible.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'currency_code')) {
                $table->string('currency_code', 3)->default('INR');
            }

            if (! Schema::hasColumn('orders', 'currency_rate')) {
                $table->decimal('currency_rate', 12, 6)->default(1);
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'preferred_currency')) {
                $table->string('preferred_currency', 3)->nullable();
            }

            if (! Schema::hasColumn('users', 'preferred_locale')) {
                $table->string('preferred_locale', 5)->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'currency_code')) {
                $table->dropColumn('currency_code');
            }

            if (Schema::hasColumn('orders', 'currency_rate')) {
                $table->dropColumn('currency_rate');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'preferred_currency')) {
                $table->dropColumn('preferred_currency');
            }

            if (Schema::hasColumn('users', 'preferred_locale')) {
                $table->dropColumn('preferred_locale');
            }
        });
    }
};
