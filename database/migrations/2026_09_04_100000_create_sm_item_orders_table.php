<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Item purchases used to go straight onto an invoice and decrement stock the
     * moment the student submitted the form. This table lets a student's item
     * picks sit as a review-able order first - reception approves (which is when
     * the invoice line and stock deduction actually happen) or rejects, mirroring
     * how subject registration goes "pending" before the registrar generates an
     * invoice for it.
     */
    public function up(): void
    {
        Schema::create('sm_item_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('record_id');
            $table->unsignedBigInteger('item_id');
            $table->integer('quantity');
            $table->decimal('unit_price', 15, 2);
            $table->decimal('amount', 15, 2);
            $table->string('status')->default('pending'); // pending, approved, rejected, cancelled
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->string('reject_reason')->nullable();
            $table->unsignedBigInteger('fm_fees_invoice_id')->nullable();
            $table->unsignedBigInteger('school_id');
            $table->unsignedBigInteger('academic_id')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'status']);
            $table->index(['school_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sm_item_orders');
    }
};
