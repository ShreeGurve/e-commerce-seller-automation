<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplace_write_alert_preferences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('business_id')->index();
            $table->unsignedBigInteger('user_id')->unique();
            $table->boolean('attempt_alerts')->default(true);
            $table->boolean('incident_alerts')->default(true);
            $table->boolean('critical_alerts')->default(true);
            $table->boolean('high_alerts')->default(true);
            $table->boolean('warning_alerts')->default(true);
            $table->unsignedSmallInteger('stale_after_minutes')->default(10);
            $table->timestamps();
            $table->foreign('business_id', 'mw_alert_pref_business_fk')->references('id')->on('businesses')->cascadeOnDelete();
            $table->foreign('user_id', 'mw_alert_pref_user_fk')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_write_alert_preferences');
    }
};
