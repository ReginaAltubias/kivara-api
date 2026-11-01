<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('missions', function (Blueprint $table) {
            $table->string('type', 30)->default('custom');
            $table->decimal('target_amount', 12, 2)->nullable();
            $table->unsignedInteger('kiva_points_reward')->default(0);
            $table->string('difficulty', 20)->nullable();
            $table->uuid('mission_template_id')->nullable();
            $table->uuid('lesson_id')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
        });

        Schema::create('budget_exception_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('child_profile_id');
            $table->uuid('parent_profile_id');
            $table->uuid('reward_id')->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->string('status', 20)->default('pending');
            $table->text('reason')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->uuid('resolved_by')->nullable();
            $table->timestamps();

            $table->foreign('child_profile_id')->references('id')->on('child_profiles')->cascadeOnDelete();
            $table->index(['parent_profile_id', 'status']);
        });

        Schema::create('dream_vault_comments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('dream_vault_id');
            $table->uuid('user_id');
            $table->text('text');
            $table->string('emoji', 16)->default('💬');
            $table->timestamps();

            $table->foreign('dream_vault_id')->references('id')->on('dream_vaults')->cascadeOnDelete();
            $table->index('dream_vault_id');
        });

        Schema::table('families', function (Blueprint $table) {
            $table->string('invite_code', 40)->nullable();
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->string('subscription_period', 10)->nullable();
            $table->timestamp('subscription_ends_at')->nullable();
            $table->unsignedInteger('extra_children')->default(0);
        });

        Schema::table('subscription_plans', function (Blueprint $table) {
            $table->decimal('extra_child_price', 12, 2)->default(0);
        });

        Schema::table('subscription_invoices', function (Blueprint $table) {
            $table->uuid('tier_id')->nullable();
            $table->string('currency', 3)->default('AOA');
            $table->string('billing_period', 20)->nullable();
            $table->string('description')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('payment_method', 40)->nullable();
            $table->string('payment_reference', 80)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('subscription_invoices', function (Blueprint $table) {
            $table->dropColumn(['tier_id', 'currency', 'billing_period', 'description', 'paid_at', 'payment_method', 'payment_reference']);
        });
        Schema::table('subscription_plans', fn (Blueprint $t) => $t->dropColumn('extra_child_price'));
        Schema::table('tenants', fn (Blueprint $t) => $t->dropColumn(['subscription_period', 'subscription_ends_at', 'extra_children']));
        Schema::table('families', fn (Blueprint $t) => $t->dropColumn('invite_code'));
        Schema::dropIfExists('dream_vault_comments');
        Schema::dropIfExists('budget_exception_requests');
        Schema::table('missions', function (Blueprint $table) {
            $table->dropColumn(['type', 'target_amount', 'kiva_points_reward', 'difficulty', 'mission_template_id', 'lesson_id', 'started_at', 'completed_at']);
        });
    }
};
