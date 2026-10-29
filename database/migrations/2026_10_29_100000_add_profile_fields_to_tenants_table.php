<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('nif')->nullable()->after('name');
            $table->string('school_type')->nullable();
            $table->string('education_level')->nullable();
            $table->string('country')->nullable();
            $table->string('province')->nullable();
            $table->string('city')->nullable();
            $table->string('address')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('website')->nullable();
            $table->string('director_name')->nullable();
            $table->string('director_phone', 30)->nullable();
            $table->string('director_email')->nullable();
            $table->string('logo_url')->nullable();
            $table->json('agreement')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'nif', 'school_type', 'education_level', 'country', 'province', 'city',
                'address', 'phone', 'website', 'director_name', 'director_phone',
                'director_email', 'logo_url', 'agreement',
            ]);
        });
    }
};
