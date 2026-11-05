<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  if(!Schema::hasTable('warehouse_locations')) Schema::create('warehouse_locations',function(Blueprint $t){
   $t->id();$t->string('code',40)->unique();$t->string('name',150);$t->string('location_type',30)->default('WAREHOUSE');
   $t->string('address',500)->nullable();$t->string('contact_person',120)->nullable();$t->string('contact_phone',40)->nullable();
   $t->boolean('is_active')->default(true);$t->text('notes')->nullable();$t->timestamps();
  });
  if(!Schema::hasTable('warehouse_stock_balances')) Schema::create('warehouse_stock_balances',function(Blueprint $t){
   $t->id();$t->unsignedBigInteger('warehouse_location_id');$t->string('sku',100);$t->string('item_name',180);
   $t->decimal('quantity',14,3)->default(0);$t->string('unit',30)->default('pcs');$t->timestamps();
   $t->foreign('warehouse_location_id','wsb_location_fk')->references('id')->on('warehouse_locations')->cascadeOnDelete();
   $t->unique(['warehouse_location_id','sku'],'wsb_location_sku_uq');
  });
 }
 public function down():void{Schema::dropIfExists('warehouse_stock_balances');Schema::dropIfExists('warehouse_locations');}
};
