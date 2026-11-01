<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('partners', function (Blueprint $table) {
            $table->string('province', 100)->nullable();
            $table->string('website', 255)->nullable();
            $table->string('director_role', 150)->nullable();
            $table->string('director_email', 255)->nullable();
            $table->string('director_phone', 40)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('partners', fn (Blueprint $t) => $t->dropColumn(['province', 'website', 'director_role', 'director_email', 'director_phone']));
    }
};
