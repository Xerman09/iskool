<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Item orders used to only ever come from students. Employees can now submit
     * them too (via staff-item-store), so student_id/record_id have to become
     * optional and a parallel staff_id is added - exactly one of student_id or
     * staff_id is set per order, never both.
     */
    public function up(): void
    {
        Schema::table('sm_item_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('staff_id')->nullable()->after('student_id');
            $table->index(['staff_id', 'status']);
        });

        Schema::table('sm_item_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('student_id')->nullable()->change();
            $table->unsignedBigInteger('record_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sm_item_orders', function (Blueprint $table) {
            $table->dropIndex(['staff_id', 'status']);
            $table->dropColumn('staff_id');
        });

        Schema::table('sm_item_orders', function (Blueprint $table) {
            $table->unsignedBigInteger('student_id')->nullable(false)->change();
            $table->unsignedBigInteger('record_id')->nullable(false)->change();
        });
    }
};
