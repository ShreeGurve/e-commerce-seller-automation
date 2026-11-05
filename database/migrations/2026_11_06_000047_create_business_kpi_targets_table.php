<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('business_kpi_targets')) Schema::create('business_kpi_targets', function(Blueprint $t) {
            $t->id(); $t->date('target_month'); $t->string('metric_key',60);
            $t->decimal('target_value',16,2); $t->string('unit',20)->default('number');
            $t->text('notes')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
            $t->unique(['target_month','metric_key'],'bkt_month_metric_unique');
            $t->index('target_month','bkt_month_idx');
        });
    }
    public function down(): void { Schema::dropIfExists('business_kpi_targets'); }
};
