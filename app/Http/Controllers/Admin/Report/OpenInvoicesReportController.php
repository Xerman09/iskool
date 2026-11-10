<?php

namespace App\Http\Controllers\Admin\Report;

use App\Course;
use App\SmStudent;
use App\SmAcademicYear;
use App\PaymentPlanAssign;
use App\Scopes\AcademicSchoolScope;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Modules\Fees\Entities\FmFeesInvoice;
use Modules\Fees\Entities\FmFeesTransaction;
use Modules\Fees\Entities\FmFeesInvoiceChield;
use Illuminate\Support\Facades\Log;

/**
 * Every invoice still owing, one line each, with what is left on it.
 *
 * The receivables trial balance the other reports could not give. The standard
 * fees report is per student and answers "who owes"; this is per invoice and
 * answers "what is open" - how many documents, raised when, due when, and for
 * how much. They are the same money read at different grains, and the
 * outstanding totals agree.
 *
 * Read as at a date by the same means the fees report uses: invoices raised
 * after it are excluded and payments are taken from the dated receipts up to
 * it, because the paid_amount on a line is a running total with no date on it
 * and can only ever answer "now".
 */
class OpenInvoicesReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('PM');
    }

    /** Days overdue at which each bucket ends, as the fees report draws them. */
    private const AGING_BUCKETS = [
        'aging_1_30' => 30,
        'aging_31_60' => 60,
        'aging_61_90' => 90,
    ];

    private const METRICS = [
        'all', 'billed', 'collected', 'outstanding', 'unbilled',
        'aging_current', 'aging_1_30', 'aging_31_60', 'aging_61_90', 'aging_90_plus',
        'fees', 'store', 'on_plan',
    ];

    public function index(Request $request)
    {
        try {
            $criteria = $this->criteria($request);
            $rows = $this->classify($criteria);

            return view('backEnd.reports.openInvoicesReport', array_merge($criteria['lists'], [
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

    public function metricList(Request $request)
    {
        $request->validate(['metric' => 'required|in:' . implode(',', self::METRICS)]);

        try {
            $criteria = $this->criteria($request);
            $rows = $this->filterByMetric($this->classify($criteria), $request->metric);

            $data = [
                'rows' => $rows,
                'metric' => $request->metric,
                'title' => __('reports.open_metric_' . $request->metric),
                'totals' => $this->summarise($rows),
                'labels' => $this->criteriaLabels($criteria),
                'printUrl' => route('open-invoices-report-metric', array_merge(
                    $request->except('print'),
                    ['print' => 1]
                )),
            ];

            if ($request->boolean('print')) {
                return view('backEnd.reports.openInvoicesReportPrint', $data);
            }

            return view('backEnd.reports.partials.openInvoicesReportList', $data)->render();
        } catch (\Exception $e) {
            Log::error(__METHOD__ . ': ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['msg' => __('common.Operation failed')], 500);
        }
    }

    private function criteriaLabels(array $criteria)
    {
        $labels = [];

        $year = SmAcademicYear::find($criteria['academicId']);
        if ($year) {
            $labels[] = __('reports.school_year') . ': ' . $year->year . ($year->title ? ' - ' . $year->title : '');
        }

        $labels[] = __('reports.fees_as_of') . ': '
            . date('M d, Y', strtotime($criteria['asOf'] ?: date('Y-m-d')));

        if (!isBasicEd()) {
            $labels[] = __('academics.program') . ': ' . ($criteria['courseId']
                ? optional(Course::find($criteria['courseId']))->course_name
                : __('reports.all_programs'));
        }

        $labels[] = __('reports.open_shows') . ': ' . ($criteria['includeSettled']
            ? __('reports.open_all_invoices')
            : __('reports.open_only_open'));

        return array_filter($labels);
    }

    private function criteria(Request $request)
    {
        $schoolId = auth()->user()->school_id;

        $years = SmAcademicYear::where('school_id', $schoolId)->orderByDesc('id')->get();
        $courses = Course::selectable()->where('school_id', $schoolId)->orderBy('course_name')->get();

        $academicId = $request->filled('academic_id')
            ? (int) $request->academic_id
            : (int) getAcademicId();

        $asOf = $this->parseDate($request->get('as_of'));
        if ($asOf && $asOf > date('Y-m-d')) {
            $asOf = null;
        }

        return [
            'schoolId' => $schoolId,
            'academicId' => $academicId,
            'courseId' => $request->filled('course_id') ? (int) $request->course_id : null,
            'type' => in_array($request->get('type'), ['fees', 'store'], true) ? $request->get('type') : null,
            'asOf' => $asOf,
            /* A trial balance is of what is open, so settled invoices are out by
               default. They are worth being able to see - a reconciliation asks what
               was raised, not only what is left - so the switch is there. */
            'includeSettled' => $request->boolean('include_settled'),
            'search' => trim((string) $request->get('search')),
            'lists' => compact('years', 'courses'),
        ];
    }

    private function parseDate($value)
    {
        if (!$value) {
            return null;
        }

        $time = strtotime($value);

        return $time ? date('Y-m-d', $time) : null;
    }

    /**
     * One row per invoice.
     *
     * Five queries whatever the size of the school: the invoices, their lines
     * summed in the database, the dated receipts where a past date is being read
     * at, the students, and the plans. Nothing is asked per invoice.
     */
    private function classify(array $criteria)
    {
        $schoolId = $criteria['schoolId'];

        $invoices = FmFeesInvoice::withoutGlobalScope(AcademicSchoolScope::class)
            ->where('school_id', $schoolId)
            ->where('academic_id', $criteria['academicId'])
            ->whereNotNull('student_id')
            ->when($criteria['type'], fn ($q) => $q->where('type', $criteria['type']))
            /* An invoice raised after the date being read at did not exist then. */
            ->when($criteria['asOf'], fn ($q) => $q->whereDate('create_date', '<=', $criteria['asOf']))
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();

        if ($invoices->isEmpty()) {
            return collect();
        }

        $invoiceIds = $invoices->pluck('id');

        $totals = FmFeesInvoiceChield::whereIn('fees_invoice_id', $invoiceIds)
            ->selectRaw('fees_invoice_id,
                COALESCE(SUM(amount),0) amount,
                COALESCE(SUM(fine),0) fine,
                COALESCE(SUM(paid_amount),0) paid,
                COALESCE(SUM(weaver),0) weaver')
            ->groupBy('fees_invoice_id')
            ->get()
            ->keyBy('fees_invoice_id');

        $paidAsOf = $criteria['asOf'] ? $this->paidAsOf($invoiceIds, $criteria['asOf']) : null;

        /* Whole models: full_name on SmStudent is an accessor over first_name and
           last_name, so selecting the column of that name hands back a single
           space - truthy, so every fallback for a missing name steps aside. */
        $students = SmStudent::withoutGlobalScope(\App\Scopes\ActiveStatusSchoolScope::class)
            ->where('school_id', $schoolId)
            ->whereIn('id', $invoices->pluck('student_id')->unique())
            ->with('course')
            ->get()
            ->keyBy('id');

        /* With their schedules, not just their ids: where a plan exists the date this
           money was promised by is the next unsettled instalment, not the invoice's
           own due date - which is three days after enrolment and never a deadline
           anyone agreed to. The standard fees report ages the same way, and read off
           the invoice here this page would say nine days late under a report saying
           fifteen. */
        $plans = PaymentPlanAssign::where('school_id', $schoolId)
            ->where('active_status', 1)
            ->whereIn('fm_fees_invoice_id', $invoiceIds)
            ->with('installments')
            ->get()
            ->keyBy('fm_fees_invoice_id');

        $today = now()->startOfDay();
        $readAt = $criteria['asOf'] ? \Carbon\Carbon::parse($criteria['asOf'])->startOfDay() : $today;

        $rows = $invoices->map(function ($invoice) use ($totals, $paidAsOf, $students, $plans, $readAt) {
            $total = $totals->get($invoice->id);
            $student = $students->get($invoice->student_id);

            $billed = (float) optional($total)->amount;
            $waiver = (float) optional($total)->weaver;
            $fine = (float) optional($total)->fine;
            $paid = $paidAsOf !== null
                ? (float) ($paidAsOf[$invoice->id] ?? 0)
                : (float) optional($total)->paid;

            $balance = ($billed + $fine) - ($paid + $waiver);

            $plan = $plans->get($invoice->id);
            $deadline = $plan
                ? $this->nextInstallmentDueOf($plan, $paid)
                : $invoice->due_date;

            /* Measured from the date being read at, not from today - an invoice 40
               days late at a month end is not 70 days late because the report is
               being run now. */
            $overdue = $deadline
                ? -1 * $readAt->diffInDays(\Carbon\Carbon::parse($deadline)->startOfDay(), false)
                : 0;

            return [
                'invoice' => $invoice,
                'student' => $student,
                'course_name' => optional(optional($student)->course)->course_name,
                'billed' => $billed,
                'waiver' => $waiver,
                'fine' => $fine,
                'paid' => $paid,
                'balance' => round($balance, 2),
                'overdue_days' => $balance > 0 ? max(0, (int) $overdue) : 0,
                'aging' => $balance > 0 ? $this->agingBucketOf($balance > 0 ? $overdue : null) : null,
                'on_plan' => (bool) $plan,
                'next_due' => $plan ? $deadline : null,
                /* Raised but with nothing on it yet - a document that bills nothing
                   is not a receivable, and is worth seeing precisely because it
                   should not be sitting there. */
                'unbilled' => $billed <= 0,
            ];
        });

        if (!$criteria['includeSettled']) {
            $rows = $rows->filter(fn ($r) => $r['balance'] > 0 || $r['unbilled']);
        }

        if ($criteria['courseId']) {
            $rows = $rows->filter(fn ($r) => optional($r['student'])->course_id == $criteria['courseId']);
        }

        if ($criteria['search'] !== '') {
            $needle = mb_strtolower($criteria['search']);
            $rows = $rows->filter(function ($r) use ($needle) {
                return str_contains(mb_strtolower((string) optional($r['student'])->full_name), $needle)
                    || str_contains(mb_strtolower((string) optional($r['student'])->admission_no), $needle)
                    || str_contains(mb_strtolower((string) $r['invoice']->invoice_id), $needle);
            });
        }

        /* Oldest due first: a trial balance is read from the stalest document down. */
        return $rows->values();
    }

    /**
     * The due date of the first instalment this plan has not settled, or null once
     * the plan is paid off.
     *
     * The same waterfall the fees report runs and the ledger page describes:
     * instalment N is settled once cumulative payments reach the sum of 1..N.
     * Payments are not tagged to instalments anywhere, so which receipt settled
     * which one is not a question the data can answer, and this does not ask it.
     */
    private function nextInstallmentDueOf($plan, $paid)
    {
        $paidSoFar = max(0, $paid - (float) $plan->baseline_paid_amount);
        $running = 0.0;

        foreach ($plan->installments as $installment) {
            $running = round($running + (float) $installment->amount, 2);

            if ($paidSoFar < $running - 0.01) {
                return $installment->due_date;
            }
        }

        return null;
    }

    /** What had been paid against each invoice as at a date - see the fees report. */
    private function paidAsOf($invoiceIds, $asOf)
    {
        return FmFeesTransaction::withoutGlobalScope(AcademicSchoolScope::class)
            ->whereIn('fm_fees_transactions.fees_invoice_id', $invoiceIds)
            ->whereDate('fm_fees_transactions.created_at', '<=', $asOf)
            ->join('fm_fees_transaction_chields as c', 'c.fees_transaction_id', '=', 'fm_fees_transactions.id')
            ->groupBy('fm_fees_transactions.fees_invoice_id')
            ->selectRaw('fm_fees_transactions.fees_invoice_id, COALESCE(SUM(c.paid_amount),0) paid')
            ->pluck('paid', 'fees_invoice_id');
    }

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

    private function summarise($rows)
    {
        $open = $rows->where('balance', '>', 0);

        $summary = [
            'all' => $rows->count(),
            'count_all' => $rows->count(),
            'billed' => round((float) $rows->sum('billed'), 2),
            'collected' => round((float) $rows->sum('paid'), 2),
            'outstanding' => round((float) $open->sum('balance'), 2),
            'count_billed' => $rows->where('billed', '>', 0)->count(),
            'count_collected' => $rows->where('paid', '>', 0)->count(),
            /* How many documents are open, which is the figure the count of a trial
               balance means - not how many students. */
            'count_outstanding' => $open->count(),
        ];

        $unbilled = $rows->where('unbilled', true);
        $summary['unbilled'] = 0.0;
        $summary['count_unbilled'] = $unbilled->count();

        foreach (['aging_current', 'aging_1_30', 'aging_31_60', 'aging_61_90', 'aging_90_plus'] as $bucket) {
            $inBucket = $open->where('aging', $bucket);
            $summary[$bucket] = round((float) $inBucket->sum('balance'), 2);
            $summary['count_' . $bucket] = $inBucket->count();
        }

        foreach (['fees', 'store'] as $type) {
            $ofType = $open->filter(fn ($r) => $r['invoice']->type === $type);
            $summary[$type] = round((float) $ofType->sum('balance'), 2);
            $summary['count_' . $type] = $ofType->count();
        }

        $onPlan = $open->where('on_plan', true);
        $summary['on_plan'] = round((float) $onPlan->sum('balance'), 2);
        $summary['count_on_plan'] = $onPlan->count();

        /* Students behind the open documents, so the page can say whether 40 open
           invoices are 40 families or four. */
        $summary['students'] = $open->pluck('student.id')->filter()->unique()->count();

        return $summary;
    }

    private function filterByMetric($rows, $metric)
    {
        switch ($metric) {
            case 'all':
            case 'billed':
                return $rows;
            case 'collected':
                return $rows->where('paid', '>', 0)->values();
            case 'outstanding':
                return $rows->where('balance', '>', 0)->values();
            case 'unbilled':
                return $rows->where('unbilled', true)->values();
            case 'fees':
            case 'store':
                return $rows->where('balance', '>', 0)
                    ->filter(fn ($r) => $r['invoice']->type === $metric)
                    ->values();
            case 'on_plan':
                return $rows->where('balance', '>', 0)->where('on_plan', true)->values();
            default:
                return $rows->where('balance', '>', 0)->where('aging', $metric)->values();
        }
    }
}
