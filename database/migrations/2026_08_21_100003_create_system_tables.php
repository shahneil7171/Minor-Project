<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * System tables: key/value store settings, staff permission groups and
     * the link between staff users and their group. Also extends the
     * account_type enum with the "manager" staff role.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('user_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->json('permissions')->default('[]');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('user_group_id')
                ->nullable()
                ->after('account_type')
                ->constrained('user_groups')
                ->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->enum('account_type', ['buyer', 'seller', 'admin', 'manager'])
                ->default('buyer')
                ->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_group_id');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->enum('account_type', ['buyer', 'seller', 'admin'])
                ->default('buyer')
                ->change();
        });

        Schema::dropIfExists('user_groups');
        Schema::dropIfExists('settings');
    }
};
