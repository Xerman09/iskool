<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sm_assign_class_teachers', function (Blueprint $table) {
            $table->unsignedInteger('course_id')->nullable()->after('class_id');
            $table->unsignedInteger('semester_id')->nullable()->after('course_id');

            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');
            $table->foreign('semester_id')->references('id')->on('semesters')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sm_assign_class_teachers', function (Blueprint $table) {
            $table->dropForeign(['course_id']);
            $table->dropForeign(['semester_id']);
            $table->dropColumn(['course_id', 'semester_id']);
        });
    }
};
