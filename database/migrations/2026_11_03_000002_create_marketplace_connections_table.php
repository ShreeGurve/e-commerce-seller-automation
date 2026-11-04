<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('marketplace_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('marketplace_id')->constrained()->cascadeOnDelete();
            $table->string('account_label', 100);
            $table->longText('credentials'); // Laravel encrypted:array cast; never store plaintext secrets.
            $table->string('status', 30)->default('connected');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->text('last_verified_message')->nullable();
            $table->timestamps();
            $table->unique(['business_id', 'marketplace_id'], 'marketplace_connection_business_platform_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketplace_connections');
    }
};
