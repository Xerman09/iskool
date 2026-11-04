<?php

namespace App\Http\Controllers\Admin\Academics;

use App\Course;
use App\SmStudent;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Traits\SubjectRegistration;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Support\Facades\Log;

/**
 * The registrar enrolling a student into this semester's blocks, at the counter.
 *
 * The student's own Enroll in Subjects screen is not always the way it happens: a
 * student without a login, one who cannot get online, or one standing at the
 * window during enrolment week still has to be registered by somebody. This is
 * that same screen with one addition, the student picker, and it runs on the same
 * rules - prerequisites, seat counts, schedule clashes - because they are the
 * school's rules and not a property of who is clicking. See SubjectRegistration.
 */
class AdminSubjectRegistrationController extends Controller
{
    use SubjectRegistration;

    public function __construct()
    {
        $this->middleware('PM');
    }

    public function index(Request $request)
    {
        try {
            $schoolId = auth()->user()->school_id;

            $courses = Course::selectable()->where('school_id', $schoolId)->get();

            /* Only students who already have a program and a curriculum version can
               be registered - those two are what decides which subjects even exist
               for them (see requiredSubjects()). Anyone else belongs on Assign
               Program first, so they are left out of the picker rather than offered
               and then refused. */
            $students = SmStudent::where('school_id', $schoolId)
                ->whereNotNull('course_id')
                ->whereNotNull('curriculum_version_id')
                ->when($request->filled('course_id'), function ($q) use ($request) {
                    $q->where('course_id', $request->course_id);
                })
                ->with('course', 'class')
                ->orderBy('first_name')
                ->get();

            $student = null;
            $activeSemester = $this->activeSemesterFor($schoolId);
            $subjects = collect();
            $hasSubmitted = false;

            if ($request->filled('student_id')) {
                $student = SmStudent::where('school_id', $schoolId)
                    ->whereNotNull('course_id')
                    ->whereNotNull('curriculum_version_id')
                    ->with('course', 'curriculumVersion', 'class')
                    ->find($request->student_id);

                if (!$student) {
                    Toastr::error(__('academics.student_has_no_program'), 'Failed');
                    return redirect()->route('admin-subject-registration');
                }

                if ($activeSemester && $student->class_id) {
                    $subjects = $this->subjectsWithBlocksFor($student, $activeSemester, $schoolId);
                    $hasSubmitted = $this->hasSubmittedRegistration($subjects);
                }
            }

            return view('backEnd.academics.adminSubjectRegistration', compact(
                'courses',
                'students',
                'student',
                'activeSemester',
                'subjects',
                'hasSubmitted'
            ));
        } catch (\Exception $e) {
            Log::error(__METHOD__ . ': ' . $e->getMessage(), ['exception' => $e]);
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

    public function register(Request $request)
    {
        $request->validate(['student_id' => 'required|integer']);

        try {
            $schoolId = auth()->user()->school_id;

            /* Re-resolved from the database rather than taken from the form, so a
               posted id cannot register a student from another school or one who
               has no program to register into. */
            $student = SmStudent::where('school_id', $schoolId)
                ->whereNotNull('course_id')
                ->whereNotNull('curriculum_version_id')
                ->find($request->student_id);

            if (!$student) {
                Toastr::error(__('academics.student_has_no_program'), 'Failed');
                return redirect()->route('admin-subject-registration');
            }

            $activeSemester = $this->activeSemesterFor($schoolId);
            if (!$activeSemester) {
                Toastr::error(__('academics.no_active_semester'), 'Failed');
                return redirect()->back();
            }

            $result = $this->saveRegistration(
                $student,
                $activeSemester,
                $schoolId,
                (array) $request->input('assign_subject_id', [])
            );

            $message = __('academics.subjects_registered_for', ['student' => $student->full_name]);
            if ($result['lockedSubjectNames']) {
                $message .= ' ' . __('academics.not_included_prerequisite', ['subjects' => $result['lockedSubjectNames']]);
            }

            Toastr::success($message, 'Success');

            // Back to the same student, so the registrar sees what was just saved
            // instead of an empty picker.
            return redirect()->route('admin-subject-registration', ['student_id' => $student->id]);
        } catch (\RuntimeException $e) {
            // Carries the reason the registration was refused - see SubjectRegistration.
            Toastr::error($e->getMessage(), 'Failed');
            return redirect()->back();
        } catch (\Exception $e) {
            Log::error(__METHOD__ . ': ' . $e->getMessage(), ['exception' => $e]);
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }
}
