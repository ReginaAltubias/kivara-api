<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partner_programs', function (Blueprint $table) {
            $table->string('program_type', 20)->default('school');
            $table->unsignedInteger('children_target')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('partner_programs', function (Blueprint $table) {
            $table->dropColumn(['program_type', 'children_target', 'starts_at', 'ends_at']);
        });
    }
};
