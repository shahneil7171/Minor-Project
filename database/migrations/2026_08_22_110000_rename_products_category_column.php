<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Renames products.category → products.category_name.
 *
 * The short column name collided with the Product::category() Eloquent
 * relationship (Eloquent returns the column, not the related model).
 * category_id is the single source of truth; this is a pure label rename
 * so the relationship can resolve correctly.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('products') && Schema::hasColumn('products', 'category') && ! Schema::hasColumn('products', 'category_name')) {
            Schema::table('products', function (Blueprint $table) {
                $table->renameColumn('category', 'category_name');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('products') && Schema::hasColumn('products', 'category_name') && ! Schema::hasColumn('products', 'category')) {
            Schema::table('products', function (Blueprint $table) {
                $table->renameColumn('category_name', 'category');
            });
        }
    }
};