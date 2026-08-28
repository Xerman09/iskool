<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sm_student_program_histories', function (Blueprint $table) {
            $table->increments('id');

            $table->integer('student_id')->unsigned();
            $table->foreign('student_id')->references('id')->on('sm_students')->onDelete('cascade');

            $table->integer('previous_course_id')->nullable()->unsigned();
            $table->foreign('previous_course_id')->references('id')->on('courses')->onDelete('set null');

            $table->integer('current_course_id')->nullable()->unsigned();
            $table->foreign('current_course_id')->references('id')->on('courses')->onDelete('set null');

            $table->integer('previous_curriculum_version_id')->nullable()->unsigned();
            $table->foreign('previous_curriculum_version_id', 'sph_previous_curriculum_version_id_foreign')->references('id')->on('curriculum_versions')->onDelete('set null');

            $table->integer('current_curriculum_version_id')->nullable()->unsigned();
            $table->foreign('current_curriculum_version_id', 'sph_current_curriculum_version_id_foreign')->references('id')->on('curriculum_versions')->onDelete('set null');

            $table->integer('changed_by')->nullable()->unsigned();

            $table->integer('school_id')->nullable()->default(1)->unsigned();
            $table->foreign('school_id')->references('id')->on('sm_schools')->onDelete('cascade');

            $table->integer('academic_id')->nullable()->default(1)->unsigned();
            $table->foreign('academic_id')->references('id')->on('sm_academic_years')->onDelete('cascade');

            $table->timestamps();
        });

        $students = DB::table('sm_students')->whereNotNull('course_id')->get(['id', 'course_id', 'curriculum_version_id', 'school_id', 'academic_id', 'created_at']);

        foreach ($students as $student) {
            DB::table('sm_student_program_histories')->insert([
                'student_id' => $student->id,
                'previous_course_id' => null,
                'current_course_id' => $student->course_id,
                'previous_curriculum_version_id' => null,
                'current_curriculum_version_id' => $student->curriculum_version_id,
                'changed_by' => null,
                'school_id' => $student->school_id ?: 1,
                'academic_id' => $student->academic_id ?: 1,
                'created_at' => $student->created_at,
                'updated_at' => $student->created_at,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sm_student_program_histories');
    }
};
