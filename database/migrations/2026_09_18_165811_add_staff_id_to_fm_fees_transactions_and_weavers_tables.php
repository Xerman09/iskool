<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Item-store invoices can now belong to a staff member instead of a student
     * (see EnrollmentInvoicing::newStaffOrderInvoice()) - these two tables only
     * had student_id, so a staff invoice's payment had nowhere to record its
     * owner. Mirrors the nullable student_id/staff_id pair already used on
     * fm_fees_invoices and sm_item_orders.
     */
    public function up(): void
    {
        Schema::table('fm_fees_transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('staff_id')->nullable()->after('student_id');
        });

        Schema::table('fm_fees_weavers', function (Blueprint $table) {
            $table->unsignedBigInteger('staff_id')->nullable()->after('student_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fm_fees_transactions', function (Blueprint $table) {
            $table->dropColumn('staff_id');
        });

        Schema::table('fm_fees_weavers', function (Blueprint $table) {
            $table->dropColumn('staff_id');
        });
    }
};
