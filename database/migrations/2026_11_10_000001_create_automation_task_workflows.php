<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('automation_tasks')) {
            Schema::create('automation_tasks', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('business_id')->nullable()->index();
                $table->string('title', 180);
                $table->text('description')->nullable();
                $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('priority', 20)->default('normal');
                $table->string('status', 20)->default('OPEN')->index();
                $table->dateTime('due_at')->nullable()->index();
                $table->dateTime('completed_at')->nullable();
                $table->foreignId('recurring_template_id')->nullable()->index();
                $table->dateTime('reminder_sent_at')->nullable();
                $table->timestamps();
                $table->index(['business_id', 'status', 'due_at'], 'automation_tasks_business_status_due_idx');
            });
        }

        if (! Schema::hasTable('automation_recurring_tasks')) {
            Schema::create('automation_recurring_tasks', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('business_id')->nullable()->index();
                $table->string('title', 180);
                $table->text('description')->nullable();
                $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('priority', 20)->default('normal');
                $table->string('frequency', 20);
                $table->unsignedSmallInteger('interval_value')->default(1);
                $table->dateTime('next_due_at');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->index(['is_active', 'next_due_at'], 'automation_recurring_active_due_idx');
            });
        }

        if (! Schema::hasTable('automation_task_reminders')) {
            Schema::create('automation_task_reminders', function (Blueprint $table) {
                $table->id();
                $table->foreignId('task_id')->constrained('automation_tasks')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('reminder_type', 30)->default('due');
                $table->dateTime('reminded_at');
                $table->timestamps();
                $table->unique(['task_id', 'reminder_type'], 'automation_task_reminder_once_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_task_reminders');
        Schema::dropIfExists('automation_tasks');
        Schema::dropIfExists('automation_recurring_tasks');
    }
};
