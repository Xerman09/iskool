<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Lets a 'store' invoice belong to a staff member instead of a student - every
     * other invoice column (student_id, class_id, course_id, semester_id, record_id)
     * is already nullable, so a staff invoice just leaves those null and sets this
     * instead. Never both staff_id and student_id on the same invoice.
     */
    public function up(): void
    {
        Schema::table('fm_fees_invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('staff_id')->nullable()->after('student_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fm_fees_invoices', function (Blueprint $table) {
            $table->dropColumn('staff_id');
        });
    }
};
