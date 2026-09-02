<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Distinguishes a Bachelor's-level program from a Master's/graduate one, so
     * AssignProgramController::store() can require a student to have actually
     * graduated from an undergraduate program before being assigned a graduate one.
     */
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->enum('level', ['undergraduate', 'graduate'])->default('undergraduate')->after('course_code');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('level');
        });
    }
};
