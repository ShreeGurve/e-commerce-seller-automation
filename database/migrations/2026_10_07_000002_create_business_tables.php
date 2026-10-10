<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('businesses', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('legal_name')->nullable();
            $t->string('gstin', 20)->nullable();
            $t->string('currency', 3)->default('INR');
            $t->string('timezone', 50)->default('Asia/Kolkata');
            $t->timestamps();
            $t->softDeletes();
        });

        Schema::table('users', function (Blueprint $t) {
            $t->foreign('business_id')->references('id')->on('businesses')->nullOnDelete();
        });

        Schema::create('brands', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->constrained()->restrictOnDelete();
            $t->string('name');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['business_id', 'name']);
        });

        Schema::create('business_settings', function (Blueprint $t) {
            $t->id();
            $t->foreignId('business_id')->constrained()->cascadeOnDelete();
            $t->string('group', 40)->default('general')->index();
            $t->string('key', 80);
            $t->text('value')->nullable();
            $t->string('type', 20)->default('string'); // string|int|decimal|bool|json
            $t->timestamps();
            $t->unique(['business_id', 'key']);
        });

        Schema::create('marketplaces', function (Blueprint $t) {
            $t->id();
            $t->string('code', 30)->unique();          // amazon, flipkart ...
            $t->string('name', 80);
            $t->string('adapter_class')->nullable();   // resolved by MarketplaceManager later
            $t->json('config')->nullable();            // NON-secret config only; secrets live in .env
            $t->boolean('is_active')->default(true);
            $t->unsignedSmallInteger('sort_order')->default(0);
            $t->timestamps();
        });

        Schema::create('categories', function (Blueprint $t) {
            $t->id();
            $t->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $t->string('name');
            $t->string('hsn', 20)->nullable();
            $t->decimal('gst_rate', 5, 2)->nullable();
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->softDeletes();
            $t->unique(['parent_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
        Schema::dropIfExists('marketplaces');
        Schema::dropIfExists('business_settings');
        Schema::dropIfExists('brands');
        Schema::table('users', fn (Blueprint $t) => $t->dropForeign(['business_id']));
        Schema::dropIfExists('businesses');
    }
};
