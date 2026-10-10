<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('type');
            $t->morphs('notifiable');
            $t->text('data');
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
            $t->index(['notifiable_type', 'notifiable_id', 'read_at'], 'notifications_unread_lookup');
        });
        Schema::create('automation_rules', function (Blueprint $t) {
            $t->id();
            $t->string('name', 120);
            $t->string('trigger_key', 40)->index();
            $t->decimal('threshold', 15, 2)->default(0);
            $t->boolean('is_active')->default(true)->index();
            $t->timestamp('last_run_at')->nullable();
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('automation_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('automation_rule_id')->nullable()->constrained('automation_rules')->nullOnDelete();
            $t->string('fingerprint', 64)->unique();
            $t->string('severity', 20)->default('medium');
            $t->string('title', 180);
            $t->text('message');
            $t->string('target_url', 500)->nullable();
            $t->timestamp('notified_at')->nullable();
            $t->timestamps();
            $t->index(['automation_rule_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_logs');
        Schema::dropIfExists('automation_rules');
        Schema::dropIfExists('notifications');
    }
};
