<?php

namespace App\Http\Controllers\Admin\Report;

use App\SmStaff;
use App\SmStudent;
use App\SmAddIncome;
use App\SmAddExpense;
use App\SmIncomeHead;
use App\SmExpenseHead;
use App\SmHrPayrollGenerate;
use App\Scopes\AcademicSchoolScope;
use App\Scopes\ActiveStatusSchoolScope;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Modules\Fees\Entities\FmFeesInvoice;
use Modules\Fees\Entities\FmFeesTransaction;
use Modules\Fees\Entities\FmFeesInvoiceChield;
use Modules\Fees\Entities\FmFeesTransactionChield;
use Illuminate\Support\Facades\Log;

/**
 * Everything that happened to money in a period, in the order it happened.
 *
 * The other reports each answer one question at one grain - who owes, what is
 * open, what came in, what the books say. This is the thread running through
 * them: an invoice raised, a line added to it afterwards, a receipt taken, an
 * expense recorded, a wage paid, all on one timeline.
 *
 * What it cannot say is who did any of it. Neither fm_fees_invoice_chields nor
 * fm_fees_weavers carries a created_by, so the page reports what happened and
 * when, and stops there rather than guessing. Adding those columns is a small
 * migration and would turn this into a full audit trail.
 *
 * Fee collections post an income row of their own (SmFeesController and the
 * Fees module both write one, keyed to the receipt by fees_collection_id), so
 * the same money reaches this page twice by two routes. The posting is shown
 * against its receipt and kept out of the income total, because a timeline that
 * silently counts a payment as income as well would make the day look twice as
 * good as it was.
 */
class ActivityAuditReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('PM');
    }

    /** The kinds of event this timeline carries, in the order the cards read them. */
    private const KINDS = [
        'invoice_raised', 'line_added', 'payment', 'income', 'expense', 'payroll',
    ];

    private const METRICS = ['all', 'billing', 'money_in', 'money_out'];

    public function index(Request $request)
    {
        try {
            $criteria = $this->criteria($request);
            $rows = $this->classify($criteria);

            return view('backEnd.reports.activityAuditReport', [
                'criteria' => $criteria,
                'rows' => $rows,
                'summary' => $this->summarise($rows),
            ]);
        } catch (\Exception $e) {
            Log::error(__METHOD__ . ': ' . $e->getMessage(), ['exception' => $e]);
            Toastr::error('Operation Failed', 'Failed');
            return redirect()->back();
        }
    }

    public function metricList(Request $request)
    {
        $request->validate([
            'metric' => ['required', function ($attribute, $value, $fail) {
                if (!in_array($value, self::METRICS, true) && !in_array($value, self::KINDS, true)) {
                    $fail(__('validation.in', ['attribute' => $attribute]));
                }
            }],
        ]);

        try {
            $criteria = $this->criteria($request);
            $rows = $this->filterByMetric($this->classify($criteria), $request->metric);

            $data = [
                'rows' => $rows,
                'metric' => $request->metric,
                'title' => __('reports.activity_metric_' . $request->metric),
                'totals' => $this->summarise($rows),
                'labels' => $this->criteriaLabels($criteria),
                'printUrl' => route('activity-audit-report-metric', array_merge(
                    $request->except('print'),
                    ['print' => 1]
                )),
            ];

            if ($request->boolean('print')) {
                return view('backEnd.reports.activityAuditReportPrint', $data);
            }

            return view('backEnd.reports.partials.activityAuditReportList', $data)->render();
        } catch (\Exception $e) {
            Log::error(__METHOD__ . ': ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['msg' => __('common.Operation failed')], 500);
        }
    }

    private function criteriaLabels(array $criteria)
    {
        return array_filter([
            __('reports.accounts_period') . ': '
                . date('M d, Y', strtotime($criteria['dateFrom']))
                . ' - ' . date('M d, Y', strtotime($criteria['dateTo'])),
            $criteria['kind']
                ? __('reports.activity_kind') . ': ' . __('reports.activity_metric_' . $criteria['kind'])
                : null,
        ]);
    }

    private function criteria(Request $request)
    {
        $dateFrom = $this->parseDate($request->get('date_from')) ?: date('Y-m-01');
        $dateTo = $this->parseDate($request->get('date_to')) ?: date('Y-m-d');

        /* Reversed rather than rejected: a range typed backwards is an obvious slip,
           and answering it is kinder than an error that loses the other criteria. */
        if ($dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        return [
            'schoolId' => auth()->user()->school_id,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'kind' => in_array($request->get('kind'), self::KINDS, true) ? $request->get('kind') : null,
            'search' => trim((string) $request->get('search')),
            'lists' => ['kinds' => collect(self::KINDS)],
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
     * Gather the period's events from each table that records one, and put them in
     * time order.
     *
     * Every source is one query and its lookups are read once and keyed, so the
     * cost is a fixed handful of queries however busy the period.
     */
    private function classify(array $criteria)
    {
        $schoolId = $criteria['schoolId'];
        $from = $criteria['dateFrom'];
        $to = $criteria['dateTo'];
        $wants = fn ($kind) => $criteria['kind'] === null || $criteria['kind'] === $kind;

        $rows = collect();

        /* Invoices raised, and the lines put on them afterwards. These are not cash -
           they are the obligations the cash is later set against, and a timeline
           without them cannot explain where a receivable came from. */
        $invoices = FmFeesInvoice::withoutGlobalScope(AcademicSchoolScope::class)
            ->where('school_id', $schoolId)
            ->whereDate('create_date', '>=', $from)
            ->whereDate('create_date', '<=', $to)
            ->get();

        $students = SmStudent::withoutGlobalScope(ActiveStatusSchoolScope::class)
            ->where('school_id', $schoolId)
            ->whereIn('id', $invoices->pluck('student_id')->filter()->unique())
            ->get()
            ->keyBy('id');

        if ($wants('invoice_raised') && $invoices->isNotEmpty()) {
            $raisedTotals = FmFeesInvoiceChield::whereIn('fees_invoice_id', $invoices->pluck('id'))
                ->selectRaw('fees_invoice_id, COALESCE(SUM(amount),0) amount')
                ->groupBy('fees_invoice_id')
                ->get()
                ->keyBy('fees_invoice_id');

            foreach ($invoices as $invoice) {
                $rows->push([
                    'at' => $invoice->created_at ?: \Carbon\Carbon::parse($invoice->create_date),
                    'kind' => 'invoice_raised',
                    'reference' => $invoice->invoice_id ?: '#' . $invoice->id,
                    'who' => optional($students->get($invoice->student_id))->full_name,
                    'detail' => __('reports.fees_type_' . ($invoice->type ?: 'fees')),
                    'amount' => (float) optional($raisedTotals->get($invoice->id))->amount,
                    'direction' => 'billing',
                ]);
            }
        }

        /* A line whose own created_at is after the invoice's create_date was put on
           an invoice already raised - an amendment, and the thing an audit of
           billing activity is actually looking for. */
        if ($wants('line_added')) {
            $later = FmFeesInvoiceChield::whereDate('created_at', '>=', $from)
                ->whereDate('created_at', '<=', $to)
                ->where('school_id', $schoolId)
                ->with(['feesType', 'item'])
                ->get();

            $lineInvoices = FmFeesInvoice::withoutGlobalScope(AcademicSchoolScope::class)
                ->whereIn('id', $later->pluck('fees_invoice_id')->unique())
                ->get()
                ->keyBy('id');

            $lineStudents = SmStudent::withoutGlobalScope(ActiveStatusSchoolScope::class)
                ->whereIn('id', $lineInvoices->pluck('student_id')->filter()->unique())
                ->get()
                ->keyBy('id');

            foreach ($later as $line) {
                $invoice = $lineInvoices->get($line->fees_invoice_id);

                if (!$invoice || !$invoice->create_date) {
                    continue;
                }

                /* Same day as the invoice means it was part of raising it, and is
                   already reported above. */
                if ($line->created_at->toDateString() <= $invoice->create_date) {
                    continue;
                }

                $rows->push([
                    'at' => $line->created_at,
                    'kind' => 'line_added',
                    'reference' => $invoice->invoice_id ?: '#' . $invoice->id,
                    'who' => optional($lineStudents->get($invoice->student_id))->full_name,
                    'detail' => $line->sm_item_id
                        ? optional($line->item)->item_name
                        : optional($line->feesType)->name,
                    'amount' => (float) $line->amount,
                    'direction' => 'billing',
                ]);
            }
        }

        /* Receipts. The money arriving against those obligations. */
        $receipts = FmFeesTransaction::withoutGlobalScope(AcademicSchoolScope::class)
            ->where('school_id', $schoolId)
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->get();

        if ($wants('payment') && $receipts->isNotEmpty()) {
            $receiptAmounts = FmFeesTransactionChield::whereIn('fees_transaction_id', $receipts->pluck('id'))
                ->selectRaw('fees_transaction_id, COALESCE(SUM(paid_amount),0) paid')
                ->groupBy('fees_transaction_id')
                ->get()
                ->keyBy('fees_transaction_id');

            $receiptInvoices = FmFeesInvoice::withoutGlobalScope(AcademicSchoolScope::class)
                ->whereIn('id', $receipts->pluck('fees_invoice_id')->filter()->unique())
                ->get()
                ->keyBy('id');

            $receiptStudents = SmStudent::withoutGlobalScope(ActiveStatusSchoolScope::class)
                ->whereIn('id', $receiptInvoices->pluck('student_id')->filter()->unique())
                ->get()
                ->keyBy('id');

            foreach ($receipts as $receipt) {
                $invoice = $receipt->fees_invoice_id ? $receiptInvoices->get($receipt->fees_invoice_id) : null;

                $rows->push([
                    'at' => $receipt->created_at,
                    'kind' => 'payment',
                    'reference' => optional($invoice)->invoice_id ?: __('reports.receipts_metric_unapplied'),
                    'who' => $invoice ? optional($receiptStudents->get($invoice->student_id))->full_name : null,
                    'detail' => $receipt->payment_method ?: __('reports.receipts_no_method'),
                    'amount' => (float) optional($receiptAmounts->get($receipt->id))->paid,
                    'direction' => 'in',
                ]);
            }
        }

        /* Income not already on the page as a receipt. A fee collection posts an
           income row keyed to the receipt by fees_collection_id, so counting both
           would report the same peso twice. */
        if ($wants('income')) {
            $heads = SmIncomeHead::where('school_id', $schoolId)->get()->keyBy('id');
            $receiptIds = $receipts->pluck('id');

            $incomes = SmAddIncome::whereBetween('date', [$from, $to])->get();

            foreach ($incomes as $income) {
                $postedFromReceipt = $income->fees_collection_id
                    && $receiptIds->contains($income->fees_collection_id);

                $rows->push([
                    'at' => \Carbon\Carbon::parse($income->date),
                    'kind' => 'income',
                    'reference' => $income->name,
                    'who' => null,
                    'detail' => optional($heads->get($income->income_head_id))->name
                        ?: __('reports.accounts_no_head'),
                    'amount' => (float) $income->amount,
                    'direction' => 'in',
                    /* Shown, but kept out of the totals - see the class docblock. */
                    'duplicate_of_receipt' => $postedFromReceipt,
                ]);
            }
        }

        if ($wants('expense')) {
            $heads = SmExpenseHead::where('school_id', $schoolId)->get()->keyBy('id');

            foreach (SmAddExpense::whereBetween('date', [$from, $to])->get() as $expense) {
                $rows->push([
                    'at' => \Carbon\Carbon::parse($expense->date),
                    'kind' => 'expense',
                    'reference' => $expense->name,
                    'who' => null,
                    'detail' => optional($heads->get($expense->expense_head_id))->name
                        ?: __('reports.accounts_no_head'),
                    'amount' => (float) $expense->amount,
                    'direction' => 'out',
                ]);
            }
        }

        if ($wants('payroll')) {
            $payrolls = SmHrPayrollGenerate::withoutGlobalScope(AcademicSchoolScope::class)
                ->where('school_id', $schoolId)
                ->where('payroll_status', 'P')
                ->whereNotNull('payment_date')
                ->whereBetween('payment_date', [$from, $to])
                ->with(['staffs' => fn ($q) => $q->withoutGlobalScope(ActiveStatusSchoolScope::class)])
                ->get();

            foreach ($payrolls as $payroll) {
                $rows->push([
                    'at' => \Carbon\Carbon::parse($payroll->payment_date),
                    'kind' => 'payroll',
                    'reference' => $payroll->payroll_month . ' ' . $payroll->payroll_year,
                    'who' => optional($payroll->staffs)->full_name,
                    'detail' => $payroll->payment_mode ?: __('reports.receipts_no_method'),
                    'amount' => (float) $payroll->net_salary,
                    'direction' => 'out',
                ]);
            }
        }

        if ($criteria['search'] !== '') {
            $needle = mb_strtolower($criteria['search']);
            $rows = $rows->filter(function ($r) use ($needle) {
                return str_contains(mb_strtolower((string) $r['who']), $needle)
                    || str_contains(mb_strtolower((string) $r['reference']), $needle)
                    || str_contains(mb_strtolower((string) $r['detail']), $needle);
            });
        }

        /* Newest first: an audit is read back from what just happened. */
        return $rows->sortByDesc('at')->values();
    }

    private function summarise($rows)
    {
        /* The postings of fee receipts are on the page but out of every total, so
           no peso is counted on both the receipt and the income row it wrote. */
        $counted = $rows->reject(fn ($r) => !empty($r['duplicate_of_receipt']));

        $summary = [
            'all' => $rows->count(),
            'count_all' => $rows->count(),
            'billing' => round((float) $counted->where('direction', 'billing')->sum('amount'), 2),
            'money_in' => round((float) $counted->where('direction', 'in')->sum('amount'), 2),
            'money_out' => round((float) $counted->where('direction', 'out')->sum('amount'), 2),
        ];

        $summary['count_billing'] = $counted->where('direction', 'billing')->count();
        $summary['count_money_in'] = $counted->where('direction', 'in')->count();
        $summary['count_money_out'] = $counted->where('direction', 'out')->count();

        foreach (self::KINDS as $kind) {
            $ofKind = $counted->where('kind', $kind);
            $summary[$kind] = round((float) $ofKind->sum('amount'), 2);
            $summary['count_' . $kind] = $rows->where('kind', $kind)->count();
        }

        $summary['duplicates'] = $rows->filter(fn ($r) => !empty($r['duplicate_of_receipt']))->count();

        return $summary;
    }

    private function filterByMetric($rows, $metric)
    {
        switch ($metric) {
            case 'all':
                return $rows;
            case 'billing':
            case 'money_in':
            case 'money_out':
                return $rows->where('direction', str_replace(['money_'], '', $metric))->values();
            default:
                return $rows->where('kind', $metric)->values();
        }
    }
}
