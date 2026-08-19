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
        Schema::table('sm_students', function (Blueprint $table) {
            $table->integer('course_id')->nullable()->unsigned();
            $table->foreign('course_id')->references('id')->on('courses')->onDelete('cascade');

            $table->integer('curriculum_version_id')->nullable()->unsigned();
            $table->foreign('curriculum_version_id')->references('id')->on('curriculum_versions')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sm_students', function (Blueprint $table) {
            $table->dropForeign(['course_id']);
            $table->dropForeign(['curriculum_version_id']);
            $table->dropColumn(['course_id', 'curriculum_version_id']);
        });
    }
};
