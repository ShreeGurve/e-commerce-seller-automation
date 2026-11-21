<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_automation_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('recommendation_type', 40);
            $table->boolean('is_enabled')->default(true);
            $table->string('priority_override', 12)->nullable();
            $table->unsignedInteger('cooldown_hours')->default(24);
            $table->string('action_guidance', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['recommendation_type', 'is_enabled'], 'mar_rules_type_enabled_idx');
        });

        Schema::create('marketplace_automation_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_id')->constrained('marketplace_automation_rules')->cascadeOnDelete();
            $table->unsignedBigInteger('product_id')->nullable()->index();
            $table->string('recommendation_type', 40);
            $table->string('priority', 12);
            $table->string('title', 180);
            $table->text('reason');
            $table->text('suggested_action');
            $table->json('metrics')->nullable();
            $table->json('recommendation_snapshot')->nullable();
            $table->string('period_from', 10)->nullable();
            $table->string('period_to', 10)->nullable();
            $table->string('dedupe_key', 64)->unique();
            $table->string('status', 16)->default('PENDING');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
            $table->index(['status', 'priority'], 'mar_tasks_status_priority_idx');
            $table->index(['rule_id', 'product_id'], 'mar_tasks_rule_product_idx');
        });

        $now = now();
        $defaults = [
            ['Low stock replenishment review', 'RESTOCK', 'HIGH', 'Review replenishment quantity and supplier lead time.'],
            ['Stock-out risk review', 'STOCK_RISK', 'HIGH', 'Review replenishment before the estimated stock-out date.'],
            ['Sales decline investigation', 'SALES_DECLINE', 'MEDIUM', 'Review listing visibility, price competitiveness and advertising performance.'],
            ['High return-rate investigation', 'HIGH_RETURN_RATE', 'HIGH', 'Review return reasons, product quality, packaging and listing accuracy.'],
            ['Low profit margin review', 'LOW_PROFIT_MARGIN', 'MEDIUM', 'Review marketplace fees, fulfilment costs, product cost and pricing.'],
        ];
        foreach ($defaults as [$name, $type, $priority, $guidance]) {
            DB::table('marketplace_automation_rules')->insert([
                'name' => $name, 'recommendation_type' => $type, 'is_enabled' => true,
                'priority_override' => $priority, 'cooldown_hours' => 24,
                'action_guidance' => $guidance, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_automation_tasks');
        Schema::dropIfExists('marketplace_automation_rules');
    }
};
