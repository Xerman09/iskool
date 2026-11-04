<?php

namespace App\Http\Controllers\Admin\Report;

use App\Course;
use App\PaymentPlanAssign;
use App\Semester;
use App\SmAcademicYear;
use App\SmStudent;
use App\Models\StudentRecord;
use App\Scopes\AcademicSchoolScope;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Modules\Fees\Entities\FmFeesInvoice;
use Modules\Fees\Entities\FmFeesInvoiceChield;
use Illuminate\Support\Facades\Log;

/**
 * One page answering, for a school year and a term: how many students are there,
 * how many are enrolled, how are they paying, and how much of it has landed.
 *
 * Every figure on it is clickable, which is the reason this is built the way it
 * is. A count and the list behind it must be the same answer to the same
 * question, so the population is resolved and classified exactly once, in
 * classify(), and both the cards and the modal read that. Counting with one query
 * and listing with another is how a report comes to show eleven students above a
 * list of nine.
 */
class StudentReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('PM');
    }

    /** The metrics a card figure can be clicked through to. */
    /**
     * How many consecutive terms a student may register nothing in before the
     * report calls them lapsed rather than merely absent this term.
     *
     * Counted in terms that actually ran, not in calendar slots - see
     * termsThatRan(). Two is the figure most schools use, and the one the
     * school asked for; it is a constant so changing it is changing a number.
     */
    private const LAPSED_AFTER_TERMS = 2;

    private const METRICS = [
        'total', 'active', 'inactive',
        'inactive_graduated', 'inactive_lapsed', 'inactive_not_registered', 'inactive_unsettled',
        'enrolled', 'not_enrolled',
        'cash', 'plan', 'not_billed',
        'paid', 'partial', 'unpaid', 'no_invoice',
    ];

    public function index(Request $request)
    {
        try {
            $criteria = $this->criteria($request);
            $rows = $this->classify($criteria);

            return view('backEnd.reports.studentReport', array_merge($criteria['lists'], [
                'criteria' => $criteria,
                'rows' => $rows,
                'summary' => $this->summarise($rows),
            ]));
        } catch (\Exception $e) {
            Log::error(__METHOD__ . ': ' . $e->getMessage(), ['exception' => $e]);
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

    /**
     * The students behind one figure on a card, for the modal that opens when it
     * is clicked. Same criteria, same classify() - so the list can only ever be
     * the rows that were counted.
     */
    public function metricList(Request $request)
    {
        $request->validate(['metric' => 'required|in:' . implode(',', self::METRICS)]);

        try {
            $criteria = $this->criteria($request);
            $rows = $this->filterByMetric($this->classify($criteria), $request->metric);

            $data = [
                'rows' => $rows,
                'metric' => $request->metric,
                'title' => __('reports.student_metric_' . $request->metric),
                'labels' => $this->criteriaLabels($criteria),
                /* The same request with print switched on, handed to the button in the
                   modal header - so the sheet is of exactly the list on screen rather
                   than of criteria reassembled a second time. */
                'printUrl' => route('student-standard-report-metric', array_merge(
                    $request->except('print'),
                    ['print' => 1]
                )),
            ];

            /* A standalone page that prints itself, the same shape the curriculum
               layout and the fees invoice use - not the modal with the rest of the
               screen hidden, which carries the sidebar's print styles with it. */
            if ($request->boolean('print')) {
                return view('backEnd.reports.studentReportPrint', $data);
            }

            return view('backEnd.reports.partials.studentReportList', $data)->render();
        } catch (\Exception $e) {
            Log::error(__METHOD__ . ': ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['msg' => __('common.Operation failed')], 500);
        }
    }

    /**
     * The criteria written out in words, for the heading of a printed sheet - a
     * page of names is not worth much without saying which year and term it is of.
     */
    private function criteriaLabels(array $criteria)
    {
        $year = SmAcademicYear::find($criteria['academicId']);

        return [
            'year' => $year ? trim($year->year . ($year->title ? ' - ' . $year->title : '')) : '-',
            'semester' => $criteria['semesterId']
                ? optional(Semester::find($criteria['semesterId']))->semester_name
                : __('reports.whole_year'),
            'course' => $criteria['courseId']
                ? optional(Course::find($criteria['courseId']))->course_name
                : __('reports.all_programs'),
            'search' => $criteria['search'],
        ];
    }

    /**
     * What was asked for, and the lists the criteria bar is built from.
     *
     * The school year defaults to the one in session rather than to the newest
     * row, so the report opens on the year the rest of the system is already
     * working in.
     */
    private function criteria(Request $request)
    {
        $schoolId = auth()->user()->school_id;

        $years = SmAcademicYear::where('school_id', $schoolId)->orderByDesc('id')->get();
        $semesters = Semester::where('school_id', $schoolId)->where('active_status', 1)
            ->orderBy('sort_order')->orderBy('id')->get();
        $courses = Course::selectable()->where('school_id', $schoolId)->orderBy('course_name')->get();

        $academicId = $request->filled('academic_id')
            ? (int) $request->academic_id
            : (int) getAcademicId();

        /* No term chosen means the whole year, which is a real question to ask of
           this report and not merely a missing answer - so it stays empty rather
           than being filled in with the open term. */
        $semesterId = $request->filled('semester_id') ? (int) $request->semester_id : null;
        $courseId = $request->filled('course_id') ? (int) $request->course_id : null;

        return [
            'schoolId' => $schoolId,
            'academicId' => $academicId,
            'semesterId' => $semesterId,
            'courseId' => $courseId,
            'search' => trim((string) $request->get('search')),
            'lists' => compact('years', 'semesters', 'courses'),
        ];
    }

    /**
     * Resolve the population and give every student their four answers.
     *
     * Built from StudentRecord rather than from sm_students, because a record is
     * what ties a student to a school year - sm_students carries only where they
     * stand today, so counting off it would report this year's picture under
     * whichever year was asked for. Promoted records are left out: they are the
     * closed copy of a year already finished.
     *
     * Everything is fetched in one query per table and matched in memory. A report
     * that asks per student runs a few thousand queries in a school of any size.
     */
    private function classify(array $criteria)
    {
        $schoolId = $criteria['schoolId'];
        $academicId = $criteria['academicId'];

        $records = StudentRecord::where('school_id', $schoolId)
            ->where('academic_id', $academicId)
            ->where('is_promote', 0)
            ->with(['studentDetail.course', 'class'])
            ->get()
            /* studentDetail() is a withDefault() relation, so a record whose student
               row is gone hands back a blank model rather than null - guarding on ->id
               is what actually excludes those. */
            ->filter(fn ($r) => optional($r->studentDetail)->id);

        if ($criteria['courseId']) {
            $records = $records->filter(fn ($r) => $r->studentDetail->course_id == $criteria['courseId']);
        }

        /* The enrolment invoice is the only thing stamped with a term (student_records
           has no semester column), so it is what a per-term question is answered from.
           Scope stripped because the model pins itself to the session's year and this
           report is asked about any of them. */
        $invoices = FmFeesInvoice::withoutGlobalScope(AcademicSchoolScope::class)
            ->where('school_id', $schoolId)
            ->where('academic_id', $academicId)
            ->where('type', 'fees')
            ->whereIn('record_id', $records->pluck('id'))
            ->get()
            ->keyBy('record_id');

        /* Paid, waived and fined summed per invoice in one query. The stored
           payment_status is a cached summary that goes stale the moment anything
           moves the lines by another route, so the lines are what decides. */
        $totals = FmFeesInvoiceChield::whereIn('fees_invoice_id', $invoices->pluck('id'))
            ->selectRaw('fees_invoice_id,
                COALESCE(SUM(amount),0) amount,
                COALESCE(SUM(fine),0) fine,
                COALESCE(SUM(paid_amount),0) paid,
                COALESCE(SUM(weaver),0) weaver')
            ->groupBy('fees_invoice_id')
            ->get()
            ->keyBy('fees_invoice_id');

        /* Which terms each student has actually registered subjects for.

           This is what Active means here, and it is worked out rather than looked
           up: sm_students.active_status is only ever set by the Delete button, so it
           says "removed from the school", not "studying". In a school where nobody
           has been deleted it is 1 for everyone, and a figure that never moves
           answers nothing.

           Joined to the subject because the term lives there - a registration row
           carries the year but not the term, the same way a block does. */
        $registeredTerms = \App\SmOptionalSubjectAssign::where('sm_optional_subject_assigns.school_id', $schoolId)
            ->where('sm_optional_subject_assigns.academic_id', $academicId)
            ->whereIn('sm_optional_subject_assigns.student_id', $records->pluck('student_id'))
            ->join('sm_subjects', 'sm_subjects.id', '=', 'sm_optional_subject_assigns.subject_id')
            ->get(['sm_optional_subject_assigns.student_id', 'sm_subjects.semester_id'])
            ->groupBy('student_id')
            ->map(fn ($rows) => $rows->pluck('semester_id')->filter()->unique()->values());

        /* Whoever has finished. The table and the is_graduate flag both exist and
           both are empty here, so this reads 0 today - but a graduate is inactive by
           definition and the moment anyone is recorded as one, the figure answers. */
        /* The terms a student would have had to miss to count as lapsed, and every
           term each of them has ever registered in. Both are needed across years,
           so neither is narrowed to the one being reported on. */
        $priorTerms = $this->priorTermsRan($schoolId, $academicId, $criteria['semesterId']);
        $allTermsByStudent = $this->termsRegisteredByStudent($schoolId, $records->pluck('student_id'));

        $graduatedStudentIds = \App\Models\Graduate::whereIn('student_id', $records->pluck('student_id'))
            ->distinct()
            ->pluck('student_id');

        $plannedInvoiceIds = PaymentPlanAssign::where('school_id', $schoolId)
            ->whereIn('fm_fees_invoice_id', $invoices->pluck('id'))
            ->where('active_status', 1)
            ->pluck('fm_fees_invoice_id')
            ->unique();

        $rows = $records->map(function ($record) use ($invoices, $totals, $plannedInvoiceIds, $registeredTerms, $graduatedStudentIds, $priorTerms, $allTermsByStudent, $criteria) {
            $student = $record->studentDetail;
            $invoice = $invoices->get($record->id);
            $total = $invoice ? $totals->get($invoice->id) : null;
            $method = $this->paymentMethodOf($invoice, $plannedInvoiceIds);

            /* Active on the school's own terms: sitting classes this term, and the
               money either settled or on a plan. Both halves are needed - someone
               registered but unpaid is not active, and neither is someone paid up
               who registered for nothing.

               Asked of the chosen term when there is one, and of the whole year
               otherwise, which is the scope the rest of the page is read at. */
            $terms = $registeredTerms->get($student->id, collect());
            $registered = $criteria['semesterId']
                ? $terms->contains((int) $criteria['semesterId'])
                : $terms->isNotEmpty();

            $settled = $record->enrollment_status === 'enrolled' || $method === 'plan';
            $graduated = $graduatedStudentIds->contains($student->id) || (int) $record->is_graduate === 1;

            /* Why someone is inactive, which is the part worth reporting - the
               split on its own says little the Enrolment card does not. First
               match wins, finished before not started before not paid.

               Two of the school's reasons are missing and cannot be guessed:
               academic dismissal and withdrawal have no column anywhere in the
               system. Those need a standing the registrar sets. */
            /* Lapsed sits above merely-not-registered because it is the more exact
               answer to the same question: both have registered nothing this term,
               but one has registered nothing for a while. Graduated stays first -
               somebody who has finished stops registering by definition, and reading
               that as absence would be wrong. */
            $lapsed = $priorTerms->isNotEmpty()
                && $priorTerms->count() >= self::LAPSED_AFTER_TERMS
                && $priorTerms->intersect($allTermsByStudent->get($student->id, collect()))->isEmpty();

            $reason = null;
            if (!($registered && $settled)) {
                if ($graduated) {
                    $reason = 'graduated';
                } elseif (!$registered) {
                    $reason = $lapsed ? 'lapsed' : 'not_registered';
                } else {
                    $reason = 'unsettled';
                }
            }

            return [
                'student' => $student,
                'record' => $record,
                'invoice' => $invoice,
                'active' => $registered && $settled,
                'inactive_reason' => $reason,
                'enrolled' => $record->enrollment_status === 'enrolled',
                'method' => $method,
                'status' => $this->paymentStatusOf($invoice, $total),
                /* Which term this student sits in. The invoice is the stamped answer;
                   where none has been raised yet, registration has already written the
                   term onto the student, so that answers instead. */
                'semester_id' => optional($invoice)->semester_id ?: $student->semester_id,
            ];
        });

        if ($criteria['semesterId']) {
            $rows = $rows->filter(fn ($r) => (int) $r['semester_id'] === $criteria['semesterId']);
        }

        if ($criteria['search'] !== '') {
            $needle = mb_strtolower($criteria['search']);
            $rows = $rows->filter(function ($r) use ($needle) {
                return str_contains(mb_strtolower((string) $r['student']->full_name), $needle)
                    || str_contains(mb_strtolower((string) $r['student']->admission_no), $needle);
            });
        }

        return $rows->sortBy(fn ($r) => $r['student']->full_name)->values();
    }

    /**
     * The terms this school has actually run, in order, as "academicId:semesterId".
     *
     * Read off activity rather than off the calendar. The calendar cannot be
     * trusted to answer this: semesters carry no academic year, so one "1st
     * Semester" row is reused by every year; two of this school's years are both
     * labelled 2026 with overlapping dates; and "School Year" (basic education's
     * term) shares sort_order 1 with "1st Semester", so their relative order falls
     * to whichever id came first.
     *
     * Worse, pairing every year with every semester invents terms that never
     * happened - and a term nobody enrolled in would count as a term everybody
     * skipped, which would mark a whole school lapsed at once. A term that somebody
     * registered in is a term that ran; nothing else needs to be assumed.
     */
    private function termsThatRan($schoolId)
    {
        $yearRank = SmAcademicYear::where('school_id', $schoolId)
            ->orderBy('year')->orderBy('id')->pluck('id')->flip();
        $semesterRank = Semester::where('school_id', $schoolId)
            ->orderBy('sort_order')->orderBy('id')->pluck('id')->flip();

        return \App\SmOptionalSubjectAssign::where('sm_optional_subject_assigns.school_id', $schoolId)
            ->join('sm_subjects', 'sm_subjects.id', '=', 'sm_optional_subject_assigns.subject_id')
            ->whereNotNull('sm_subjects.semester_id')
            ->distinct()
            ->get(['sm_optional_subject_assigns.academic_id', 'sm_subjects.semester_id'])
            ->map(fn ($row) => [
                'key' => $row->academic_id . ':' . $row->semester_id,
                'academic_id' => (int) $row->academic_id,
                /* Anything the lists do not place goes last rather than first, so an
                   unknown year cannot masquerade as the oldest one. */
                'rank' => (($yearRank[$row->academic_id] ?? 9999) * 1000)
                    + ($semesterRank[$row->semester_id] ?? 999),
            ])
            ->unique('key')
            ->sortBy('rank')
            ->values();
    }

    /**
     * The last few terms that ran before the one being reported on - the ones a
     * student would have had to miss, all of them, to count as lapsed.
     *
     * With no term chosen the whole year is in view, so the reference point is that
     * year's latest term; earlier terms of the same year are then prior, which is
     * what "has this student been away a while" means when asked of a year.
     *
     * A school with fewer terms behind it than the threshold returns fewer, and the
     * caller declines to judge: nobody can have missed terms that never happened.
     */
    private function priorTermsRan($schoolId, $academicId, $semesterId)
    {
        $terms = $this->termsThatRan($schoolId);

        if ($terms->isEmpty()) {
            return collect();
        }

        if ($semesterId) {
            $current = $terms->firstWhere('key', $academicId . ':' . $semesterId);
            $reference = $current['rank'] ?? null;
        } else {
            $reference = $terms->where('academic_id', (int) $academicId)->max('rank');
        }

        if ($reference === null) {
            /* The year or term being reported on has no activity of its own, so
               place it after everything that has run and let all of it count. */
            $reference = PHP_INT_MAX;
        }

        return $terms->where('rank', '<', $reference)
            ->sortByDesc('rank')
            ->take(self::LAPSED_AFTER_TERMS)
            ->pluck('key')
            ->values();
    }

    /** Every term each student has registered in, as the same "year:term" keys. */
    private function termsRegisteredByStudent($schoolId, $studentIds)
    {
        return \App\SmOptionalSubjectAssign::where('sm_optional_subject_assigns.school_id', $schoolId)
            ->whereIn('sm_optional_subject_assigns.student_id', $studentIds)
            ->join('sm_subjects', 'sm_subjects.id', '=', 'sm_optional_subject_assigns.subject_id')
            ->whereNotNull('sm_subjects.semester_id')
            ->get(['sm_optional_subject_assigns.student_id', 'sm_optional_subject_assigns.academic_id', 'sm_subjects.semester_id'])
            ->groupBy('student_id')
            ->map(fn ($rows) => $rows->map(fn ($r) => $r->academic_id . ':' . $r->semester_id)->unique()->values());
    }

    /** No invoice means nothing has been billed, so there is no method yet. */
    private function paymentMethodOf($invoice, $plannedInvoiceIds)
    {
        if (!$invoice) {
            return 'not_billed';
        }

        return $plannedInvoiceIds->contains($invoice->id) ? 'plan' : 'cash';
    }

    /** Same arithmetic the invoice itself settles on - see FeesOverrideController. */
    private function paymentStatusOf($invoice, $total)
    {
        if (!$invoice) {
            return 'no_invoice';
        }

        $paid = (float) optional($total)->paid;
        $balance = ((float) optional($total)->amount + (float) optional($total)->fine)
            - ($paid + (float) optional($total)->weaver);

        if ($balance <= 0) {
            return 'paid';
        }

        return $paid > 0 ? 'partial' : 'unpaid';
    }

    private function summarise($rows)
    {
        return [
            'total' => $rows->count(),
            'active' => $rows->where('active', true)->count(),
            'inactive' => $rows->where('active', false)->count(),
            'inactive_graduated' => $rows->where('inactive_reason', 'graduated')->count(),
            'inactive_lapsed' => $rows->where('inactive_reason', 'lapsed')->count(),
            'inactive_not_registered' => $rows->where('inactive_reason', 'not_registered')->count(),
            'inactive_unsettled' => $rows->where('inactive_reason', 'unsettled')->count(),
            'enrolled' => $rows->where('enrolled', true)->count(),
            'not_enrolled' => $rows->where('enrolled', false)->count(),
            'cash' => $rows->where('method', 'cash')->count(),
            'plan' => $rows->where('method', 'plan')->count(),
            'not_billed' => $rows->where('method', 'not_billed')->count(),
            'paid' => $rows->where('status', 'paid')->count(),
            'partial' => $rows->where('status', 'partial')->count(),
            'unpaid' => $rows->where('status', 'unpaid')->count(),
            'no_invoice' => $rows->where('status', 'no_invoice')->count(),
        ];
    }

    /** The counterpart of summarise(): one figure, the rows it was counted from. */
    private function filterByMetric($rows, $metric)
    {
        switch ($metric) {
            case 'total':        return $rows;
            case 'active':       return $rows->where('active', true)->values();
            case 'inactive':     return $rows->where('active', false)->values();
            case 'inactive_graduated':
            case 'inactive_lapsed':
            case 'inactive_not_registered':
            case 'inactive_unsettled':
                return $rows->where('inactive_reason', substr($metric, 9))->values();
            case 'enrolled':     return $rows->where('enrolled', true)->values();
            case 'not_enrolled': return $rows->where('enrolled', false)->values();
            case 'cash':
            case 'plan':
            case 'not_billed':   return $rows->where('method', $metric)->values();
            default:             return $rows->where('status', $metric)->values();
        }
    }
}
