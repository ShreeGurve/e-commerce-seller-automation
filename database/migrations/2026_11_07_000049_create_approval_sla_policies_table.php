<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('approval_sla_policies')) Schema::create('approval_sla_policies', function(Blueprint $table) {
            $table->id();
            $table->string('request_type',60)->unique();
            $table->unsignedSmallInteger('sla_hours')->default(48);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('approval_sla_policies'); }
};
