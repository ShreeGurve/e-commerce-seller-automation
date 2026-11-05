<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('business_activity_logs')) Schema::create('business_activity_logs', function(Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action',80);
            $table->string('subject_type',120)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('details')->nullable();
            $table->string('ip_address',45)->nullable();
            $table->timestamps();
            $table->index(['business_id','created_at'],'bal_business_created_idx');
            $table->index(['subject_type','subject_id'],'bal_subject_idx');
        });
    }
    public function down(): void { Schema::dropIfExists('business_activity_logs'); }
};
