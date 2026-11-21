<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_write_emergency_controls', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->unique('mwec_business_uq');
            $table->boolean('is_active')->default(false);
            $table->string('reason', 1000)->nullable();
            $table->unsignedBigInteger('activated_by')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->unsignedBigInteger('released_by')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_write_emergency_controls');
    }
};
