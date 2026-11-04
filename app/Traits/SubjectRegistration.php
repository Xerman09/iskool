<?php

namespace App\Traits;

use App\Semester;
use App\SmAssignSubject;
use App\SmClassRoutineUpdate;
use App\SmOptionalSubjectAssign;
use App\SmSubject;
use App\Models\StudentRecord;
use Illuminate\Support\Facades\DB;
use Modules\Fees\Entities\FmFeesInvoice;
use Modules\Fees\Entities\FmFeesInvoiceChield;

/**
 * Registering a student into this semester's blocks.
 *
 * Shared by the student's own Enroll in Subjects screen and the registrar's
 * counterpart, which does the same thing on a student's behalf. The rules live
 * here rather than in either controller because they are the same rules: what a
 * registrar does for a student at the counter cannot be a different enrolment
 * from the one the student could have made themselves. Every guard below was
 * reasoned out once, and a second copy of them would drift the first time one
 * was fixed.
 *
 * A refusal is thrown as a RuntimeException carrying the message to show, so a
 * controller can surface it without re-deciding what went wrong.
 */
trait SubjectRegistration
{
    /**
     * The semester enrolment is open for. There is only ever one.
     */
    protected function activeSemesterFor($schoolId)
    {
        return Semester::where('school_id', $schoolId)
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();
    }

    /**
     * This semester's subjects with every block a student may sit in, each block
     * carrying its schedule and how many seats are left - the whole payload the
     * registration screen renders.
     */
    protected function subjectsWithBlocksFor($student, Semester $activeSemester, $schoolId)
    {
        $subjects = $this->requiredSubjects($student, $activeSemester);
        $paidStudentIds = $this->paidStudentIds($schoolId);

        $subjects->each(function ($subject) use ($student, $paidStudentIds) {
            $equivalentIds = $this->equivalentSubjectIds($subject);

            $blocks = SmAssignSubject::whereIn('subject_id', $equivalentIds)
                ->with('teacher', 'section', 'subject.course')
                ->get();

            $blocks->each(function ($block) use ($paidStudentIds) {
                /* weekend orders and names the days. section and teacherDetail are
                   read by groupedScheduleSlots() whether or not the cell prints
                   them, so loading them here is what keeps that from firing two
                   queries per block on a screen that lists every block on offer. */
                $block->scheduleSlots = SmClassRoutineUpdate::where('subject_id', $block->subject_id)
                    ->where('class_id', $block->class_id)
                    ->where('section_id', $block->section_id)
                    ->with('weekend', 'section', 'teacherDetail')
                    ->get();

                $taken = $this->slotsHeldIn($block->id, $paidStudentIds);
                $block->takenSlots = $taken;
                $block->slotsLeft = $block->max_slots !== null ? max(0, $block->max_slots - $taken) : null;
            });

            $subject->blocks = $blocks;
            // Matched via equivalentSubjectIds(), not $subject->id directly - a chosen
            // block may belong to another course's equivalent curriculum row (cross-program
            // minor), which is saved under that row's own subject_id, not this one's.
            // Scoped to the academic year, because registering is something a student
            // does once per term: without it, last year's row answered for this one and
            // the screen opened already filled in, pointing at blocks belonging to a year
            // that has ended, with no way to enroll for the year actually being opened.
            $subject->chosenAssignSubjectId = SmOptionalSubjectAssign::where('student_id', $student->id)
                ->whereIn('subject_id', $equivalentIds)
                ->where('academic_id', getAcademicId())
                ->value('assign_subject_id');
            $subject->unmetPrerequisites = $this->unmetPrerequisites($student->id, $subject);
        });

        return $subjects;
    }

    /**
     * Whether this term's registration has already been submitted.
     *
     * Minors are optional - curriculum-wide and skippable by design (see
     * requiredSubjects()/saveRegistration()) - so they never gate "awaiting
     * invoice"/balance-summary. Only majors (which only ever show for the
     * student's own class_id/year level) are mandatory for this semester.
     */
    protected function hasSubmittedRegistration($subjects)
    {
        $requiredThisTerm = $subjects->filter(fn ($s) => $s->subject_classification !== 'minor');
        $eligible = $requiredThisTerm->filter(fn ($s) => empty($s->unmetPrerequisites));

        return $eligible->count() > 0 && $eligible->every(fn ($s) => $s->chosenAssignSubjectId !== null);
    }

    /**
     * Write the registration: check the picks over, then replace this year's rows
     * with them.
     *
     * @param  array  $choices  subject id => assign_subject (block) id
     * @return array{lockedSubjectNames: string}
     * @throws \RuntimeException carrying the message to show the person
     */
    protected function saveRegistration($student, Semester $activeSemester, $schoolId, array $choices)
    {
        $subjects = $this->requiredSubjects($student, $activeSemester);
        $paidStudentIds = $this->paidStudentIds($schoolId);

        $selectedBlocks = [];
        $lockedSubjectIds = [];
        foreach ($subjects as $subject) {
            $unmet = $this->unmetPrerequisites($student->id, $subject);
            if (!empty($unmet)) {
                // Not eligible for this subject yet - skip it entirely rather than
                // blocking the rest of the semester's registration. They can still
                // enroll in every other subject they do qualify for.
                $lockedSubjectIds[] = $subject->id;
                continue;
            }

            $assignSubjectId = $choices[$subject->id] ?? null;
            if (!$assignSubjectId) {
                // Minors are optional - curriculum-wide, "pick up a minor you
                // skipped, or take one early" (see requiredSubjects()) - so a
                // student is free to leave one unchosen entirely, this semester
                // or ever. Only majors are mandatory to submit a block for.
                if ($subject->subject_classification === 'minor') {
                    continue;
                }
                throw new \RuntimeException("Please select a block for {$subject->subject_name}.");
            }

            $block = SmAssignSubject::where('id', $assignSubjectId)
                ->whereIn('subject_id', $this->equivalentSubjectIds($subject))
                ->first();
            if (!$block) {
                throw new \RuntimeException('One of the selected blocks is no longer valid.');
            }

            if ($block->max_slots !== null) {
                $taken = $this->slotsHeldIn($block->id, $paidStudentIds, $student->id);
                if ($taken >= $block->max_slots) {
                    throw new \RuntimeException("The block selected for {$subject->subject_name} is already full.");
                }
            }

            $selectedBlocks[] = $block;
        }

        $this->assertNoScheduleConflict($selectedBlocks);

        $record = StudentRecord::where('school_id', $schoolId)
            ->where('student_id', $student->id)
            ->where('academic_id', getAcademicId())
            ->where('is_promote', 0)
            ->first();

        // Don't touch existing registrations for subjects that are locked this submission -
        // a prerequisite may have been attached after the student already registered/was
        // graded for it, and that record shouldn't be silently wiped.
        // Expanded through equivalentSubjectIds() so a previously-saved cross-program pick
        // (saved under the other course's curriculum row id, not this subject's own id) is
        // actually found and replaced instead of left behind as an orphaned duplicate.
        $editableSubjectIds = $subjects
            ->reject(fn ($s) => in_array($s->id, $lockedSubjectIds))
            ->flatMap(fn ($s) => $this->equivalentSubjectIds($s))
            ->unique()
            ->values();

        DB::transaction(function () use ($editableSubjectIds, $selectedBlocks, $student, $schoolId, $record, $paidStudentIds, $activeSemester) {
            // Only this year's rows. The delete is how a resubmission replaces an earlier
            // one, but unscoped it reached back through every year the student has studied
            // and removed those registrations too - including the is_pass marks that
            // unmetPrerequisites() and requiredSubjects() read to decide what has already
            // been passed, which would quietly reopen subjects that were long finished.
            SmOptionalSubjectAssign::where('student_id', $student->id)
                ->whereIn('subject_id', $editableSubjectIds)
                ->where('academic_id', getAcademicId())
                ->delete();

            foreach ($selectedBlocks as $block) {
                // Lock the block row itself (it always exists, unlike the assign rows being
                // counted) so concurrent registrations for the same block serialize here
                // instead of racing between the earlier count check and this insert.
                $lockedBlock = SmAssignSubject::where('id', $block->id)->lockForUpdate()->first();
                if ($lockedBlock && $lockedBlock->max_slots !== null) {
                    $taken = $this->slotsHeldIn($lockedBlock->id, $paidStudentIds, $student->id);
                    if ($taken >= $lockedBlock->max_slots) {
                        throw new \RuntimeException('One of the selected blocks filled up during registration. Please choose another block.');
                    }
                }

                $choice = new SmOptionalSubjectAssign();
                $choice->student_id = $student->id;
                $choice->record_id = optional($record)->id;
                $choice->subject_id = $block->subject_id;
                $choice->assign_subject_id = $block->id;
                $choice->school_id = $schoolId;
                $choice->session_id = getAcademicId();
                $choice->academic_id = getAcademicId();
                $choice->active_status = 1;
                $choice->save();
            }

            /* A student who is already enrolled stays enrolled when they change
               their load - the cashier does not review them again from scratch
               for adding a subject. Tuition is billed on the curriculum's full
               load for the term, not on what was ticked, so the amount owed does
               not move either. Only someone not yet enrolled this year is put up
               for review, and only this year: a previous year keeps the standing
               it ended on. */
            if ($record && $record->enrollment_status !== 'enrolled') {
                $record->enrollment_status = 'pending';
                $record->save();
            }

            /* Which term the student is in. Nothing else in the system ever wrote this,
               so it stayed null for everyone - which quietly emptied the Semester filter
               on attendance (it compares against this column) and left every enrolment
               invoice without a term, since EnrollmentInvoicing reads it too. Registering
               is the moment the answer is known. */
            if ($student->semester_id != $activeSemester->id) {
                $student->semester_id = $activeSemester->id;
                $student->save();
            }
        });

        /* Joining a block is joining its class group chat, and registration is the
           moment that becomes true - Assign Subject raises the group long before
           anyone has chosen it. Firing the same event Assign Subject fires keeps one
           definition of what a class group is.

           Outside the transaction, and unable to fail the enrolment: a chat that
           cannot be written is not a reason to refuse a student their subjects. */
        foreach ($selectedBlocks as $block) {
            try {
                event(new \App\Events\CreateClassGroupChat($block));
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error(
                    'Class group chat sync failed for block ' . $block->id . ': ' . $e->getMessage(),
                    ['exception' => $e]
                );
            }
        }

        return [
            'lockedSubjectNames' => $subjects->whereIn('id', $lockedSubjectIds)->pluck('subject_name')->implode(', '),
        ];
    }

    /**
     * No two of the chosen blocks may want the same student at the same hour.
     *
     * Slots belonging to one subject are left to overlap each other: a block's own
     * rows are its weekly meetings, not a clash.
     */
    protected function assertNoScheduleConflict(array $selectedBlocks)
    {
        $schedule = SmClassRoutineUpdate::whereIn('subject_id', collect($selectedBlocks)->pluck('subject_id'))
            ->where(function ($q) use ($selectedBlocks) {
                foreach ($selectedBlocks as $block) {
                    $q->orWhere(function ($q2) use ($block) {
                        $q2->where('subject_id', $block->subject_id)
                            ->where('class_id', $block->class_id)
                            ->where('section_id', $block->section_id);
                    });
                }
            })
            ->get();

        $allSlots = $schedule->groupBy('subject_id')->flatten(1);
        foreach ($allSlots as $a) {
            foreach ($allSlots as $b) {
                if ($a->id === $b->id || $a->subject_id === $b->subject_id) {
                    continue;
                }
                if ((int) $a->day !== (int) $b->day) {
                    continue;
                }
                if ($a->start_time < $b->end_time && $b->start_time < $a->end_time) {
                    throw new \RuntimeException('Schedule conflict detected between the selected subjects.');
                }
            }
        }
    }

    /**
     * The subjects a student must/can register for this semester. Majors stay locked to the
     * student's current year level. Minors are curriculum-wide - any minor in the student's
     * own course+curriculum for this semester is offered regardless of which year level it's
     * tagged under, so a student can pick up a minor they skipped, or take one early. Minors
     * already passed (in any of their cross-program equivalent forms - see
     * equivalentSubjectIds()) are excluded so they stop reappearing once completed; without
     * this, resubmitting a later semester would delete and recreate that already-graded row.
     */
    protected function requiredSubjects($student, Semester $activeSemester)
    {
        $subjects = SmSubject::where('course_id', $student->course_id)
            ->where('curriculum_version_id', $student->curriculum_version_id)
            ->where('semester_id', $activeSemester->id)
            ->where(function ($q) use ($student) {
                $q->where('class_id', $student->class_id)
                    ->orWhere('subject_classification', 'minor');
            })
            ->get();

        /* Anything already passed drops off the list - a major as much as a minor, and
           whichever curriculum version it was passed under. That is what crediting
           means: the student does not sit the same subject twice. See
           creditedSubjectIds() for what counts as the same subject. */
        return $subjects->reject(function ($subject) use ($student) {
            return SmOptionalSubjectAssign::where('student_id', $student->id)
                ->whereIn('subject_id', $this->creditedSubjectIds($subject))
                ->where('is_pass', 1)
                ->exists();
        })->values();
    }

    /**
     * Minor subjects are shared across courses via source_subject_id (the base-subject
     * catalog Curriculum Builder builds each course's curriculum rows from). For a minor,
     * this resolves every course's curriculum row built from the same base subject, with the
     * same units, for the same semester, so students can register into any course's open
     * block. Majors, and any subject without a source_subject_id, stay scoped to just the
     * subject itself.
     */
    protected function equivalentSubjectIds(SmSubject $subject)
    {
        if ($subject->subject_classification !== 'minor' || !$subject->source_subject_id) {
            return collect([$subject->id]);
        }

        return SmSubject::where('source_subject_id', $subject->source_subject_id)
            ->where('subject_classification', 'minor')
            ->where('semester_id', $subject->semester_id)
            ->where('units', $subject->units)
            ->pluck('id');
    }

    /**
     * The students whose tuition money has actually landed this academic year.
     *
     * A seat is not held by asking for it. Counting every registration meant one
     * student opening the form took the block away from everyone else, and a block
     * could sit full of people who never came back to pay. A partial payment is
     * enough - the school's rule - and whoever settles first holds the seat.
     *
     * Store items are left out. They ride on the same invoice while it is still open,
     * so counting them would let a student hold a seat by buying a uniform.
     */
    protected function paidStudentIds($schoolId)
    {
        // FmFeesInvoice is scoped to the academic year, so this is this year's money.
        $invoices = FmFeesInvoice::where('school_id', $schoolId)
            ->where('type', 'fees')
            ->whereNotNull('student_id')
            ->get(['id', 'student_id']);

        if ($invoices->isEmpty()) {
            return collect();
        }

        $paidInvoiceIds = FmFeesInvoiceChield::where('school_id', $schoolId)
            ->whereIn('fees_invoice_id', $invoices->pluck('id'))
            ->whereNull('sm_item_id')
            ->where('paid_amount', '>', 0)
            ->distinct()
            ->pluck('fees_invoice_id');

        return $invoices->whereIn('id', $paidInvoiceIds)
            ->pluck('student_id')
            ->unique()
            ->values();
    }

    /**
     * How many of a block's seats are actually held, optionally ignoring one student
     * so that re-submitting a registration does not count them against themselves.
     */
    protected function slotsHeldIn($assignSubjectId, $paidStudentIds, $exceptStudentId = null)
    {
        if ($paidStudentIds->isEmpty()) {
            return 0;
        }

        return SmOptionalSubjectAssign::where('assign_subject_id', $assignSubjectId)
            ->whereIn('student_id', $paidStudentIds)
            ->when($exceptStudentId, fn ($q) => $q->where('student_id', '!=', $exceptStudentId))
            ->count();
    }

    /**
     * Every curriculum row that counts as the same subject already taken.
     *
     * A curriculum version rebuilds each subject as its own row, so a student who shifts
     * course or version meets a different id for a subject they have already sat. Two rows
     * built from the same base subject and carrying the same number of units are the same
     * subject for the purpose of credit - the school's rule - whichever course or version
     * they belong to. Units are what makes it safe: a subject rebuilt with a different
     * load is a different subject, and is not credited.
     *
     * This is credit, not registration. equivalentSubjectIds() stays narrower, because
     * which block a student may sit in is a different question from what they have passed.
     */
    protected function creditedSubjectIds(SmSubject $subject)
    {
        if (!$subject->source_subject_id) {
            return collect([$subject->id]);
        }

        return SmSubject::where('source_subject_id', $subject->source_subject_id)
            ->where('units', $subject->units)
            ->pluck('id');
    }

    /**
     * Returns the names of prerequisite subjects the student has not yet passed
     * (passed = teacher marked is_pass=1 on their SmOptionalSubjectAssign row).
     *
     * Matched through creditedSubjectIds(), so a prerequisite passed under an earlier
     * curriculum still counts - otherwise a shifting student would be sent back through
     * subjects they finished years before.
     */
    protected function unmetPrerequisites($studentId, SmSubject $subject)
    {
        $prerequisites = $subject->prerequisites()->with('prerequisiteSubject')->get();

        $unmet = [];
        foreach ($prerequisites as $prerequisite) {
            $prerequisiteSubject = $prerequisite->prerequisiteSubject;

            $creditedIds = $prerequisiteSubject
                ? $this->creditedSubjectIds($prerequisiteSubject)
                : collect([$prerequisite->prerequisite_subject_id]);

            $passed = SmOptionalSubjectAssign::where('student_id', $studentId)
                ->whereIn('subject_id', $creditedIds)
                ->where('is_pass', 1)
                ->exists();

            if (!$passed) {
                $unmet[] = optional($prerequisiteSubject)->subject_name ?? 'Unknown subject';
            }
        }

        return $unmet;
    }
}
