<?php

namespace App\Http\Controllers\Admin\Report;

use App\Course;
use App\Semester;
use App\SmStudent;
use App\SmAcademicYear;
use App\PaymentPlanAssign;
use App\Models\StudentRecord;
use App\Scopes\AcademicSchoolScope;
use App\Traits\EnrollmentBalanceBreakdown;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Modules\Fees\Entities\FmFeesInvoice;
use Modules\Fees\Entities\FmFeesInvoiceChield;
use Illuminate\Support\Facades\Log;

/**
 * One page answering, for a school year and a term: what was billed, what came
 * in, what is still owed, and how long it has been owed.
 *
 * The counterpart of StudentReportController, and built the same way and for the
 * same reason: a figure and the list behind it must be the same answer to the
 * same question, so the population is resolved and priced exactly once, in
 * classify(), and both the cards and the modal read that.
 *
 * Why this exists when the Fees module already ships five reports: those five
 * are one query rendered five ways - each shows a single column of the same
 * per-student table, and none of them totals anything by program or term, ages
 * the outstanding, or says what a student owes across more than one invoice.
 */
class FeesStandardReportController extends Controller
{
    /* ledgerFor(), so a printed statement's charges and payments come from the
       same source as the on-screen ledger a student reads - two different answers
       to "what do I owe" is the one outcome worth engineering against. The
       trait's priorYearBalanceFor() is deliberately not used; see
       priorBalancesFor() for why. */
    use EnrollmentBalanceBreakdown;

    public function __construct()
    {
        $this->middleware('PM');
    }

    /**
     * How many statements one bulk print will produce.
     *
     * Each student's statement costs a pair of queries for their invoices and
     * payments, and a browser asked to lay out a thousand of them stops
     * responding - so a run larger than this is refused with the count, rather
     * than left to time out halfway and print nothing.
     */
    private const MAX_BULK_STATEMENTS = 200;

    /**
     * Days overdue at which each bucket ends. A balance not yet due, or due
     * today, is current; everything else falls in the first bucket it fits.
     */
    private const AGING_BUCKETS = [
        'aging_1_30' => 30,
        'aging_31_60' => 60,
        'aging_61_90' => 90,
    ];

    /** The metrics a figure on a card can be clicked through to. */
    private const METRICS = [
        'all',
        'assessed', 'waiver', 'fine', 'net', 'collected', 'outstanding',
        'paid', 'partial', 'unpaid', 'no_invoice',
        'aging_current', 'aging_1_30', 'aging_31_60', 'aging_61_90', 'aging_90_plus',
        'wallet',
        'on_plan', 'not_on_plan', 'plan_overdue',
        'prior_balance',
    ];

    public function index(Request $request)
    {
        try {
            $criteria = $this->criteria($request);
            $rows = $this->classify($criteria);

            return view('backEnd.reports.feesReport', array_merge($criteria['lists'], [
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
                'title' => __('reports.fees_metric_' . $request->metric),
                'totals' => $this->summarise($rows),
                'labels' => $this->criteriaLabels($criteria),
                /* The same request with print switched on, handed to the button in the
                   modal header - so the sheet is of exactly the list on screen rather
                   than of criteria reassembled a second time. */
                'printUrl' => route('fees-standard-report-metric', array_merge(
                    $request->except('print'),
                    ['print' => 1]
                )),
                /* Statements for this list, carrying the same metric - so the run is
                   the students behind the figure that was clicked, not the whole
                   filtered population. */
                'statementsUrl' => route('fees-standard-report-statements', $request->except('print')),
            ];

            /* A standalone page that prints itself, the same shape the student report
               uses - not the modal with the rest of the screen hidden, which carries
               the sidebar's print styles with it. */
            if ($request->boolean('print')) {
                return view('backEnd.reports.feesReportPrint', $data);
            }

            return view('backEnd.reports.partials.feesReportList', $data)->render();
        } catch (\Exception $e) {
            Log::error(__METHOD__ . ': ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['msg' => __('common.Operation failed')], 500);
        }
    }

    /**
     * One student's statement of account, as a sheet that prints itself.
     *
     * Scoped to the same criteria the report was read at, so the balance on the
     * statement is the balance the figure was counted from. Reached from the
     * student's row in the breakdown table and from any metric list.
     */
    public function statement(Request $request, $studentId)
    {
        try {
            $criteria = $this->criteria($request);

            /* Scoped to the school rather than found by id alone: an id in the URL is
               whatever the sender typed, and one school's admin has no business
               printing another's student. */
            $student = SmStudent::where('school_id', $criteria['schoolId'])->find($studentId);

            if (!$student) {
                Toastr::error(__('common.not_found'), 'Failed');
                return redirect()->route('fees-standard-report');
            }

            return view('backEnd.reports.feesStatementPrint', [
                'statements' => collect([$this->statementFor($student, $criteria)]),
                'labels' => $this->criteriaLabels($criteria),
            ]);
        } catch (\Exception $e) {
            Log::error(__METHOD__ . ': ' . $e->getMessage(), ['exception' => $e]);
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

    /**
     * A statement for every student behind the figure currently on screen - one
     * per page, in the order the report lists them.
     *
     * Takes the same metric the modal does, so "print statements" from an
     * Outstanding list is a run of exactly the students who owe something, and
     * from the report itself is the whole filtered population.
     */
    public function statements(Request $request)
    {
        $request->validate(['metric' => 'nullable|in:' . implode(',', self::METRICS)]);

        try {
            $criteria = $this->criteria($request);
            $rows = $this->classify($criteria);

            if ($request->filled('metric')) {
                $rows = $this->filterByMetric($rows, $request->metric);
            }

            if ($rows->isEmpty()) {
                Toastr::warning(__('reports.no_students_found'), 'Failed');
                return redirect()->back();
            }

            if ($rows->count() > self::MAX_BULK_STATEMENTS) {
                Toastr::warning(__('reports.fees_statement_too_many', [
                    'count' => $rows->count(),
                    'max' => self::MAX_BULK_STATEMENTS,
                ]), 'Failed');
                return redirect()->back();
            }

            return view('backEnd.reports.feesStatementPrint', [
                'statements' => $rows->map(fn ($row) => $this->statementFor($row['student'], $criteria)),
                'labels' => $this->criteriaLabels($criteria),
            ]);
        } catch (\Exception $e) {
            Log::error(__METHOD__ . ': ' . $e->getMessage(), ['exception' => $e]);
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

    /**
     * Everything one statement prints: the charges, the payments against them,
     * and what is left.
     *
     * The lines and payments come from ledgerFor(), which is what the student's
     * own ledger page reads - but the totals are recomputed here with this
     * report's arithmetic rather than taken from its due_amount sums, because
     * due_amount is a stored column that only the payment flow maintains. Taking
     * it would let a statement disagree with the figure it was printed from.
     */
    private function statementFor(SmStudent $student, array $criteria)
    {
        /* ledgerFor() takes the same three scopes the picker offers, so a whole-stay
           statement is one call rather than a year's call repeated. */
        $ledger = $criteria['scope'] === 'whole_stay'
            ? $this->ledgerFor($student, 'whole_stay', null)
            : $this->ledgerFor($student, 'semester', $criteria['academicId']);

        $invoices = $ledger['invoices'];
        if ($criteria['type']) {
            $invoices = $invoices->where('type', $criteria['type'])->values();
        }

        $invoiceIds = $invoices->pluck('id');
        $payments = $ledger['transactions']->whereIn('fees_invoice_id', $invoiceIds)->values();

        /* The instalment schedules behind these invoices, one query for the lot, and
           fetched before the totals below because the schedule is what sets the date
           this money was promised by.

           A statement for somebody on a plan that showed only a single balance would
           leave out the only thing they were actually told - which amount is due on
           which date. */
        $planAssigns = PaymentPlanAssign::where('school_id', $student->school_id)
            ->where('active_status', 1)
            ->whereIn('fm_fees_invoice_id', $invoiceIds)
            ->with(['installments', 'planType'])
            ->get();

        $schedules = $planAssigns->map(function ($assign) use ($invoices) {
            $invoice = $invoices->firstWhere('id', $assign->fm_fees_invoice_id);
            $invPaid = $invoice ? (float) $invoice->invoiceDetails->sum('paid_amount') : 0.0;

            /* Computed from the lines already loaded, not from the trait's
               installmentSchedule() - that one re-reads the invoice and SUMs it per
               call, which on a bulk run is two queries per student for an amount
               sitting in memory. */
            return [
                'assign' => $assign,
                'invoice' => $invoice,
                'schedule' => $this->planScheduleFor($assign, max(0, $invPaid - (float) $assign->baseline_paid_amount)),
            ];
        })->keyBy(fn ($plan) => $plan['assign']->fm_fees_invoice_id);

        $today = now()->startOfDay();
        $assessed = 0.0;
        $waiver = 0.0;
        $fine = 0.0;
        $paid = 0.0;
        $overdueDays = null;

        /* Per invoice as well as in total: a statement that gives only one figure
           leaves whoever is being chased unable to see which charge it came from. */
        $breakdown = $invoices->map(function ($invoice) use (&$assessed, &$waiver, &$fine, &$paid, &$overdueDays, $today, $schedules) {
            $lines = $invoice->invoiceDetails;

            $invAssessed = (float) $lines->sum('amount');
            $invWaiver = (float) $lines->sum('weaver');
            $invFine = (float) $lines->sum('fine');
            $invPaid = (float) $lines->sum('paid_amount');
            $invBalance = ($invAssessed + $invFine) - ($invPaid + $invWaiver);

            $assessed += $invAssessed;
            $waiver += $invWaiver;
            $fine += $invFine;
            $paid += $invPaid;

            /* The same deadline the report ages against - the next unsettled
               instalment where a plan exists, the invoice's own due date otherwise.
               Read off the invoice here too and the statement would say eight days
               late under a report saying fifteen, which is the one thing a statement
               printed off a report must not do. */
            $plan = $schedules->get($invoice->id);
            $next = $plan ? $this->nextInstallmentOf($plan['schedule']) : null;
            $deadline = $plan ? ($next ? $next['due_date'] : null) : $invoice->due_date;

            if ($invBalance > 0 && $deadline) {
                $days = -1 * $today->diffInDays(\Carbon\Carbon::parse($deadline)->startOfDay(), false);
                $overdueDays = max($overdueDays ?? 0, $days);
            }

            return [
                'invoice' => $invoice,
                'lines' => $lines,
                'assessed' => $invAssessed,
                'waiver' => $invWaiver,
                'fine' => $invFine,
                'paid' => $invPaid,
                'balance' => $invBalance,
            ];
        });

        $net = ($assessed + $fine) - $waiver;
        $balance = $net - $paid;

        /* Arrears from years before this one, which the figures above cannot show:
           they are of the period being reported on. The same bulk call the report
           uses, so the note on a statement and the Prior Years column it was
           printed from are one answer rather than two.

           Never asked of a whole stay: every year is already in the figures above,
           so an arrears note would be the same debt stated twice. */
        $priorBalance = $criteria['scope'] === 'whole_stay'
            ? null
            : (float) ($this->priorBalancesFor(
                collect([$student->id]),
                $student->school_id,
                $criteria['academicId']
            )[$student->id] ?? 0);

        return [
            'student' => $student,
            'breakdown' => $breakdown,
            'schedules' => $schedules,
            'payments' => $payments,
            'assessed' => $assessed,
            'waiver' => $waiver,
            'fine' => $fine,
            'net' => $net,
            'paid' => $paid,
            'balance' => $balance,
            'prior_balance' => $priorBalance,
            'overdue_days' => $balance > 0 ? max(0, (int) ($overdueDays ?? 0)) : 0,
            'aging' => $balance > 0 ? $this->agingBucketOf($overdueDays) : null,
            'wallet' => $this->walletBalanceOf($student),
        ];
    }

    /**
     * What each of these students still owes from school years before the one
     * being reported on, keyed by student id.
     *
     * The trait has priorYearBalanceFor(), but it answers for one student in four
     * queries and reckons "prior" against the session's year rather than the year
     * in the criteria - so on a report of an older year it would answer about the
     * wrong one. This does the whole population in three, against the year asked
     * about, and totals the invoice lines with this report's own arithmetic rather
     * than summing the stored due_amount column, which only the payment flow
     * maintains.
     *
     * Signed, not clamped: a credit carried out of an earlier year is as real as a
     * debt, and the page decides which to show.
     */
    private function priorBalancesFor($studentIds, $schoolId, $academicId)
    {
        $current = SmAcademicYear::where('school_id', $schoolId)->find($academicId);

        if (!$current || $studentIds->isEmpty()) {
            return collect();
        }

        /* Later by start date, or - when two years carry the same one, which happens
           readily because the date is typed by hand and a year copied from another
           inherits its dates - by the order they were created in.

           Prior is then everything that is neither this year nor a later one, which
           is deliberately the wider way round. Listing the earlier years and asking
           for invoices in them misses any invoice whose academic year has since been
           deleted: this database has an invoice stamped academic_id 10 against a
           school whose years are 1, 11 and 15, and a student carrying 22,500 of it
           reported as owing nothing. An unknown year is treated as an old one,
           because that is overwhelmingly what it is. */
        $laterYearIds = SmAcademicYear::where('school_id', $schoolId)
            ->where('id', '!=', $current->id)
            ->where(function ($q) use ($current) {
                $q->where('starting_date', '>', $current->starting_date)
                    ->orWhere(function ($q2) use ($current) {
                        $q2->where('starting_date', $current->starting_date)
                            ->where('id', '>', $current->id);
                    });
            })
            ->pluck('id');

        $invoices = FmFeesInvoice::withoutGlobalScope(AcademicSchoolScope::class)
            ->where('school_id', $schoolId)
            ->whereIn('student_id', $studentIds)
            /* An invoice with no year at all is also not of this one. */
            ->where(function ($q) use ($academicId) {
                $q->whereNull('academic_id')->orWhere('academic_id', '!=', $academicId);
            })
            ->whereNotIn('academic_id', $laterYearIds)
            ->get(['id', 'student_id']);

        if ($invoices->isEmpty()) {
            return collect();
        }

        $totals = FmFeesInvoiceChield::whereIn('fees_invoice_id', $invoices->pluck('id'))
            ->selectRaw('fees_invoice_id,
                COALESCE(SUM(amount),0) amount,
                COALESCE(SUM(fine),0) fine,
                COALESCE(SUM(paid_amount),0) paid,
                COALESCE(SUM(weaver),0) weaver')
            ->groupBy('fees_invoice_id')
            ->get()
            ->keyBy('fees_invoice_id');

        return $invoices->groupBy('student_id')->map(function ($group) use ($totals) {
            $balance = 0.0;

            foreach ($group as $invoice) {
                $total = $totals->get($invoice->id);
                $balance += ((float) optional($total)->amount + (float) optional($total)->fine)
                    - ((float) optional($total)->paid + (float) optional($total)->weaver);
            }

            return round($balance, 2);
        });
    }

    /**
     * A plan's installments with each one's standing, worked out from an amount
     * already in hand rather than fetched.
     *
     * The trait's installmentSchedule() answers the same question, but reads
     * paidSoFar off $assign->invoice->Tpaidamount - a lazy load plus a SUM, per
     * student. That is affordable on the one-student ledger page it was written
     * for and not here, where this runs for every row of the report and every
     * statement of a bulk run. The arithmetic is the same waterfall, deliberately:
     * installment N is paid once cumulative payments reach the sum of 1..N.
     *
     * Payments are not tagged to installments anywhere -
     * payment_plan_installments holds only a number, a date and an amount - so
     * which installment a given receipt settled is not a question the data can
     * answer, and this does not pretend to.
     */
    private function planScheduleFor($assign, $paidSoFar)
    {
        $running = 0.0;
        $nextFound = false;

        return $assign->installments->values()->map(function ($installment) use (&$running, &$nextFound, $paidSoFar) {
            $before = $running;
            $running = round($running + (float) $installment->amount, 2);

            if ($paidSoFar >= $running - 0.01) {
                $status = 'paid';
            } elseif ($paidSoFar > $before) {
                $status = 'partial';
            } else {
                $status = 'unpaid';
            }

            /* How much of paidSoFar landed inside this installment's own band, so a
               part-paid one shows what is still owed on it rather than its whole
               scheduled amount. */
            $applied = max(0, min($paidSoFar, $running) - $before);

            $isNext = false;
            if (!$nextFound && $status !== 'paid') {
                $isNext = true;
                $nextFound = true;
            }

            return [
                'no' => $installment->installment_no,
                'due_date' => $installment->due_date,
                'amount' => (float) $installment->amount,
                'remaining' => round((float) $installment->amount - $applied, 2),
                'status' => $status,
                'is_next' => $isNext,
            ];
        });
    }

    /**
     * What a student on a plan actually owes next: the first installment not yet
     * settled. Null once the plan is paid off, which is what lets a finished plan
     * stop being chased.
     */
    private function nextInstallmentOf($schedule)
    {
        return $schedule->firstWhere('is_next', true);
    }

    /**
     * One student's wallet balance, guarded the same way the report's bulk lookup
     * is - the column belongs to the Wallet module and need not be there.
     */
    private function walletBalanceOf(SmStudent $student)
    {
        if (!$student->user_id || !Schema::hasColumn('users', 'wallet_balance')) {
            return 0.0;
        }

        return (float) DB::table('users')->where('id', $student->user_id)->value('wallet_balance');
    }

    /**
     * The criteria written out in words, for the heading of a printed sheet - a
     * page of figures is not worth much without saying which year and term it is of.
     */
    private function criteriaLabels(array $criteria)
    {
        $labels = [];

        /* Whole stay names no year or term, because it is of neither - saying one
           would make a sheet of every year a student has been here look like a
           sheet of one of them. */
        if ($criteria['scope'] === 'whole_stay') {
            /* academics.whole_stay, not a new key: the student's ledger page already
               calls this scope that, and two names for one thing is two to learn. */
            $labels[] = __('reports.fees_period') . ': ' . __('academics.whole_stay');
        } else {
            $year = SmAcademicYear::find($criteria['academicId']);
            if ($year) {
                $labels[] = __('reports.school_year') . ': ' . $year->year . ($year->title ? ' - ' . $year->title : '');
            }

            $labels[] = termLabel() . ': ' . ($criteria['semesterId']
                ? optional(Semester::find($criteria['semesterId']))->semester_name
                : __('reports.whole_year'));
        }

        if (!isBasicEd()) {
            $labels[] = __('academics.program') . ': ' . ($criteria['courseId']
                ? optional(Course::find($criteria['courseId']))->course_name
                : __('reports.all_programs'));
        }

        $labels[] = __('reports.col_charge_type') . ': ' . ($criteria['type']
            ? __('reports.fees_type_' . $criteria['type'])
            : __('reports.fees_type_all'));

        return array_filter($labels);
    }

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

        /* How wide a period is being asked about. The term picker carries this
           because all three are answers to the same question - which fees are in
           view - even though whole stay reaches past the year the picker beside it
           names, and so overrides it.

           The name is ledgerFor()'s: the student's own ledger page already offers
           semester, year and whole stay, and two names for one scope would be two
           things to learn. */
        $scope = $request->get('semester_id') === 'whole_stay'
            ? 'whole_stay'
            : ($request->filled('semester_id') ? 'semester' : 'year');

        /* No term chosen means the whole year, which is a real question to ask of
           this report and not merely a missing answer - so it stays empty rather
           than being filled in with the open term. */
        $semesterId = $scope === 'semester' ? (int) $request->semester_id : null;
        $courseId = $request->filled('course_id') ? (int) $request->course_id : null;

        /* Tuition and item-store purchases are both owed to the school, so both are
           counted by default - leaving the store out would report a receivable the
           cashier's drawer disagrees with. The filter is there because the two are
           chased differently. */
        $type = in_array($request->get('type'), ['fees', 'store'], true) ? $request->get('type') : null;

        return [
            'schoolId' => $schoolId,
            'scope' => $scope,
            'academicId' => $academicId,
            'semesterId' => $semesterId,
            'courseId' => $courseId,
            'type' => $type,
            'search' => trim((string) $request->get('search')),
            'lists' => compact('years', 'semesters', 'courses'),
        ];
    }

    /**
     * Resolve the population and price every student.
     *
     * Built from StudentRecord rather than from sm_students for the same reason the
     * student report is: a record is what ties a student to a school year, while
     * sm_students carries only where they stand today.
     *
     * For a year or a term, that year's records are the population and promoted
     * records are left out - they are the closed copy of a year already finished.
     * For a whole stay the opposite holds: the promoted copies are precisely the
     * earlier years being asked about, so every record a student has is taken and
     * the latest one stands for them in the table.
     *
     * Everything is fetched in one query per table and matched in memory. The
     * module's own reports read the invoice totals off accessors instead, which is
     * five SUM queries per invoice, and the reason none of them can be totalled.
     */
    private function classify(array $criteria)
    {
        $schoolId = $criteria['schoolId'];
        $academicId = $criteria['academicId'];
        $wholeStay = $criteria['scope'] === 'whole_stay';

        $records = StudentRecord::where('school_id', $schoolId)
            ->when(!$wholeStay, fn ($q) => $q->where('academic_id', $academicId)->where('is_promote', 0))
            ->with(['studentDetail.course', 'class'])
            ->get()
            /* studentDetail() is a withDefault() relation, so a record whose student
               row is gone hands back a blank model rather than null - guarding on ->id
               is what actually excludes those. */
            ->filter(fn ($r) => optional($r->studentDetail)->id);

        if ($criteria['courseId']) {
            $records = $records->filter(fn ($r) => $r->studentDetail->course_id == $criteria['courseId']);
        }

        /* One row per student, not per record. Over a single year the two are the
           same thing, but a stay spans a record per year - left as records, a
           four-year student would appear four times and their balance would be
           counted four times with it.

           The newest record stands for them, because the table's programme and year
           level columns are asking where this student is now. */
        if ($wholeStay) {
            $records = $records->sortBy('academic_id')
                ->groupBy('student_id')
                ->map(fn ($forStudent) => $forStudent->last())
                ->values();
        }

        /* Scope stripped because the model pins itself to the session's year and this
           report is asked about any of them. Staff purchases are left out: an invoice
           carries either a student or a staff member (see
           EnrollmentInvoicing::newStaffOrderInvoice()), and what a staff member owes
           the item store is not a student receivable.

           Grouped, not keyed: a student can hold more than one invoice in a year -
           the enrolment one, and a separate one per later store checkout - and what
           they owe is all of them together.

           Matched on the record for a year or a term, and on the student for a whole
           stay. The record is the tighter match and stays the default so those
           figures do not shift; but over a stay it would miss an invoice whose
           record has since been promoted away, which is most of what a whole stay
           is asking for. */
        $invoices = FmFeesInvoice::withoutGlobalScope(AcademicSchoolScope::class)
            ->where('school_id', $schoolId)
            ->whereNotNull('student_id')
            ->when(!$wholeStay, fn ($q) => $q->where('academic_id', $academicId)
                ->whereIn('record_id', $records->pluck('id')))
            ->when($wholeStay, fn ($q) => $q->whereIn('student_id', $records->pluck('student_id')))
            ->when($criteria['type'], fn ($q) => $q->where('type', $criteria['type']))
            ->get()
            ->groupBy($wholeStay ? 'student_id' : 'record_id');

        $invoiceIds = $invoices->flatten()->pluck('id');

        /* Billed, waived, fined and paid summed per invoice in one query - the same
           call StudentReportController makes. The stored payment_status is a cached
           summary that goes stale the moment anything moves the lines by another
           route, so the lines are what decides. */
        $totals = FmFeesInvoiceChield::whereIn('fees_invoice_id', $invoiceIds)
            ->selectRaw('fees_invoice_id,
                COALESCE(SUM(amount),0) amount,
                COALESCE(SUM(fine),0) fine,
                COALESCE(SUM(paid_amount),0) paid,
                COALESCE(SUM(weaver),0) weaver')
            ->groupBy('fees_invoice_id')
            ->get()
            ->keyBy('fees_invoice_id');

        /* The plans attached to those invoices, with their schedules, in one query.
           Keyed on fm_fees_invoice_id because that is the live link -
           payment_plan_assign_id on the invoice is a leftover from an earlier
           one-invoice-per-installment design and is never set (see
           FmFeesInvoice::paymentPlanAssign()). */
        $plans = PaymentPlanAssign::where('school_id', $schoolId)
            ->where('active_status', 1)
            ->whereIn('fm_fees_invoice_id', $invoiceIds)
            ->with('installments')
            ->get()
            ->keyBy('fm_fees_invoice_id');

        /* What the school is holding for each student. Wallet money is a liability,
           not a receipt: it only becomes one once applied to an invoice, and until
           then the student both holds a balance here and owes one there. It is
           carried so the page can say so, and deliberately never netted off the
           receivable.

           users.wallet_balance is added by the Wallet module's own migration, behind
           a hasColumn guard - so on a school running without that module the column
           is simply absent, and asking for it would take the whole report down over
           a figure that is zero anyway. */
        $wallets = Schema::hasColumn('users', 'wallet_balance')
            ? DB::table('users')
                ->whereIn('id', $records->pluck('studentDetail.user_id')->filter()->unique())
                ->pluck('wallet_balance', 'id')
            : collect();

        /* Arrears carried in from earlier years, which the figures of this period
           cannot show - and without which a collection report says zero against a
           student who owes thousands. Not asked over a whole stay: every year is
           already in the figures, so it would be the same debt counted twice. */
        $priorBalances = $wholeStay
            ? collect()
            : $this->priorBalancesFor($records->pluck('student_id')->unique(), $schoolId, $academicId);

        $today = now()->startOfDay();

        $rows = $records->map(function ($record) use ($invoices, $totals, $plans, $wallets, $priorBalances, $today, $wholeStay) {
            $student = $record->studentDetail;
            /* Keyed as the grouping above was - by student over a stay, by record
               otherwise. */
            $own = $invoices->get($wholeStay ? $student->id : $record->id, collect());

            $assessed = 0.0;
            $waiver = 0.0;
            $fine = 0.0;
            $paid = 0.0;
            $overdueDays = null;
            $nextDue = null;
            $nextDueAmount = 0.0;
            $onPlan = false;
            $buckets = $this->emptyBuckets();

            foreach ($own as $invoice) {
                $total = $totals->get($invoice->id);

                $invAssessed = (float) optional($total)->amount;
                $invWaiver = (float) optional($total)->weaver;
                $invFine = (float) optional($total)->fine;
                $invPaid = (float) optional($total)->paid;

                $assessed += $invAssessed;
                $waiver += $invWaiver;
                $fine += $invFine;
                $paid += $invPaid;

                /* Same arithmetic the invoice itself settles on - see
                   EnrollmentInvoicing. The module's balance report leaves the waiver
                   out of this, which is why its figure runs high. */
                $invBalance = ($invAssessed + $invFine) - ($invPaid + $invWaiver);

                /* The date this money was actually promised by.

                   An invoice's own due_date is three days after enrolment for fees
                   and seven for a store order (see EnrollmentInvoicing) - a checkout
                   window, not a deadline anyone agreed to. Aged off that, every
                   unpaid balance is months overdue by mid-term and the buckets say
                   nothing.

                   Where a plan exists, the next unsettled installment's due_date is
                   the date the school actually gave the family, so that is what the
                   balance is aged against. Cash payers still fall back to the
                   invoice. */
                $plan = $plans->get($invoice->id);
                $schedule = null;

                if ($plan) {
                    $onPlan = true;
                    $paidSoFar = max(0, $invPaid - (float) $plan->baseline_paid_amount);
                    $schedule = ['schedule' => $this->planScheduleFor($plan, $paidSoFar)];
                    $next = $this->nextInstallmentOf($schedule['schedule']);

                    if ($next) {
                        /* The soonest unmet promise across the student's plans, which is
                           the one worth a call. */
                        if ($nextDue === null || $next['due_date'] < $nextDue) {
                            $nextDue = $next['due_date'];
                            $nextDueAmount = $next['remaining'];
                        }
                    }

                    $deadline = $next ? $next['due_date'] : null;
                } else {
                    $deadline = $invoice->due_date;
                }

                /* Aged off the oldest promise still unmet, not off the student's
                   newest one: how long the money has been outstanding is the
                   question, and the newest would always answer "current". */
                if ($invBalance > 0 && $deadline) {
                    $days = -1 * $today->diffInDays(\Carbon\Carbon::parse($deadline)->startOfDay(), false);
                    $overdueDays = max($overdueDays ?? 0, $days);
                }

                /* Each invoice's balance spread over the buckets and added in, so a
                   student holding several invoices is aged across all of them rather
                   than off whichever happened to be oldest. */
                foreach ($this->agingSplitFor($invBalance, $schedule, $invoice->due_date, $today) as $bucket => $amount) {
                    $buckets[$bucket] = round($buckets[$bucket] + $amount, 2);
                }
            }

            $net = ($assessed + $fine) - $waiver;
            $balance = $net - $paid;

            return [
                'student' => $student,
                'record' => $record,
                'invoice_count' => $own->count(),
                'assessed' => $assessed,
                'waiver' => $waiver,
                'fine' => $fine,
                'net' => $net,
                'paid' => $paid,
                'balance' => $balance,
                'wallet' => (float) ($wallets[$student->user_id] ?? 0),
                'status' => $this->paymentStatusOf($own->count(), $paid, $balance),
                /* The balance split across the buckets, which is what the Aging card
                   totals; the single bucket below is only the worst one this student
                   is in, for the badge on their row. */
                'aging_buckets' => $buckets,
                'aging' => $balance > 0 ? $this->agingBucketOf($overdueDays) : null,
                'overdue_days' => $balance > 0 ? max(0, (int) ($overdueDays ?? 0)) : 0,
                /* Reported beside this period's balance, never added into it: the
                   two are of different periods, and one figure covering both could
                   not be reconciled against either. */
                'prior_balance' => (float) ($priorBalances[$student->id] ?? 0),
                'on_plan' => $onPlan,
                /* Null once every installment is settled, which is how a finished
                   plan stops being chased. */
                'next_due' => $nextDue,
                'next_due_amount' => $nextDueAmount,
                /* Which term this student sits in. The invoice is the stamped answer;
                   where none has been raised yet, registration has already written the
                   term onto the student, so that answers instead. */
                'semester_id' => optional($own->first())->semester_id ?: $student->semester_id,
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

        /* Largest debt first: this is a collection report, and the name at the top is
           the one worth a phone call. Students square with the school fall to the
           bottom, in name order among themselves. */
        return $rows->sort(function ($a, $b) {
            return $b['balance'] <=> $a['balance']
                ?: strcmp((string) $a['student']->full_name, (string) $b['student']->full_name);
        })->values();
    }

    /** Nothing billed is not the same as nothing owed, so it is its own answer. */
    private function paymentStatusOf($invoiceCount, $paid, $balance)
    {
        if ($invoiceCount === 0) {
            return 'no_invoice';
        }

        if ($balance <= 0) {
            return 'paid';
        }

        return $paid > 0 ? 'partial' : 'unpaid';
    }

    /**
     * How long the oldest owing invoice has been due. A balance with no due date on
     * it anywhere cannot be aged, so it is reported as current rather than dropped -
     * leaving it out would make the buckets disagree with the outstanding total
     * above them.
     */
    private function agingBucketOf($overdueDays)
    {
        if ($overdueDays === null || $overdueDays <= 0) {
            return 'aging_current';
        }

        foreach (self::AGING_BUCKETS as $bucket => $limit) {
            if ($overdueDays <= $limit) {
                return $bucket;
            }
        }

        return 'aging_90_plus';
    }

    /** Every bucket at zero, so a row always carries the full set to add into. */
    private function emptyBuckets()
    {
        return array_fill_keys(array_merge(
            ['aging_current'],
            array_keys(self::AGING_BUCKETS),
            ['aging_90_plus']
        ), 0.0);
    }

    /** Which bucket a date falls in, measured from today. */
    private function bucketForDate($date, $today)
    {
        if (!$date) {
            return 'aging_current';
        }

        return $this->agingBucketOf(
            -1 * $today->diffInDays(\Carbon\Carbon::parse($date)->startOfDay(), false)
        );
    }

    /**
     * Spread one invoice's balance across the aging buckets.
     *
     * A single bucket per student is the wrong answer wherever a plan exists. Take
     * a balance of 1,000 made of one instalment 15 days late for 250 and another
     * not due for another month for 750: bucketed off the oldest unmet date, the
     * whole 1,000 reports as overdue, and the figure a school would act on is four
     * times the money actually late.
     *
     * So the balance is walked down the unsettled instalments in order, each taking
     * up to what it still owes and landing in the bucket of its own due date.
     * Anything left when the schedule runs out - a fine added after the plan was
     * drawn up, or a plan whose total no longer matches the invoice - goes to the
     * oldest bucket used rather than being dropped, because the buckets have to add
     * back up to the balance or they cannot be reconciled against it.
     *
     * Without a plan there is one date and one bucket, which is what the invoice's
     * own due date can honestly support.
     */
    private function agingSplitFor($balance, $plan, $invoiceDueDate, $today)
    {
        $buckets = $this->emptyBuckets();

        if ($balance <= 0) {
            return $buckets;
        }

        if (!$plan) {
            $buckets[$this->bucketForDate($invoiceDueDate, $today)] = round($balance, 2);

            return $buckets;
        }

        $left = $balance;
        $lastBucket = null;

        foreach ($plan['schedule'] as $installment) {
            if ($left <= 0.009) {
                break;
            }

            if ($installment['remaining'] <= 0) {
                continue;
            }

            $take = min($left, $installment['remaining']);
            $bucket = $this->bucketForDate($installment['due_date'], $today);

            $buckets[$bucket] = round($buckets[$bucket] + $take, 2);
            $left = round($left - $take, 2);
            $lastBucket = $lastBucket ?: $bucket;
        }

        if ($left > 0.009) {
            $bucket = $lastBucket ?: $this->bucketForDate($invoiceDueDate, $today);
            $buckets[$bucket] = round($buckets[$bucket] + $left, 2);
        }

        return $buckets;
    }

    /**
     * Both halves of every figure on the page: the money, and how many students it
     * is spread across. A peso total on its own does not say whether it is one
     * family or forty.
     */
    private function summarise($rows)
    {
        $sum = fn ($key) => round((float) $rows->sum($key), 2);

        $summary = [
            'students' => $rows->count(),
            'assessed' => $sum('assessed'),
            'waiver' => $sum('waiver'),
            'fine' => $sum('fine'),
            'net' => $sum('net'),
            'collected' => $sum('paid'),
            /* Credit balances are a real thing - an overpayment, or a waiver granted
               after payment - but they are not money anyone can collect, so they are
               reported beside the receivable rather than quietly reducing it. */
            'outstanding' => round((float) $rows->where('balance', '>', 0)->sum('balance'), 2),
            'credit' => round(abs((float) $rows->where('balance', '<', 0)->sum('balance')), 2),
            'wallet' => $sum('wallet'),
        ];

        $summary['collection_rate'] = $summary['net'] > 0
            ? round($summary['collected'] / $summary['net'] * 100, 1)
            : 0;

        foreach (['paid', 'partial', 'unpaid', 'no_invoice'] as $status) {
            $summary['count_' . $status] = $rows->where('status', $status)->count();
        }

        /* Summed off each row's split, not off one bucket per student - so a
           balance that is part late and part not due reports as both, and the five
           buckets add back up to outstanding. */
        foreach (array_keys($this->emptyBuckets()) as $bucket) {
            $summary[$bucket] = round((float) $rows->sum(fn ($r) => $r['aging_buckets'][$bucket] ?? 0), 2);
            $summary['count_' . $bucket] = $rows->filter(fn ($r) => ($r['aging_buckets'][$bucket] ?? 0) > 0)->count();
        }

        $summary['count_all'] = $rows->count();
        $summary['count_assessed'] = $rows->where('invoice_count', '>', 0)->count();
        $summary['count_net'] = $summary['count_assessed'];
        $summary['count_waiver'] = $rows->where('waiver', '>', 0)->count();
        $summary['count_fine'] = $rows->where('fine', '>', 0)->count();
        $summary['count_collected'] = $rows->where('paid', '>', 0)->count();
        $summary['count_outstanding'] = $rows->where('balance', '>', 0)->count();
        $summary['count_wallet'] = $rows->where('wallet', '>', 0)->count();

        /* Who is on an instalment plan, and of those, who has let a dated promise
           pass. The second is the one worth acting on: a plan that is being kept is
           a balance nobody needs to chase. */
        $onPlan = $rows->where('on_plan', true);
        $summary['on_plan'] = round((float) $onPlan->where('balance', '>', 0)->sum('balance'), 2);
        $summary['count_on_plan'] = $onPlan->count();

        $notOnPlan = $rows->where('on_plan', false);
        $summary['not_on_plan'] = round((float) $notOnPlan->where('balance', '>', 0)->sum('balance'), 2);
        $summary['count_not_on_plan'] = $notOnPlan->count();

        $planOverdue = $onPlan->filter(fn ($r) => $r['balance'] > 0 && $r['overdue_days'] > 0);
        $summary['plan_overdue'] = round((float) $planOverdue->sum('next_due_amount'), 2);
        $summary['count_plan_overdue'] = $planOverdue->count();

        /* Only what is owed. A credit carried out of an earlier year is kept out of
           the arrears figure for the same reason a credit balance is kept out of
           outstanding: it is not money anyone can collect. */
        $withArrears = $rows->where('prior_balance', '>', 0);
        $summary['prior_balance'] = round((float) $withArrears->sum('prior_balance'), 2);
        $summary['count_prior_balance'] = $withArrears->count();

        return $summary;
    }

    /** The counterpart of summarise(): one figure, the rows it was counted from. */
    private function filterByMetric($rows, $metric)
    {
        switch ($metric) {
            case 'all':
                return $rows;
            case 'assessed':
            case 'net':
                return $rows->where('invoice_count', '>', 0)->values();
            case 'waiver':
                return $rows->where('waiver', '>', 0)->values();
            case 'fine':
                return $rows->where('fine', '>', 0)->values();
            case 'collected':
                return $rows->where('paid', '>', 0)->values();
            case 'outstanding':
                return $rows->where('balance', '>', 0)->values();
            case 'wallet':
                return $rows->where('wallet', '>', 0)->values();
            case 'on_plan':
                return $rows->where('on_plan', true)->values();
            case 'not_on_plan':
                return $rows->where('on_plan', false)->values();
            case 'plan_overdue':
                return $rows->where('on_plan', true)
                    ->filter(fn ($r) => $r['balance'] > 0 && $r['overdue_days'] > 0)
                    ->values();
            case 'prior_balance':
                return $rows->where('prior_balance', '>', 0)->values();
            case 'aging_current':
            case 'aging_1_30':
            case 'aging_31_60':
            case 'aging_61_90':
            case 'aging_90_plus':
                /* Everyone with money in that bucket, which after the split is no
                   longer the same as everyone whose worst bucket it is. */
                return $rows->filter(fn ($r) => ($r['aging_buckets'][$metric] ?? 0) > 0)->values();
            case 'paid':
            case 'partial':
            case 'unpaid':
            case 'no_invoice':
                return $rows->where('status', $metric)->values();
            default:
                return $rows->where('aging', $metric)->values();
        }
    }
}
