<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('automation_rule_versions')) {
            Schema::create('automation_rule_versions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('automation_rule_id')->index();
                $table->unsignedInteger('version_number');
                $table->json('snapshot');
                $table->string('change_type', 30)->default('updated');
                $table->string('change_summary', 180)->nullable();
                $table->unsignedBigInteger('changed_by')->nullable();
                $table->timestamps();
                $table->unique(['automation_rule_id', 'version_number'], 'arv_rule_version_uq');
                $table->index(['automation_rule_id', 'created_at'], 'arv_rule_created_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_rule_versions');
    }
};
