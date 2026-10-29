<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * O progresso pode pertencer a uma crianca (child_profile_id) ou a um adolescente (user_id);
     * a coluna da crianca era NOT NULL, o que impedia qualquer registo de adolescentes.
     */
    public function up(): void
    {
        Schema::table('user_progress', function (Blueprint $table) {
            $table->uuid('child_profile_id')->nullable()->change();
            $table->unique(['user_id', 'learning_object_id'], 'user_progress_user_lo_unique');
        });
    }

    public function down(): void
    {
        Schema::table('user_progress', function (Blueprint $table) {
            $table->dropUnique('user_progress_user_lo_unique');
        });
    }
};
