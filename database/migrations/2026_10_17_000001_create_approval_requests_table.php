
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approval_requests', function (Blueprint $table) {
            $table->id();

            // Business / tenant ownership
            $table->foreignId('business_id')
                ->nullable()
                ->constrained('businesses')
                ->restrictOnDelete();

            // Request information
            $table->string('title', 180);
            $table->string('type', 60)->nullable()->index();
            $table->string('request_type', 60)
                ->default('other')
                ->index();

            $table->string('reference', 100)->nullable()->index();
            $table->decimal('amount', 15, 2)->nullable();
            $table->text('description')->nullable();

            // Optional relation to a product, order, expense, etc.
            $table->nullableMorphs('entity');

            // Proposed changes for manual or automated approval
            $table->json('current_value')->nullable();
            $table->json('proposed_value')->nullable();

            $table->string('source', 20)->default('USER');

            // Existing workflow plus automation execution states
            $table->string('status', 20)
                ->default('PENDING')
                ->index();

            // Request creator and reviewer
            $table->foreignId('requested_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('reviewed_at')->nullable();

            // Keep both while migrating existing application code
            $table->text('review_note')->nullable();
            $table->text('decision_note')->nullable();

            // Execution tracking
            $table->timestamp('executed_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['business_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approval_requests');
    }
};
