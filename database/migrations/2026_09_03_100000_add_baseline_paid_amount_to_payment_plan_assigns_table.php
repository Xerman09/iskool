<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Snapshot of the plan's invoice's Tpaidamount at the moment the plan was
     * created - the invoice's real lines (tuition/misc, or items) are already
     * fully billed before any plan exists, so there's no separate "installment"
     * line to track payments against. Everything paid past this baseline is
     * progress against the installment schedule, however it's split across lines.
     */
    public function up(): void
    {
        Schema::table('payment_plan_assigns', function (Blueprint $table) {
            $table->decimal('baseline_paid_amount', 10, 2)->default(0)->after('total_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_plan_assigns', function (Blueprint $table) {
            $table->dropColumn('baseline_paid_amount');
        });
    }
};
