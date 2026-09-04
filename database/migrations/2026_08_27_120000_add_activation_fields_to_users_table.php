<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'activation_code')) {
                $table->string('activation_code', 10)->nullable()->after('remember_token');
            }
            if (!Schema::hasColumn('users', 'activation_expires_at')) {
                $table->timestamp('activation_expires_at')->nullable()->after('activation_code');
            }
            if (!Schema::hasColumn('users', 'must_change_password')) {
                $table->boolean('must_change_password')->default(false)->after('password');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $columns = [];
            if (Schema::hasColumn('users', 'activation_code')) {
                $columns[] = 'activation_code';
            }
            if (Schema::hasColumn('users', 'activation_expires_at')) {
                $columns[] = 'activation_expires_at';
            }
            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
};
