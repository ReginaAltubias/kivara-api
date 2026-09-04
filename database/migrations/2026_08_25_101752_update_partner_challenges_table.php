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
        Schema::table('partner_challenges', function (Blueprint $table) {
            $table->string('status')->default('active');
            $table->foreignUuid('winner_classroom_id')->nullable()->constrained('classrooms')->nullOnDelete();
            $table->string('school_reward_type')->nullable(); // 'equipment', 'discount', 'cash', 'other'
            $table->string('school_reward_description')->nullable();
            $table->foreignUuid('partner_id')->nullable()->constrained('partners')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('partner_challenges', function (Blueprint $table) {
            $table->dropForeign(['winner_classroom_id']);
            $table->dropForeign(['partner_id']);
            $table->dropColumn(['status', 'winner_classroom_id', 'school_reward_type', 'school_reward_description', 'partner_id']);
        });
    }
};
