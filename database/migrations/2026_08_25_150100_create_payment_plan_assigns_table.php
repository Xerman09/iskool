<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_plan_assigns', function (Blueprint $table) {
            $table->increments('id');

            $table->unsignedInteger('payment_plan_type_id');
            $table->foreign('payment_plan_type_id')->references('id')->on('payment_plan_types')->onDelete('cascade');

            $table->integer('student_id')->unsigned();
            $table->foreign('student_id')->references('id')->on('sm_students')->onDelete('cascade');

            $table->unsignedInteger('record_id')->nullable();
            $table->integer('course_id')->unsigned()->nullable();

            $table->decimal('total_amount', 10, 2);
            $table->unsignedInteger('number_of_installments');

            $table->tinyInteger('active_status')->default(1);
            $table->integer('created_by')->nullable()->default(1)->unsigned();
            $table->integer('updated_by')->nullable()->default(1)->unsigned();
            $table->integer('school_id')->nullable()->default(1)->unsigned();
            $table->foreign('school_id')->references('id')->on('sm_schools')->onDelete('cascade');
            $table->integer('academic_id')->nullable()->default(1)->unsigned();
            $table->foreign('academic_id')->references('id')->on('sm_academic_years')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_plan_assigns');
    }
};
