<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        if (!Schema::hasColumn('procurement_requisitions','awarded_quote_id')) Schema::table('procurement_requisitions', function(Blueprint $t) {
            $t->unsignedBigInteger('awarded_quote_id')->nullable();
            $t->unsignedBigInteger('awarded_by')->nullable();
            $t->dateTime('awarded_at')->nullable();
            $t->text('award_reason')->nullable();
        });
        if (!Schema::hasTable('procurement_award_reviews')) Schema::create('procurement_award_reviews', function(Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('requisition_id'); $t->unsignedBigInteger('quote_id');
            $t->unsignedBigInteger('reviewed_by')->nullable(); $t->string('decision',20);
            $t->text('reason')->nullable(); $t->dateTime('reviewed_at'); $t->timestamps();
            $t->foreign('requisition_id','par_req_fk')->references('id')->on('procurement_requisitions')->cascadeOnDelete();
            $t->foreign('quote_id','par_quote_fk')->references('id')->on('procurement_supplier_quotes')->cascadeOnDelete();
            $t->foreign('reviewed_by','par_user_fk')->references('id')->on('users')->nullOnDelete();
        });
    }
    public function down(): void {
        Schema::dropIfExists('procurement_award_reviews');
        if (Schema::hasColumn('procurement_requisitions','awarded_quote_id')) Schema::table('procurement_requisitions', function(Blueprint $t) {
            $t->dropColumn(['awarded_quote_id','awarded_by','awarded_at','award_reason']);
        });
    }
};
