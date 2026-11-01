<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->unsignedInteger('max_programs')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('subscription_plans', fn (Blueprint $t) => $t->dropColumn('max_programs'));
    }
};
