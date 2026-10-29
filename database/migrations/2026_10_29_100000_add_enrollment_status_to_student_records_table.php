<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Enrollment status belongs to a year, not to a person.
 *
 * It lived on sm_students as a single column, so a student enrolled once stayed
 * "enrolled" in every academic year that followed - including years that held no
 * record of them at all. Worse, the cashier's gate reads the same column: a student
 * carrying last year's 'enrolled' could not be invoiced for the new year, because
 * that gate only opens on 'pending'.
 *
 * student_records already exists once per student per year, which is exactly the
 * grain this belongs at. sm_students keeps its column untouched so this can be rolled
 * back, but nothing reads it any more.
 */
class AddEnrollmentStatusToStudentRecordsTable extends Migration
{
    public function up()
    {
        Schema::table('student_records', function (Blueprint $table) {
            $table->string('enrollment_status', 20)->nullable()->after('is_graduate');
            $table->timestamp('enrolled_at')->nullable()->after('enrollment_status');
        });

        if (!Schema::hasColumn('sm_students', 'enrollment_status')) {
            return;
        }

        /* Carry the existing value to the year it actually describes: the year the
           student registered subjects in. A student with registrations in several
           years gets it on the most recent one, which is what the single column was
           last describing. */
        $students = DB::table('sm_students')
            ->whereNotNull('enrollment_status')
            ->get(['id', 'academic_id', 'enrollment_status', 'enrolled_at']);

        foreach ($students as $student) {
            $academicId = DB::table('sm_optional_subject_assigns')
                ->where('student_id', $student->id)
                ->max('academic_id');

            /* No registrations anywhere - a basic-education learner never has any, and
               a college student may have been assigned a program without getting as far
               as picking blocks. Fall back to the year they were admitted in. */
            $academicId = $academicId ?: $student->academic_id;

            if (!$academicId) {
                continue;
            }

            DB::table('student_records')
                ->where('student_id', $student->id)
                ->where('academic_id', $academicId)
                ->update([
                    /* The old column defaulted to an empty string, which is not a status
                       and never matched the null the code tests for - so a student sitting
                       on '' was skipped by the very branch meant to move them to 'pending'.
                       Only the two real values survive the move. */
                    'enrollment_status' => $student->enrollment_status ?: null,
                    'enrolled_at' => $student->enrolled_at,
                ]);
        }
    }

    public function down()
    {
        Schema::table('student_records', function (Blueprint $table) {
            $table->dropColumn(['enrollment_status', 'enrolled_at']);
        });
    }
}
