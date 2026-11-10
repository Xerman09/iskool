<?php

namespace App\Http\Controllers\Admin\Report;

use App\SmStudent;
use App\Scopes\AcademicSchoolScope;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Brian2694\Toastr\Facades\Toastr;
use Modules\Fees\Entities\FmFeesInvoice;
use Modules\Fees\Entities\FmFeesTransaction;
use Modules\Fees\Entities\FmFeesTransactionChield;
use Illuminate\Support\Facades\Log;

/**
 * Every receipt taken in a period, what it was applied to, and how it was
 * tendered.
 *
 * The fourth of the standard reports and built the same way: criteria resolved
 * once, the population classified once, and every figure clickable through to
 * the rows it was counted from.
 *
 * Two questions the other reports cannot answer. The fees report is per
 * student and says what is owed, not what came in on a given day. The accounts
 * report sees fee collections only as the income rows they post
 * (SmFeesController writes a "Fees Collect" row per payment), which carries the
 * amount but not the invoice it settled. This is the register between them: one
 * line per receipt, tied to its invoice and its student.
 *
 * Receipts that point at no invoice are reported rather than dropped. Cash on
 * the books applied to nothing is the first thing an auditor asks about, and
 * leaving it out of the list is how it stays unnoticed.
 */
class CashReceiptsReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('PM');
    }

    /**
     * The method a receipt carries when it was settled out of the student's
     * wallet rather than tendered (see StudentFeesController, which deducts
     * wallet_balance and stamps the receipt with this).
     */
    private const WALLET_METHOD = 'Wallet';

    /** The fixed metrics a figure on a card can be clicked through to. */
    private const METRICS = [
        'all', 'received', 'cash_in', 'from_wallet', 'topup',
        'fine', 'waiver', 'unapplied', 'students',
    ];

    public function index(Request $request)
    {
        try {
            $criteria = $this->criteria($request);
            $rows = $this->classify($criteria);

            return view('backEnd.reports.cashReceiptsReport', array_merge($criteria['lists'], [
                'criteria' => $criteria,
                'rows' => $rows,
                'summary' => $this->summarise($rows),
                'methodTotals' => $this->methodTotals($rows),
            ]));
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
                if (!in_array($value, self::METRICS, true)
                    && !preg_match('/^method_[A-Za-z0-9_\- ]{1,50}$/', (string) $value)) {
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
                'title' => $this->metricTitle($request->metric),
                'totals' => $this->summarise($rows),
                'labels' => $this->criteriaLabels($criteria),
                'printUrl' => route('cash-receipts-report-metric', array_merge(
                    $request->except('print'),
                    ['print' => 1]
                )),
            ];

            if ($request->boolean('print')) {
                return view('backEnd.reports.cashReceiptsReportPrint', $data);
            }

            return view('backEnd.reports.partials.cashReceiptsReportList', $data)->render();
        } catch (\Exception $e) {
            Log::error(__METHOD__ . ': ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['msg' => __('common.Operation failed')], 500);
        }
    }

    /** A method metric is named after the method itself; nothing else would mean anything. */
    private function metricTitle($metric)
    {
        if (in_array($metric, self::METRICS, true)) {
            return __('reports.receipts_metric_' . $metric);
        }

        return __('fees.payment_method') . ' - ' . substr($metric, strlen('method_'));
    }

    private function criteriaLabels(array $criteria)
    {
        return array_filter([
            __('reports.accounts_period') . ': '
                . date('M d, Y', strtotime($criteria['dateFrom']))
                . ' - ' . date('M d, Y', strtotime($criteria['dateTo'])),
            $criteria['method']
                ? __('fees.payment_method') . ': ' . $criteria['method']
                : null,
        ]);
    }

    private function criteria(Request $request)
    {
        $schoolId = auth()->user()->school_id;

        /* The month to date, because a receipts register is read against a day's or
           a month's takings rather than against a school year. */
        $dateFrom = $this->parseDate($request->get('date_from')) ?: date('Y-m-01');
        $dateTo = $this->parseDate($request->get('date_to')) ?: date('Y-m-d');

        /* Reversed rather than rejected: a range typed backwards is an obvious slip,
           and answering it is kinder than an error that loses the other criteria. */
        if ($dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        /* The methods actually used, rather than the settings list - a register is
           of what happened, and a method nobody tendered is not a filter worth
           offering. */
        $methods = FmFeesTransaction::withoutGlobalScope(AcademicSchoolScope::class)
            ->where('school_id', $schoolId)
            ->whereNotNull('payment_method')
            ->distinct()
            ->pluck('payment_method')
            ->filter()
            ->sort()
            ->values();

        return [
            'schoolId' => $schoolId,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'method' => $methods->contains($request->get('method')) ? $request->get('method') : null,
            'search' => trim((string) $request->get('search')),
            'lists' => compact('methods'),
        ];
    }

    /** A date the pickers can hand back, or null - never a guess at what was meant. */
    private function parseDate($value)
    {
        if (!$value) {
            return null;
        }

        $time = strtotime($value);

        return $time ? date('Y-m-d', $time) : null;
    }

    /**
     * One row per receipt, with the invoice and student it was applied to.
     *
     * Four queries: the receipts, their lines summed in the database, the invoices
     * they point at, and the students behind those. The module's own screens read
     * the amount off FmFeesTransaction::$paid_amount, which re-queries the lines on
     * every read - a SUM per receipt, and the reason none of them totals.
     */
    private function classify(array $criteria)
    {
        $schoolId = $criteria['schoolId'];

        /* Scope stripped because it pins the model to the session's academic year
           while this report is asked about a date range, which may cross one. */
        $receipts = FmFeesTransaction::withoutGlobalScope(AcademicSchoolScope::class)
            ->where('school_id', $schoolId)
            ->whereDate('created_at', '>=', $criteria['dateFrom'])
            ->whereDate('created_at', '<=', $criteria['dateTo'])
            ->when($criteria['method'], fn ($q) => $q->where('payment_method', $criteria['method']))
            ->orderBy('created_at')
            ->get();

        if ($receipts->isEmpty()) {
            return collect();
        }

        $amounts = FmFeesTransactionChield::whereIn('fees_transaction_id', $receipts->pluck('id'))
            ->selectRaw('fees_transaction_id,
                COALESCE(SUM(paid_amount),0) paid,
                COALESCE(SUM(fine),0) fine,
                COALESCE(SUM(weaver),0) weaver')
            ->groupBy('fees_transaction_id')
            ->get()
            ->keyBy('fees_transaction_id');

        $invoices = FmFeesInvoice::withoutGlobalScope(AcademicSchoolScope::class)
            ->whereIn('id', $receipts->pluck('fees_invoice_id')->filter()->unique())
            ->get(['id', 'invoice_id', 'student_id', 'staff_id', 'type'])
            ->keyBy('id');

        /* Whole models, not a column list. full_name on SmStudent is an accessor
           over first_name and last_name, so selecting the column of that name hands
           back a single space - truthy, so every fallback for a missing name steps
           aside and the column renders blank. The set is bounded by the receipts in
           the period, so there is nothing to save by narrowing it. */
        $students = SmStudent::whereIn('id', $invoices->pluck('student_id')->filter()->unique())
            ->get()
            ->keyBy('id');

        $rows = $receipts->map(function ($receipt) use ($amounts, $invoices, $students) {
            $amount = $amounts->get($receipt->id);
            $invoice = $receipt->fees_invoice_id ? $invoices->get($receipt->fees_invoice_id) : null;
            $student = $invoice && $invoice->student_id ? $students->get($invoice->student_id) : null;

            return [
                'receipt' => $receipt,
                'date' => $receipt->created_at,
                'invoice' => $invoice,
                'student' => $student,
                'method' => $receipt->payment_method ?: __('reports.receipts_no_method'),
                'amount' => (float) optional($amount)->paid,
                'fine' => (float) optional($amount)->fine,
                'waiver' => (float) optional($amount)->weaver,
                /* Settled out of the wallet, which is not money arriving: the school
                   was already holding it, and the cash came in whenever the wallet
                   was topped up. Counted as a receipt it would overstate the day's
                   takings and never reconcile against a deposit. */
                'from_wallet' => $receipt->payment_method === self::WALLET_METHOD,
                /* The other half of the same story: money handed over on this
                   receipt that went into the wallet rather than against the invoice.
                   It is cash in, and it settles nothing. */
                'topup' => (float) $receipt->add_wallet_money,
                /* Cash on the books against no invoice. Either the invoice was
                   deleted under it or the receipt was never applied; both are the
                   same problem to whoever has to reconcile, and both are worth a
                   name on the page. */
                'unapplied' => !$invoice,
                'note' => $receipt->payment_note,
            ];
        });

        if ($criteria['search'] !== '') {
            $needle = mb_strtolower($criteria['search']);
            $rows = $rows->filter(function ($r) use ($needle) {
                return str_contains(mb_strtolower((string) optional($r['student'])->full_name), $needle)
                    || str_contains(mb_strtolower((string) optional($r['invoice'])->invoice_id), $needle)
                    || str_contains(mb_strtolower((string) $r['receipt']->invoice_number), $needle);
            });
        }

        /* Newest first: a register is read back from the latest takings. */
        return $rows->sortByDesc('date')->values();
    }

    /**
     * Takings per method, largest first - the figure a cashier reconciles a drawer
     * and a bank deposit against.
     */
    private function methodTotals($rows)
    {
        return $rows->groupBy('method')
            ->map(fn ($group, $method) => [
                'metric' => 'method_' . $method,
                'name' => $method,
                'amount' => round((float) $group->sum('amount'), 2),
                'receipts' => $group->count(),
            ])
            ->sortByDesc('amount')
            ->values();
    }

    private function summarise($rows)
    {
        $summary = [
            'all' => $rows->count(),
            'count_all' => $rows->count(),
            'received' => round((float) $rows->sum('amount'), 2),
            'fine' => round((float) $rows->sum('fine'), 2),
            'waiver' => round((float) $rows->sum('waiver'), 2),
        ];

        $summary['count_received'] = $rows->where('amount', '>', 0)->count();
        $summary['count_fine'] = $rows->where('fine', '>', 0)->count();
        $summary['count_waiver'] = $rows->where('waiver', '>', 0)->count();

        /* Two different true answers, and a register that gives only one of them
           misleads whoever is holding the other.

           Received is everything applied to invoices, and it is the figure that
           reconciles with the fees report's Collected. Cash In is money that
           actually arrived in the period - everything tendered, plus top-ups, less
           anything settled out of a wallet - and it is the figure that reconciles
           with a drawer and a bank deposit. They are equal only in a school with no
           wallet. */
        $fromWallet = $rows->where('from_wallet', true);
        $summary['from_wallet'] = round((float) $fromWallet->sum('amount'), 2);
        $summary['count_from_wallet'] = $fromWallet->count();

        $topups = $rows->where('topup', '>', 0);
        $summary['topup'] = round((float) $rows->sum('topup'), 2);
        $summary['count_topup'] = $topups->count();

        $summary['cash_in'] = round(
            (float) $rows->where('from_wallet', false)->sum('amount') + $summary['topup'],
            2
        );
        $summary['count_cash_in'] = $rows
            ->filter(fn ($r) => (!$r['from_wallet'] && $r['amount'] > 0) || $r['topup'] > 0)
            ->count();

        $unapplied = $rows->where('unapplied', true);
        $summary['unapplied'] = round((float) $unapplied->sum('amount'), 2);
        $summary['count_unapplied'] = $unapplied->count();

        /* How many people the takings came from, which says whether a day's total
           is one large settlement or forty small ones. */
        $summary['students'] = $rows->pluck('student.id')->filter()->unique()->count();
        $summary['count_students'] = $summary['students'];

        return $summary;
    }

    private function filterByMetric($rows, $metric)
    {
        if (str_starts_with((string) $metric, 'method_')) {
            $method = substr($metric, strlen('method_'));

            return $rows->where('method', $method)->values();
        }

        switch ($metric) {
            case 'all':
            case 'received':
                return $rows;
            case 'from_wallet':
                return $rows->where('from_wallet', true)->values();
            case 'topup':
                return $rows->where('topup', '>', 0)->values();
            case 'cash_in':
                return $rows
                    ->filter(fn ($r) => (!$r['from_wallet'] && $r['amount'] > 0) || $r['topup'] > 0)
                    ->values();
            case 'fine':
                return $rows->where('fine', '>', 0)->values();
            case 'waiver':
                return $rows->where('waiver', '>', 0)->values();
            case 'unapplied':
                return $rows->where('unapplied', true)->values();
            default:
                return $rows->filter(fn ($r) => $r['student'])->values();
        }
    }
}
