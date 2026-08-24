<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fm_fees_invoices', function (Blueprint $table) {
            $table->unsignedInteger('payment_plan_assign_id')->nullable()->after('course_id');
            $table->unsignedInteger('installment_no')->nullable()->after('payment_plan_assign_id');
        });
    }

    public function down(): void
    {
        Schema::table('fm_fees_invoices', function (Blueprint $table) {
            $table->dropColumn(['payment_plan_assign_id', 'installment_no']);
        });
    }
};
