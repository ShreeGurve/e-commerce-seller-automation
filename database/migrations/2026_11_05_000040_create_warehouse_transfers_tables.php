<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up():void{
  if(!Schema::hasTable('warehouse_transfers'))Schema::create('warehouse_transfers',function(Blueprint $t){
   $t->id();$t->string('transfer_number',45)->unique();$t->unsignedBigInteger('source_location_id');$t->unsignedBigInteger('destination_location_id');
   $t->date('transfer_date');$t->string('status',20)->default('DRAFT');$t->text('notes')->nullable();
   $t->unsignedBigInteger('created_by')->nullable();$t->unsignedBigInteger('dispatched_by')->nullable();$t->dateTime('dispatched_at')->nullable();
   $t->unsignedBigInteger('received_by')->nullable();$t->dateTime('received_at')->nullable();$t->timestamps();
   $t->foreign('source_location_id','wt_source_fk')->references('id')->on('warehouse_locations')->restrictOnDelete();
   $t->foreign('destination_location_id','wt_dest_fk')->references('id')->on('warehouse_locations')->restrictOnDelete();
   $t->foreign('created_by','wt_creator_fk')->references('id')->on('users')->nullOnDelete();
   $t->foreign('dispatched_by','wt_dispatch_user_fk')->references('id')->on('users')->nullOnDelete();
   $t->foreign('received_by','wt_receive_user_fk')->references('id')->on('users')->nullOnDelete();
  });
  if(!Schema::hasTable('warehouse_transfer_items'))Schema::create('warehouse_transfer_items',function(Blueprint $t){
   $t->id();$t->unsignedBigInteger('warehouse_transfer_id');$t->string('sku',100);$t->string('item_name',180);
   $t->decimal('quantity',14,3);$t->string('unit',30)->default('pcs');$t->timestamps();
   $t->foreign('warehouse_transfer_id','wti_transfer_fk')->references('id')->on('warehouse_transfers')->cascadeOnDelete();
  });
 }
 public function down():void{Schema::dropIfExists('warehouse_transfer_items');Schema::dropIfExists('warehouse_transfers');}
};
