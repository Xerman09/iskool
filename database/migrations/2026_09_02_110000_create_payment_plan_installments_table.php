<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Pure due-date schedule for an installment plan - no money lives here. The
     * actual payable amount is one real chield line on the plan's invoice
     * (fm_fees_invoice_id on payment_plan_assigns); this table just says "expect
     * ₱X by date Y" so the UI can compute paid/partial/unpaid per installment by
     * comparing that chield's real paid_amount against these rows in order.
     */
    public function up(): void
    {
        Schema::create('payment_plan_installments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payment_plan_assign_id');
            $table->integer('installment_no');
            $table->date('due_date');
            $table->decimal('amount', 10, 2);
            $table->integer('school_id')->nullable();
            $table->integer('academic_id')->nullable();
            $table->timestamps();

            $table->index('payment_plan_assign_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_plan_installments');
    }
};
