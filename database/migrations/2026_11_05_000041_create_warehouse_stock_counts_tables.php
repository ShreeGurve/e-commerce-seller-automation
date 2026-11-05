<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void{
  if(!Schema::hasTable('warehouse_stock_counts'))Schema::create('warehouse_stock_counts',function(Blueprint $t){
   $t->id();$t->string('count_number',45)->unique();$t->unsignedBigInteger('warehouse_location_id');$t->date('count_date');
   $t->string('status',20)->default('DRAFT');$t->text('notes')->nullable();$t->unsignedBigInteger('created_by')->nullable();
   $t->unsignedBigInteger('finalized_by')->nullable();$t->dateTime('finalized_at')->nullable();$t->timestamps();
   $t->foreign('warehouse_location_id','wsc_location_fk')->references('id')->on('warehouse_locations')->restrictOnDelete();
   $t->foreign('created_by','wsc_creator_fk')->references('id')->on('users')->nullOnDelete();
   $t->foreign('finalized_by','wsc_final_user_fk')->references('id')->on('users')->nullOnDelete();
  });
  if(!Schema::hasTable('warehouse_stock_count_items'))Schema::create('warehouse_stock_count_items',function(Blueprint $t){
   $t->id();$t->unsignedBigInteger('warehouse_stock_count_id');$t->string('sku',100);$t->string('item_name',180);
   $t->decimal('system_quantity',14,3)->default(0);$t->decimal('counted_quantity',14,3);$t->decimal('variance_quantity',14,3)->default(0);
   $t->string('unit',30)->default('pcs');$t->text('notes')->nullable();$t->timestamps();
   $t->foreign('warehouse_stock_count_id','wsci_count_fk')->references('id')->on('warehouse_stock_counts')->cascadeOnDelete();
  });
 }
 public function down():void{Schema::dropIfExists('warehouse_stock_count_items');Schema::dropIfExists('warehouse_stock_counts');}
};
