<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('automation_tasks')) {
            Schema::table('automation_tasks', function (Blueprint $table) {
                if (!Schema::hasColumn('automation_tasks', 'escalated_at')) $table->dateTime('escalated_at')->nullable()->index();
            });
        }
        if (!Schema::hasTable('automation_task_escalations')) {
            Schema::create('automation_task_escalations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('task_id')->constrained('automation_tasks')->cascadeOnDelete();
                $table->foreignId('from_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('to_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('escalated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('reason', 500)->nullable();
                $table->dateTime('created_at')->useCurrent();
                $table->index(['task_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_task_escalations');
        if (Schema::hasTable('automation_tasks') && Schema::hasColumn('automation_tasks', 'escalated_at')) {
            Schema::table('automation_tasks', function (Blueprint $table) { $table->dropColumn('escalated_at'); });
        }
    }
};
