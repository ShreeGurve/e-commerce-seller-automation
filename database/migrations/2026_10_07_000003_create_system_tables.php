<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->string('action', 80)->index();
            $t->nullableMorphs('subject');
            $t->json('old_values')->nullable();
            $t->json('new_values')->nullable();
            $t->string('ip_address', 45)->nullable();
            $t->string('user_agent')->nullable();
            $t->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('system_logs', function (Blueprint $t) {
            $t->id();
            $t->string('level', 20)->index();
            $t->text('message');
            $t->json('context')->nullable();
            $t->string('url', 500)->nullable();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $t->timestamp('created_at')->useCurrent();
        });

        // Monthly financial closing. Writes to rows dated inside a CLOSED period are blocked (enforced later by PeriodService).
        Schema::create('financial_periods', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->constrained()->restrictOnDelete();
            $t->unsignedSmallInteger('year');
            $t->unsignedTinyInteger('month');
            $t->string('status', 10)->default('OPEN')->index(); // OPEN | CLOSED
            $t->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('closed_at')->nullable();
            $t->foreignId('reopened_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('reopened_at')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->unique(['business_id', 'year', 'month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_periods');
        Schema::dropIfExists('system_logs');
        Schema::dropIfExists('activity_logs');
    }
};
