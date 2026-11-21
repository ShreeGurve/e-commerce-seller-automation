<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('automation_task_dependencies')) {
            Schema::create('automation_task_dependencies', function (Blueprint $table) {
                $table->id();
                $table->foreignId('predecessor_task_id')->constrained('automation_tasks')->cascadeOnDelete();
                $table->foreignId('dependent_task_id')->constrained('automation_tasks')->cascadeOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['predecessor_task_id', 'dependent_task_id'], 'automation_task_dependency_pair_unique');
                $table->index('dependent_task_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_task_dependencies');
    }
};
