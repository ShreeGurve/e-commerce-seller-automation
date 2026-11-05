<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('business_automation_events')) return;
        Schema::table('business_automation_events', function (Blueprint $table) {
            if (!Schema::hasColumn('business_automation_events','assigned_to')) {
                $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('business_automation_events','assigned_at')) $table->dateTime('assigned_at')->nullable();
            if (!Schema::hasColumn('business_automation_events','due_at')) $table->dateTime('due_at')->nullable();
            if (!Schema::hasColumn('business_automation_events','priority')) $table->string('priority',20)->default('normal');
        });
        Schema::table('business_automation_events', function (Blueprint $table) {
            $table->index(['status','due_at'],'bae_status_due_idx');
            $table->index(['assigned_to','status'],'bae_assignee_status_idx');
        });
    }
    public function down(): void {
        if (!Schema::hasTable('business_automation_events')) return;
        Schema::table('business_automation_events', function (Blueprint $table) {
            if (Schema::hasColumn('business_automation_events','assigned_to')) $table->dropForeign(['assigned_to']);
            foreach (['assigned_to','assigned_at','due_at','priority'] as $column) {
                if (Schema::hasColumn('business_automation_events',$column)) $table->dropColumn($column);
            }
        });
    }
};
