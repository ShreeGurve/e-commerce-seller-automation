<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('automation_tasks', 'effort_hours')) {
            Schema::table('automation_tasks', function (Blueprint $table) { $table->decimal('effort_hours', 7, 2)->nullable()->after('priority'); });
        }
        if (!Schema::hasTable('automation_employee_capacities')) {
            Schema::create('automation_employee_capacities', function (Blueprint $table) {
                $table->id(); $table->unsignedBigInteger('business_id')->nullable()->index(); $table->unsignedBigInteger('user_id')->index();
                $table->decimal('weekly_capacity_hours', 6, 2)->default(40); $table->timestamps();
                $table->unique(['business_id', 'user_id'], 'auto_emp_capacity_business_user_uq');
            });
        }
    }
    public function down(): void
    {
        Schema::dropIfExists('automation_employee_capacities');
        if (Schema::hasColumn('automation_tasks', 'effort_hours')) Schema::table('automation_tasks', fn (Blueprint $table) => $table->dropColumn('effort_hours'));
    }
};
