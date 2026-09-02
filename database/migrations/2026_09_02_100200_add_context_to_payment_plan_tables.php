<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * "context" separates a tuition installment plan from a store (item-purchase)
     * installment plan so a student can have one active plan of each kind at the
     * same time without the "already on a plan" uniqueness check in
     * PaymentPlanAssignController colliding between the two.
     */
    public function up(): void
    {
        Schema::table('payment_plan_types', function (Blueprint $table) {
            $table->string('context')->default('tuition')->after('name');
        });

        Schema::table('payment_plan_assigns', function (Blueprint $table) {
            $table->string('context')->default('tuition')->after('payment_plan_type_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_plan_types', function (Blueprint $table) {
            $table->dropColumn('context');
        });

        Schema::table('payment_plan_assigns', function (Blueprint $table) {
            $table->dropColumn('context');
        });
    }
};
