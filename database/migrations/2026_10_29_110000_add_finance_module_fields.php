<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('type')->default('subscription');
            $table->string('currency', 3)->default('AOA');
            $table->string('plan_name')->nullable();
            $table->string('description')->nullable();
        });

        Schema::table('wallets', function (Blueprint $table) {
            $table->text('freeze_reason')->nullable();
            $table->timestamp('frozen_at')->nullable();
            $table->uuid('frozen_by')->nullable();
        });

        Schema::create('regional_prices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('plan_id');
            $table->string('currency_code', 3);
            $table->decimal('price_monthly', 12, 2)->default(0);
            $table->decimal('price_yearly', 12, 2)->default(0);
            $table->decimal('extra_child_price', 12, 2)->default(0);
            $table->timestamps();

            $table->foreign('plan_id')->references('id')->on('subscription_plans')->cascadeOnDelete();
            $table->unique(['plan_id', 'currency_code']);
        });

        $defaults = [
            ['code' => 'AOA', 'name' => 'Kwanza angolano', 'symbol' => 'Kz', 'decimal_places' => 2],
            ['code' => 'USD', 'name' => 'Dólar americano', 'symbol' => '$', 'decimal_places' => 2],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'decimal_places' => 2],
        ];
        foreach ($defaults as $currency) {
            if (!DB::table('currencies')->where('code', $currency['code'])->exists()) {
                DB::table('currencies')->insert($currency + ['is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('regional_prices');

        Schema::table('wallets', function (Blueprint $table) {
            $table->dropColumn(['freeze_reason', 'frozen_at', 'frozen_by']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn(['type', 'currency', 'plan_name', 'description']);
        });
    }
};
