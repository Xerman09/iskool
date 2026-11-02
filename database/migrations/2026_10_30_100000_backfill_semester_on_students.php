<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Give every already-registered student the term they registered in.
 *
 * sm_students.semester_id was read in two places and written in none: the Student
 * Attendance criteria compare the chosen term against it, and every enrolment invoice
 * takes its semester from it. Left null for everyone, the Semester filter silently
 * emptied the attendance roll and invoices carried no term at all.
 *
 * StudentSubjectRegistrationController::register() now records it. This fills in the
 * students who registered before that, reading the term off the subjects they actually
 * chose - their most recent registration wins, which is the term they are in now.
 *
 * Nothing is overwritten where a value already exists: a registrar who set it by hand
 * knows something this cannot.
 */
class BackfillSemesterOnStudents extends Migration
{
    public function up()
    {
        $students = DB::table('sm_students')
            ->whereNull('semester_id')
            ->pluck('id');

        foreach ($students as $studentId) {
            /* The term of the newest subject this student registered for. Joined rather
               than read off the student, because the subject is where the term is true. */
            $semesterId = DB::table('sm_optional_subject_assigns as o')
                ->join('sm_subjects as s', 's.id', '=', 'o.subject_id')
                ->where('o.student_id', $studentId)
                ->whereNotNull('s.semester_id')
                ->orderByDesc('o.academic_id')
                ->orderByDesc('o.id')
                ->value('s.semester_id');

            if ($semesterId) {
                DB::table('sm_students')->where('id', $studentId)->update(['semester_id' => $semesterId]);
            }
        }
    }

    /**
     * Nothing to undo: the column held null for these rows and holds a term now. Clearing
     * it again would only restore the defect, and would not tell apart a value this filled
     * from one a registrar set afterwards.
     */
    public function down()
    {
        //
    }
}
