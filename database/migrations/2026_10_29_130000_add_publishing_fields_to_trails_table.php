<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trails', function (Blueprint $table) {
            $table->string('grade_level', 20)->default('5-7');
            $table->boolean('is_mandatory')->default(false);
            $table->boolean('is_published')->default(false);
            $table->unsignedInteger('version')->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('trails', function (Blueprint $table) {
            $table->dropColumn(['grade_level', 'is_mandatory', 'is_published', 'version']);
        });
    }
};
