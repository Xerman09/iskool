<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The one invoice a plan's installment chield line lives on - installments no
     * longer get their own invoice, they're all partial payments against this one.
     */
    public function up(): void
    {
        Schema::table('payment_plan_assigns', function (Blueprint $table) {
            $table->unsignedBigInteger('fm_fees_invoice_id')->nullable()->after('payment_plan_type_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_plan_assigns', function (Blueprint $table) {
            $table->dropColumn('fm_fees_invoice_id');
        });
    }
};
